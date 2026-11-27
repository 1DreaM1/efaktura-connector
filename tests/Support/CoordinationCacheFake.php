<?php

namespace DreaM1\EfakturaConnector\Tests\Support;

use RuntimeException;

/** Small cache facade fake for Laravel adapters without requiring a cache driver in tests. */
final class CoordinationCacheFake
{
    /** @var array<string, mixed> */
    private array $values = [];
    /** @var array<string, CoordinationCacheLockFake> */
    private array $locks           = [];
    private bool $failLocks        = false;
    private bool $failReads        = false;
    private ?string $lastStoreName = null;
    /** @var callable|null */
    private $afterNextGet;

    /**
     * Configures whether lock requests should throw a synthetic cache failure.
     *
     * @param bool $fail Whether lock creation should fail.
     */
    public function failLocks(bool $fail = true): void
    {
        $this->failLocks = $fail;
    }

    /**
     * Configures whether value reads and writes should throw a synthetic cache failure.
     *
     * @param bool $fail Whether reads and writes should fail.
     */
    public function failReads(bool $fail = true): void
    {
        $this->failReads = $fail;
    }

    /**
     * Reads a cached fake value and returns the default when the key is absent.
     *
     * @param  string $key     Cache entry key.
     * @param  mixed  $default Value to return when the key is not stored.
     * @return mixed  Stored value or the supplied default.
     */
    public function get(string $key, $default = null)
    {
        if ($this->failReads) {
            throw new RuntimeException('Synthetic cache read failure.');
        }

        $value = $this->values[$key] ?? $default;

        if ($this->afterNextGet !== null) {
            $callback           = $this->afterNextGet;
            $this->afterNextGet = null;
            $callback($this, $key, $value);
        }

        return $value;
    }

    /**
     * Runs a callback after the next cache read, then clears it.
     *
     * @param callable(self, string, mixed): void $callback Callback receiving this cache, key and read value.
     */
    public function afterNextGet(callable $callback): void
    {
        $this->afterNextGet = $callback;
    }

    /**
     * Stores a fake cache value; the test fake does not expire entries.
     *
     * @param  string $key   Cache entry key.
     * @param  mixed  $value Value to store.
     * @param  mixed  $ttl   Ignored by this fake; accepted to match Laravel's cache method.
     * @return bool   Always true after storing the value.
     */
    public function put(string $key, $value, $ttl = null): bool
    {
        if ($this->failReads) {
            throw new RuntimeException('Synthetic cache write failure.');
        }

        $this->values[$key] = $value;

        return true;
    }

    /**
     * Removes a fake cache value.
     *
     * @param  string $key Cache entry key.
     * @return bool   Always true after removing the key.
     */
    public function forget(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    /** Selects a synthetic cache store and returns this fake store. */
    public function store(?string $name = null): self
    {
        $this->lastStoreName = $name;

        return $this;
    }

    /** Returns the last named cache store selected through the Cache facade. */
    public function lastStoreName(): ?string
    {
        return $this->lastStoreName;
    }

    /**
     * Returns the fake lock for a cache key or throws when lock failures are enabled.
     *
     * @param  string                    $name    Lock key.
     * @param  int                       $seconds Lease duration passed to the Laravel cache lock.
     * @return CoordinationCacheLockFake Fake lock associated with the key.
     * @throws RuntimeException          when lock failures are enabled
     */
    public function lock(string $name, int $seconds = 0): CoordinationCacheLockFake
    {
        if ($this->failLocks) {
            throw new RuntimeException('Synthetic cache lock failure.');
        }

        if (! isset($this->locks[$name])) {
            $this->locks[$name] = new CoordinationCacheLockFake($seconds);
        }

        return $this->locks[$name];
    }
}
