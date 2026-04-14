<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/check-backend-coverage.php <clover-xml-path> <minimum-percent>\n");
    exit(1);
}

$reportPath = $argv[1];
$minimumPercent = (float) $argv[2];

if (! file_exists($reportPath)) {
    fwrite(STDERR, "Coverage report not found: {$reportPath}\n");
    exit(1);
}

$xml = simplexml_load_file($reportPath);

if ($xml === false) {
    fwrite(STDERR, "Unable to parse Clover report: {$reportPath}\n");
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
