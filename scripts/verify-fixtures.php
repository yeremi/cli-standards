<?php

declare(strict_types=1);

/*
 * Fixture regression suite.
 *
 * Each case runs PHPCS with one standard (or ruleset file) against one fixture
 * and compares the reported messages per sniff source code
 * (e.g. CLIStandards.Documentation.DocblockOptions.MissingDocblock).
 * Any source not listed in 'sources' is expected to report nothing.
 * Totals are derived from the PHPCS report and checked as well.
 */

$verbose = in_array('--verbose', $argv, true);

$root = dirname(__DIR__);
$phpcs = $root . '/vendor/bin/phpcs';

if (!is_file($phpcs)) {
    fwrite(STDERR, "PHPCS binary not found at {$phpcs}. Run composer install first.\n");
    exit(1);
}

$cases = [
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/Comprehensive/fail.php',
        'errors' => 10,
        'warnings' => 7,
        'sources' => [
            'CLIStandards.Arguments.FlagExtraction.DirectAccess' => 1,
            'CLIStandards.Arguments.FlagNaming.NonKebabCase' => 1,
            'CLIStandards.CommandStructure.CommandRegistration.MissingNamespace' => 1,
            'CLIStandards.Documentation.DocblockExamples.MissingExamplesSection' => 2,
            'CLIStandards.Documentation.DocblockOptions.MissingOptionsSection' => 1,
            'CLIStandards.Documentation.SinceAnnotation.MissingSince' => 2,
            'CLIStandards.ErrorHandling.ErrorSeverity.DirectExit' => 2,
            'CLIStandards.ErrorHandling.WpErrorHandling.UncheckedWpError' => 1,
            'CLIStandards.Output.OutputMethod.RawOutput' => 1,
            'CLIStandards.Safety.ConfirmOnDelete.MissingConfirm' => 2,
            'CLIStandards.Safety.DryRunSupport.MissingDryRun' => 2,
            'CLIStandards.Scalability.BatchProcessing.UnboundedQuery' => 1,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/CLILinter/fail.php',
        'errors' => 0,
        'warnings' => 7,
        'sources' => [
            'CLIStandards.Arguments.FlagExtraction.DirectAccess' => 1,
            'CLIStandards.Documentation.SinceAnnotation.MissingSince' => 1,
            'CLIStandards.Output.AvailableFields.MissingAvailableFields' => 1,
            'CLIStandards.Output.StdoutStderr.MixedOutput' => 1,
            'CLIStandards.Safety.DryRunSupport.MissingDryRun' => 1,
            'CLIStandards.Scalability.BatchSizeFlag.MissingBatchSizeFlag' => 1,
            'CLIStandards.Scalability.ProgressBar.MissingProgressBar' => 1,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/OutputMethod/fail.php',
        'errors' => 5,
        'warnings' => 1,
        'sources' => [
            'CLIStandards.Documentation.DocblockOptions.MissingDocblock' => 1,
            'CLIStandards.Output.FormatSupport.MissingFormatSupport' => 1,
            'CLIStandards.Output.OutputMethod.RawOutput' => 4,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/Safety/fail.php',
        'errors' => 3,
        'warnings' => 7,
        'sources' => [
            'CLIStandards.Arguments.FlagExtraction.DirectAccess' => 1,
            'CLIStandards.Documentation.SinceAnnotation.MissingSince' => 3,
            'CLIStandards.Safety.ConfirmOnDelete.MissingConfirm' => 3,
            'CLIStandards.Safety.DryRunSupport.MissingDryRun' => 3,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/Documentation/fail.php',
        'errors' => 8,
        'warnings' => 2,
        'sources' => [
            'CLIStandards.Documentation.DocblockExamples.MissingExamplesSection' => 2,
            'CLIStandards.Documentation.DocblockOptions.MissingDocblock' => 4,
            'CLIStandards.Documentation.DocblockOptions.MissingOptionsSection' => 2,
            'CLIStandards.Documentation.SinceAnnotation.MissingSince' => 1,
            'CLIStandards.Output.AvailableFields.MissingAvailableFields' => 1,
        ],
    ],
    [
        'standard' => 'tests/Fixtures/Documentation/ignore-methods.xml',
        'fixture' => 'tests/Fixtures/Documentation/ignore-methods.php',
        'errors' => 1,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Documentation.DocblockOptions.MissingDocblock' => 1,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/Arguments/fail.php',
        'errors' => 2,
        'warnings' => 2,
        'sources' => [
            'CLIStandards.Arguments.FlagExtraction.DirectAccess' => 2,
            'CLIStandards.Arguments.FlagNaming.NonKebabCase' => 2,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/Scalability/fail.php',
        'errors' => 0,
        'warnings' => 3,
        'sources' => [
            'CLIStandards.Scalability.ProgressBar.MissingProgressBar' => 3,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/Classification/fail.php',
        'errors' => 4,
        'warnings' => 4,
        'sources' => [
            'CLIStandards.Safety.ConfirmOnDelete.MissingConfirm' => 4,
            'CLIStandards.Safety.DryRunSupport.MissingDryRun' => 4,
        ],
    ],
    [
        'standard' => 'tests/Fixtures/Classification/custom-destructive.xml',
        'fixture' => 'tests/Fixtures/Classification/custom-destructive.php',
        'errors' => 1,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Safety.ConfirmOnDelete.MissingConfirm' => 1,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/Delegation/fail.php',
        'errors' => 3,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Safety.ConfirmOnDelete.MissingConfirm' => 3,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/Registration/fail.php',
        'errors' => 4,
        'warnings' => 3,
        'sources' => [
            'CLIStandards.Arguments.FlagExtraction.DirectAccess' => 1,
            'CLIStandards.Arguments.FlagNaming.NonKebabCase' => 1,
            'CLIStandards.Documentation.DocblockExamples.MissingExamplesSection' => 1,
            'CLIStandards.Documentation.DocblockOptions.MissingDocblock' => 1,
            'CLIStandards.Documentation.SinceAnnotation.MissingSince' => 1,
            'CLIStandards.Safety.ConfirmOnDelete.MissingConfirm' => 1,
            'CLIStandards.Safety.DryRunSupport.MissingDryRun' => 1,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/ErrorHandling/fail.php',
        'errors' => 1,
        'warnings' => 4,
        'sources' => [
            'CLIStandards.ErrorHandling.ErrorSeverity.DirectExit' => 1,
            'CLIStandards.ErrorHandling.WpErrorHandling.UncapturedWpError' => 1,
            'CLIStandards.ErrorHandling.WpErrorHandling.UncheckedWpError' => 3,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/Confirm/fail.php',
        'errors' => 2,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Safety.ConfirmPassthrough.MissingAssocArgs' => 2,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/CommandStructure/fail.php',
        'errors' => 3,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.CommandStructure.CommandRegistration.MissingNamespace' => 3,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/Classes/fail.php',
        'errors' => 4,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Documentation.DocblockOptions.MissingDocblock' => 4,
        ],
    ],
    [
        'standard' => 'tests/Fixtures/Classes/ignore-classes.xml',
        'fixture' => 'tests/Fixtures/Classes/ignore-classes.php',
        'errors' => 1,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Documentation.DocblockOptions.MissingDocblock' => 1,
        ],
    ],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/Classes/pass.php', 'errors' => 0, 'warnings' => 0, 'sources' => []],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/CommandStructure/pass.php', 'errors' => 0, 'warnings' => 0, 'sources' => []],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/Confirm/pass.php', 'errors' => 0, 'warnings' => 0, 'sources' => []],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/ErrorHandling/pass.php', 'errors' => 0, 'warnings' => 0, 'sources' => []],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/Registration/pass.php', 'errors' => 0, 'warnings' => 0, 'sources' => []],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/Delegation/pass.php', 'errors' => 0, 'warnings' => 0, 'sources' => []],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/Classification/pass.php', 'errors' => 0, 'warnings' => 0, 'sources' => []],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/OutputMethod/pass.php', 'errors' => 0, 'warnings' => 0, 'sources' => []],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/Safety/pass.php', 'errors' => 0, 'warnings' => 0, 'sources' => []],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/Documentation/pass.php', 'errors' => 0, 'warnings' => 0, 'sources' => []],
    ['standard' => 'CLIStandards', 'fixture' => 'tests/Fixtures/Scalability/pass.php', 'errors' => 0, 'warnings' => 0, 'sources' => []],
    [
        'standard' => 'CLIStandardsMust',
        'fixture' => 'tests/Fixtures/Comprehensive/fail.php',
        'errors' => 10,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Arguments.FlagNaming.NonKebabCase' => 1,
            'CLIStandards.CommandStructure.CommandRegistration.MissingNamespace' => 1,
            'CLIStandards.Documentation.DocblockExamples.MissingExamplesSection' => 2,
            'CLIStandards.Documentation.DocblockOptions.MissingOptionsSection' => 1,
            'CLIStandards.ErrorHandling.ErrorSeverity.DirectExit' => 2,
            'CLIStandards.Output.OutputMethod.RawOutput' => 1,
            'CLIStandards.Safety.ConfirmOnDelete.MissingConfirm' => 2,
        ],
    ],
    ['standard' => 'CLIStandardsMust', 'fixture' => 'tests/Fixtures/CLILinter/fail.php', 'errors' => 0, 'warnings' => 0, 'sources' => []],
    [
        'standard' => 'CLIStandardsMust',
        'fixture' => 'tests/Fixtures/Documentation/fail.php',
        'errors' => 8,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Documentation.DocblockExamples.MissingExamplesSection' => 2,
            'CLIStandards.Documentation.DocblockOptions.MissingDocblock' => 4,
            'CLIStandards.Documentation.DocblockOptions.MissingOptionsSection' => 2,
        ],
    ],
    [
        'standard' => 'CLIStandardsMust',
        'fixture' => 'tests/Fixtures/Classification/fail.php',
        'errors' => 4,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Safety.ConfirmOnDelete.MissingConfirm' => 4,
        ],
    ],
    [
        'standard' => 'CLIStandardsMust',
        'fixture' => 'tests/Fixtures/Registration/fail.php',
        'errors' => 4,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Arguments.FlagNaming.NonKebabCase' => 1,
            'CLIStandards.Documentation.DocblockExamples.MissingExamplesSection' => 1,
            'CLIStandards.Documentation.DocblockOptions.MissingDocblock' => 1,
            'CLIStandards.Safety.ConfirmOnDelete.MissingConfirm' => 1,
        ],
    ],
    [
        'standard' => 'CLIStandardsMust',
        'fixture' => 'tests/Fixtures/ErrorHandling/fail.php',
        'errors' => 1,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.ErrorHandling.ErrorSeverity.DirectExit' => 1,
        ],
    ],
    ['standard' => 'CLIStandardsMust', 'fixture' => 'tests/Fixtures/Scalability/fail.php', 'errors' => 0, 'warnings' => 0, 'sources' => []],
    [
        'standard' => 'CLIStandardsMust',
        'fixture' => 'tests/Fixtures/Safety/fail.php',
        'errors' => 3,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Safety.ConfirmOnDelete.MissingConfirm' => 3,
        ],
    ],
];

$hasFailure = false;

foreach ($cases as $case) {
    $fixture = $case['fixture'];
    $fixturePath = $root . '/' . $fixture;
    $standard = $case['standard'];

    // A standard ending in .xml is a ruleset file relative to the repo root.
    if (substr($standard, -4) === '.xml') {
        $standard = $root . '/' . $standard;
    }

    $command = escapeshellarg($phpcs)
        . ' --standard=' . escapeshellarg($standard) . ' --report=json '
        . escapeshellarg($fixturePath)
        . ' 2>&1';

    $output = [];
    exec($command, $output, $exitCode);

    $json = implode("\n", $output);
    $report = json_decode($json, true);

    if (!is_array($report) || !isset($report['totals']['errors'], $report['totals']['warnings'], $report['files'])) {
        fwrite(STDERR, "Could not parse PHPCS JSON report for {$fixture}.\n");
        fwrite(STDERR, $json . "\n");
        exit(1);
    }

    $actualSources = [];
    $messages = [];
    foreach ($report['files'] as $file) {
        foreach ($file['messages'] as $message) {
            $source = $message['source'];
            $actualSources[$source] = ($actualSources[$source] ?? 0) + 1;
            $messages[] = sprintf('    %d:%d %s', $message['line'], $message['column'], $source);
        }
    }

    $expectedSources = $case['sources'];
    ksort($actualSources);
    ksort($expectedSources);

    $sourceDiff = [];
    foreach (array_unique(array_merge(array_keys($expectedSources), array_keys($actualSources))) as $source) {
        $expected = $expectedSources[$source] ?? 0;
        $actual = $actualSources[$source] ?? 0;
        if ($expected !== $actual) {
            $sourceDiff[] = sprintf('    %s: expected %d, got %d', $source, $expected, $actual);
        }
    }

    $actual = [
        'errors' => (int) $report['totals']['errors'],
        'warnings' => (int) $report['totals']['warnings'],
    ];

    $expectedExitCode = ($case['errors'] > 0 || $case['warnings'] > 0) ? 1 : 0;
    $label = $case['standard'] . ' :: ' . $fixture;

    if ($sourceDiff !== []
        || $actual['errors'] !== $case['errors']
        || $actual['warnings'] !== $case['warnings']
        || $exitCode !== $expectedExitCode
    ) {
        $hasFailure = true;
        fwrite(
            STDERR,
            sprintf(
                "Fixture mismatch for %s. Expected %d errors / %d warnings with exit code %d, got %d errors / %d warnings with exit code %d.\n",
                $label,
                $case['errors'],
                $case['warnings'],
                $expectedExitCode,
                $actual['errors'],
                $actual['warnings'],
                $exitCode
            )
        );

        if ($sourceDiff !== []) {
            fwrite(STDERR, "  Per-sniff differences:\n" . implode("\n", $sourceDiff) . "\n");
        }

        if ($verbose && $messages !== []) {
            fwrite(STDERR, "  Reported messages:\n" . implode("\n", $messages) . "\n");
        }

        continue;
    }

    if ($verbose) {
        fwrite(
            STDOUT,
            sprintf(
                "[ok] %s => %d errors / %d warnings across %d sniff sources\n",
                $label,
                $actual['errors'],
                $actual['warnings'],
                count($actualSources)
            )
        );
    }
}

if ($hasFailure) {
    exit(1);
}

fwrite(STDOUT, sprintf("All %d fixture expectations passed.\n", count($cases)));
