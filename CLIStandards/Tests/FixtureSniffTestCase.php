<?php
/**
 * FixtureSniffTestCase
 *
 * @package CLIStandards
 *
 * Base class for the sniff unit tests: runs one sniff over every shared
 * fixture in tests/Fixtures/ and compares errors and warnings per line.
 *
 * Running each sniff over all fixtures, not only its own, also catches a
 * sniff reporting in code meant for another one. Fixtures that need a custom
 * ruleset (a sibling .xml with properties) are checked end to end by
 * scripts/verify-fixtures.php instead.
 *
 * A fixture whose fixable reports change it needs a `<fixture>.fixed` file
 * with the expected phpcbf result. Each fixture holds fixable reports of one
 * sniff only, so a single .fixed file fits every sniff.
 * @since   1.2.0
 */

namespace CLIStandards\Tests;

abstract class FixtureSniffTestCase extends PhpcsSniffTestCase
{
    /**
     * Expected reports per fixture basename:
     * [ 'safety-fail.php' => [ 'errors' => [ line => count ], 'warnings' => [ line => count ] ] ].
     * Fixtures not listed expect no reports.
     *
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    abstract protected function getExpectations(): array;

    /**
     * Every shared fixture without a custom ruleset.
     *
     * @return array<string>
     */
    public static function getFixtureFiles(): array
    {
        $files = [];
        foreach (glob(dirname(__DIR__, 2) . '/tests/Fixtures/*/*.php') as $file) {
            if (!is_file(substr($file, 0, -4) . '.xml')) {
                $files[] = $file;
            }
        }

        sort($files, SORT_NATURAL);

        return $files;
    }

    /**
     * Test files to check: the shared fixtures, not files next to the test.
     *
     * @param string $testFileBase Unused.
     *
     * @return array<string>
     */
    protected function getTestFiles($testFileBase)
    {
        return self::getFixtureFiles();
    }

    /**
     * Expected errors for a fixture: line => count.
     *
     * @param string $testFile Fixture basename.
     *
     * @return array<int, int>
     */
    protected function getErrorList($testFile = '')
    {
        return $this->getExpectations()[$testFile]['errors'] ?? [];
    }

    /**
     * Expected warnings for a fixture: line => count.
     *
     * @param string $testFile Fixture basename.
     *
     * @return array<int, int>
     */
    protected function getWarningList($testFile = '')
    {
        return $this->getExpectations()[$testFile]['warnings'] ?? [];
    }
}
