<?php

declare(strict_types=1);

/*
 * Ruleset end-to-end checks.
 *
 * Per-sniff, per-line expectations live in the PHPUnit tests
 * (CLIStandards/Tests, `composer test:unit`). This script covers what they
 * cannot: the full CLIStandards and CLIStandardsMust rulesets (severities,
 * exclusions), rulesets with custom properties (tests/Fixtures/*.xml), and
 * phpcbf with every sniff enabled.
 *
 * Each case runs PHPCS with one standard (or ruleset file) against one fixture
 * and compares the reported messages per sniff source code
 * (e.g. CLIStandards.Documentation.DocblockOptions.MissingDocblock).
 * Any source not listed in 'sources' is expected to report nothing.
 * Totals are derived from the PHPCS report and checked as well.
 * A case with 'fixed' also runs phpcbf and compares its output with that file.
 */

$verbose = in_array('--verbose', $argv, true);

$root = dirname(__DIR__);
$phpcs = $root . '/vendor/bin/phpcs';
$phpcbf = $root . '/vendor/bin/phpcbf';

if (!is_file($phpcs)) {
    fwrite(STDERR, "PHPCS binary not found at {$phpcs}. Run composer install first.\n");
    exit(1);
}

$cases = [
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/Comprehensive/comprehensive-fail.php',
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
        'fixture' => 'tests/Fixtures/CLILinter/clilinter-fail.php',
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
        'standard' => 'tests/Fixtures/Documentation/ignore-methods.xml',
        'fixture' => 'tests/Fixtures/Documentation/ignore-methods.php',
        'errors' => 1,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Documentation.DocblockOptions.MissingDocblock' => 1,
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
        'standard' => 'tests/Fixtures/Classes/ignore-classes.xml',
        'fixture' => 'tests/Fixtures/Classes/ignore-classes.php',
        'errors' => 1,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Documentation.DocblockOptions.MissingDocblock' => 1,
        ],
    ],
    [
        'standard' => 'CLIStandardsMust',
        'fixture' => 'tests/Fixtures/DocumentedFlags/documented-flags-fail.php',
        'errors' => 0,
        'warnings' => 0,
        'sources' => [],
    ],
    [
        'standard' => 'CLIStandardsMust',
        'fixture' => 'tests/Fixtures/Comprehensive/comprehensive-fail.php',
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
    [
        'standard' => 'CLIStandardsMust',
        'fixture' => 'tests/Fixtures/CLILinter/clilinter-fail.php',
        'errors' => 0,
        'warnings' => 0,
        'sources' => [],
    ],
    [
        'standard' => 'CLIStandardsMust',
        'fixture' => 'tests/Fixtures/Documentation/documentation-fail.php',
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
        'fixture' => 'tests/Fixtures/Classification/classification-fail.php',
        'errors' => 4,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Safety.ConfirmOnDelete.MissingConfirm' => 4,
        ],
    ],
    [
        'standard' => 'CLIStandardsMust',
        'fixture' => 'tests/Fixtures/Registration/registration-fail.php',
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
        'fixture' => 'tests/Fixtures/ErrorHandling/error-handling-fail.php',
        'errors' => 1,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.ErrorHandling.ErrorSeverity.DirectExit' => 1,
        ],
    ],
    [
        'standard' => 'CLIStandardsMust',
        'fixture' => 'tests/Fixtures/Scalability/scalability-fail.php',
        'errors' => 0,
        'warnings' => 0,
        'sources' => [],
    ],
    [
        'standard' => 'CLIStandardsMust',
        'fixture' => 'tests/Fixtures/Safety/safety-fail.php',
        'errors' => 3,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Safety.ConfirmOnDelete.MissingConfirm' => 3,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/Autofix/autofix-output.php',
        'fixed' => 'tests/Fixtures/Autofix/autofix-output.php.fixed',
        'errors' => 8,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Output.OutputMethod.RawOutput' => 8,
        ],
    ],
    [
        'standard' => 'CLIStandards',
        'fixture' => 'tests/Fixtures/Autofix/autofix-confirm.php',
        'fixed' => 'tests/Fixtures/Autofix/autofix-confirm.php.fixed',
        'errors' => 3,
        'warnings' => 0,
        'sources' => [
            'CLIStandards.Safety.ConfirmPassthrough.MissingAssocArgs' => 3,
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
        . ' -q --standard=' . escapeshellarg($standard) . ' --report=json '
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

    // With reports, PHPCS 3 exits with 1 (or 2 when some are fixable);
    // PHPCS 4 with 1 (fixable), 2 (not fixable) or 3 (both).
    $expectedExitCode = ($case['errors'] > 0 || $case['warnings'] > 0) ? 1 : 0;
    $label = $case['standard'] . ' :: ' . $fixture;

    if ($sourceDiff !== []
        || $actual['errors'] !== $case['errors']
        || $actual['warnings'] !== $case['warnings']
        || !($exitCode === $expectedExitCode || ($expectedExitCode === 1 && in_array($exitCode, [2, 3], true)))
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

    // 'fixed' => path of the file phpcbf must turn the fixture into.
    if (isset($case['fixed'])) {
        $fixCommand = escapeshellarg($phpcbf)
            . ' --standard=' . escapeshellarg($standard)
            . ' --stdin-path=' . escapeshellarg($fixturePath)
            . ' - < ' . escapeshellarg($fixturePath) . ' 2>&1';

        $fixed = [];
        exec($fixCommand, $fixed);
        $fixedContent    = implode("\n", $fixed) . "\n";
        $expectedContent = (string) file_get_contents($root . '/' . $case['fixed']);

        if ($fixedContent !== $expectedContent) {
            $hasFailure = true;
            fwrite(STDERR, "Autofix mismatch for {$label}: phpcbf output differs from {$case['fixed']}.\n");
            if ($verbose) {
                fwrite(STDERR, "  phpcbf output:\n" . $fixedContent);
            }
            continue;
        }
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
