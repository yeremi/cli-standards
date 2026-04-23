<?php

declare(strict_types=1);

$verbose = in_array('--verbose', $argv, true);

$root = dirname(__DIR__);
$phpcs = $root . '/vendor/bin/phpcs';

if (!is_file($phpcs)) {
    fwrite(STDERR, "PHPCS binary not found at {$phpcs}. Run composer install first.\n");
    exit(1);
}

$cases = [
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/Comprehensive/fail.php', 'errors' => 9, 'warnings' => 7],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/CLILinter/fail.php', 'errors' => 0, 'warnings' => 8],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/OutputMethod/fail.php', 'errors' => 4, 'warnings' => 1],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/Safety/fail.php', 'errors' => 3, 'warnings' => 7],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/OutputMethod/pass.php', 'errors' => 0, 'warnings' => 0],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/Safety/pass.php', 'errors' => 0, 'warnings' => 0],
    ['standard' => 'CLIStandardsMust', 'fixture' => 'tests/Fixtures/Comprehensive/fail.php', 'errors' => 9, 'warnings' => 0],
    ['standard' => 'CLIStandardsMust', 'fixture' => 'tests/Fixtures/CLILinter/fail.php', 'errors' => 0, 'warnings' => 0],
    ['standard' => 'CLIStandardsMust', 'fixture' => 'tests/Fixtures/Safety/fail.php', 'errors' => 3, 'warnings' => 0],
];

$hasFailure = false;

foreach ($cases as $case) {
    $fixture = $case['fixture'];
    $fixturePath = $root . '/' . $fixture;
    $command = escapeshellarg($phpcs)
        . ' --standard=' . escapeshellarg($case['standard']) . ' --report=json '
        . escapeshellarg($fixturePath)
        . ' 2>&1';

    $output = [];
    exec($command, $output, $exitCode);

    $json = implode("\n", $output);
    $report = json_decode($json, true);

    if (!is_array($report) || !isset($report['totals']['errors'], $report['totals']['warnings'])) {
        fwrite(STDERR, "Could not parse PHPCS JSON report for {$fixture}.\n");
        fwrite(STDERR, $json . "\n");
        exit(1);
    }

    $actual = [
        'errors' => (int) $report['totals']['errors'],
        'warnings' => (int) $report['totals']['warnings'],
    ];

    $expectedExitCode = ($case['errors'] > 0 || $case['warnings'] > 0) ? 1 : 0;

    if ($actual['errors'] !== $case['errors']
        || $actual['warnings'] !== $case['warnings']
        || $exitCode !== $expectedExitCode
    ) {
        $hasFailure = true;
        fwrite(
            STDERR,
            sprintf(
                "Fixture mismatch for %s using %s. Expected %d errors / %d warnings with exit code %d, got %d errors / %d warnings with exit code %d.\n",
                $fixture,
                $case['standard'],
                $case['errors'],
                $case['warnings'],
                $expectedExitCode,
                $actual['errors'],
                $actual['warnings'],
                $exitCode
            )
        );

        continue;
    }

    if ($verbose) {
        fwrite(
            STDOUT,
            sprintf(
                "[ok] %s => %d errors / %d warnings\n",
                $case['standard'] . ' :: ' . $fixture,
                $actual['errors'],
                $actual['warnings']
            )
        );
    }
}

if ($hasFailure) {
    exit(1);
}

fwrite(STDOUT, "All fixture expectations passed.\n");
