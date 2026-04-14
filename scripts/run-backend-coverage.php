<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$coverageDir = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'coverage'.DIRECTORY_SEPARATOR.'backend';
$cloverPath = $coverageDir.DIRECTORY_SEPARATOR.'clover.xml';
$htmlPath = $coverageDir.DIRECTORY_SEPARATOR.'html';
$pestBinary = $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest';
$minimumPercent = 20.0;

if (! is_dir($coverageDir) && ! mkdir($coverageDir, 0777, true) && ! is_dir($coverageDir)) {
    fwrite(STDERR, "Unable to create coverage directory: {$coverageDir}\n");
    exit(1);
}

if (! file_exists($pestBinary)) {
    fwrite(STDERR, "Pest binary not found at: {$pestBinary}\n");
    exit(1);
}

$phpBinary = escapeshellarg(PHP_BINARY);
$pest = escapeshellarg($pestBinary);
$clover = escapeshellarg($cloverPath);
$html = escapeshellarg($htmlPath);

if (extension_loaded('xdebug')) {
    $command = "{$phpBinary} -d xdebug.mode=coverage {$pest} --coverage-clover {$clover} --coverage-html {$html} --coverage-text";
} elseif (extension_loaded('pcov')) {
    $command = "{$phpBinary} -d pcov.enabled=1 {$pest} --coverage-clover {$clover} --coverage-html {$html} --coverage-text";
} else {
    fwrite(
        STDERR,
        "Backend coverage requires Xdebug or PCOV in the active PHP runtime. ".
        "The CI workflow provisions Xdebug automatically.\n",
    );
    exit(1);
}

passthru($command, $exitCode);

if ($exitCode !== 0) {
    exit($exitCode);
}

if (! file_exists($cloverPath)) {
    fwrite(
        STDERR,
        "Backend tests finished, but no Clover report was generated. Ensure the selected coverage driver is working.\n",
    );
    exit(1);
}

$xml = simplexml_load_file($cloverPath);

if ($xml === false) {
    fwrite(STDERR, "Unable to parse Clover report: {$cloverPath}\n");
    exit(1);
}

$project = $xml->project;
$lineRate = $project ? (float) ($project['line-rate'] ?? 0) : 0.0;
$linePercent = round($lineRate * 100, 2);

fwrite(STDOUT, sprintf("Backend line coverage: %.2f%% (minimum %.2f%%)\n", $linePercent, $minimumPercent));

if ($linePercent < $minimumPercent) {
    fwrite(
        STDERR,
        sprintf(
            "Backend coverage threshold failed: %.2f%% is below the required %.2f%%.\n",
            $linePercent,
            $minimumPercent,
        ),
    );
    exit(1);
}
