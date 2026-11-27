# Laravel bridge compatibility checks

The Laravel 13 project locks Laravel Framework 13.x and PHPUnit 13 for PHP 8.4+. The `5.6/` project locks Laravel Framework 5.6 and PHPUnit 9.6 for PHP 7.4. Both reuse package source and test files from the repository; their dependencies stay under this directory and do not affect the root Composer project.

From the repository root, run:

```powershell
./tools/test-laravel-bridge.ps1 -LaravelVersion 13
./tools/test-laravel-bridge.ps1 -LaravelVersion 5.6
```

The runner installs locked dependencies into a temporary vendor directory, checks the platform requirements, runs all Laravel bridge test files, and checks the public API baseline. Laravel 5.6 is run with PHP 7.4 to match the package's minimum supported runtime. The Laravel 13 run uses PHP 8.4.
