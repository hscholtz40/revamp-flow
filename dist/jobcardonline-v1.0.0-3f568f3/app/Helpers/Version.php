<?php

namespace App\Helpers;

class Version
{
    public static function get(): string
    {
        // Try to get version from package.json
        $packageJsonPath = base_path('package.json');
        if (file_exists($packageJsonPath)) {
            $packageJson = json_decode(file_get_contents($packageJsonPath), true);
            if (isset($packageJson['version'])) {
                return $packageJson['version'];
            }
        }

        // Fallback to git tag or commit hash
        $gitTag = trim(shell_exec('git describe --tags --exact-match 2>/dev/null') ?? '');
        if ($gitTag) {
            return $gitTag;
        }

        $gitHash = trim(shell_exec('git rev-parse --short HEAD 2>/dev/null') ?? '');
        if ($gitHash) {
            return 'dev-' . $gitHash;
        }

        return 'unknown';
    }
}
