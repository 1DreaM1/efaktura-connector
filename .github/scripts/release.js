module.exports = async ({ github, context, core }) => {
  const { owner, repo } = context.repo;
  const defaultBranch = context.payload.repository.default_branch;
  const commits = (context.payload.commits || []).map((commit) => commit.id).filter(Boolean);
  if (commits.length === 0) commits.push(context.sha);

  const associatedPullRequests = new Map();
  for (const commitSha of [...new Set(commits)]) {
    const { data: pullRequests } = await github.rest.repos.listPullRequestsAssociatedWithCommit({
      owner,
      repo,
      commit_sha: commitSha,
    });

    for (const pullRequest of pullRequests) {
      if (pullRequest.merged_at && pullRequest.base.ref === defaultBranch) {
        associatedPullRequests.set(pullRequest.number, pullRequest);
      }
    }
  }

  if (associatedPullRequests.size === 0) {
    core.notice('No merged pull request was associated with this push; no release was created.');
    return;
  }

  // Apply exactly one of these labels to a PR to request its release version bump.
  const allowedBumps = new Set(['release:patch', 'release:minor', 'release:major']);
  const requestedBumps = [];
  for (const pullRequest of associatedPullRequests.values()) {
    const labels = (pullRequest.labels || []).map((label) => label.name.toLowerCase());
    const releaseLabels = labels.filter((label) => allowedBumps.has(label));

    if (releaseLabels.length > 1) {
      throw new Error(`Pull request #${pullRequest.number} has multiple release labels.`);
    }
    if (releaseLabels.length === 1) {
      requestedBumps.push({
        bump: releaseLabels[0].slice('release:'.length),
        pullRequest: pullRequest.number,
      });
    }
  }

  if (requestedBumps.length === 0) {
    core.notice('No release:patch, release:minor, or release:major label was found; no release was created.');
    return;
  }

  const bumpRank = { patch: 1, minor: 2, major: 3 };
  const bump = requestedBumps.reduce((selected, request) =>
    bumpRank[request.bump] > bumpRank[selected] ? request.bump : selected,
    'patch'
  );

  async function getVersionTags() {
    return github.paginate(github.rest.git.listMatchingRefs, {
      owner,
      repo,
      ref: 'tags/v',
    });
  }

  function parseVersionTag(ref) {
    const match = ref.ref.match(/^refs\/tags\/v(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/);
    return match ? { ref, major: Number(match[1]), minor: Number(match[2]), patch: Number(match[3]) } : null;
  }

  function compareVersions(left, right) {
    return left.major - right.major || left.minor - right.minor || left.patch - right.patch;
  }

  function nextVersion(latest) {
    if (bump === 'major') return `v${latest.major + 1}.0.0`;
    if (bump === 'minor') return `v${latest.major}.${latest.minor + 1}.0`;
    return `v${latest.major}.${latest.minor}.${latest.patch + 1}`;
  }

  for (let attempt = 0; attempt < 4; attempt += 1) {
    const refs = await getVersionTags();
    if (refs.some((ref) => ref.object.type === 'commit' && ref.object.sha === context.sha)) {
      core.notice('A version tag already points to this commit; skipping duplicate release.');
      return;
    }

    const versions = refs.map(parseVersionTag).filter(Boolean).sort(compareVersions);
    const latest = versions.length > 0 ? versions[versions.length - 1] : { major: 0, minor: 0, patch: 0 };
    const tagName = nextVersion(latest);

    try {
      const { data: release } = await github.rest.repos.createRelease({
        owner,
        repo,
        tag_name: tagName,
        target_commitish: context.sha,
        name: tagName,
        generate_release_notes: true,
      });
      // Releases created with GITHUB_TOKEN do not trigger the `release: published` workflow.
      await github.rest.actions.createWorkflowDispatch({
        owner,
        repo,
        workflow_id: 'provenance.yml',
        ref: tagName,
        inputs: { tag: tagName },
      });
      core.notice(`Created ${tagName} for pull request(s): ${requestedBumps.map((request) => `#${request.pullRequest}`).join(', ')}. ${release.html_url}`);
      return;
    } catch (error) {
      if (error.status !== 422) throw error;

      const updatedRefs = await getVersionTags();
      const tagWasCreated = updatedRefs.some((ref) => ref.ref === `refs/tags/${tagName}`);
      if (!tagWasCreated) throw error;
    }
  }

  throw new Error('Could not create a unique release tag after concurrent release attempts.');
};
