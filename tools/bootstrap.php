<?php

/** Loads the shared resource-path, hash-algorithm, and validation-resource classes for repository tools. */

require_once dirname(__DIR__) . '/src/Infrastructure/ResourcePath.php';
require_once dirname(__DIR__) . '/src/Support/HashAlgorithm.php';
require_once dirname(__DIR__) . '/src/Infrastructure/EFaktura/Validation/ValidationResources.php';
require_once __DIR__ . '/RepositoryToolDefaults.php';
