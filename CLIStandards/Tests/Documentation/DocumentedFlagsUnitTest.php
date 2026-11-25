<?php
/**
 * Unit test for DocumentedFlagsSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\Documentation;

use CLIStandards\Tests\FixtureSniffTestCase;

class DocumentedFlagsUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'arguments-fail.php' => [
                'warnings' => [22 => 1, 24 => 1, 43 => 1, 45 => 1],
            ],
            'classification-fail.php' => [
                'warnings' => [25 => 1, 47 => 1, 69 => 1, 91 => 1],
            ],
            'confirm-fail.php' => [
                'warnings' => [25 => 2, 49 => 2, 51 => 1],
            ],
            'documentation-fail.php' => [
                'warnings' => [32 => 1],
            ],
            'documented-flags-fail.php' => [
                'warnings' => [26 => 1, 29 => 1, 32 => 1, 54 => 1, 59 => 1, 85 => 1, 87 => 1, 109 => 1, 118 => 1, 119 => 1],
            ],
            'error-handling-fail.php' => [
                'warnings' => [27 => 1, 38 => 1, 61 => 1],
            ],
            'names-fail.php' => [
                'warnings' => [38 => 1],
            ],
            'registration-fail.php' => [
                'warnings' => [59 => 1, 61 => 1],
            ],
            'safety-fail.php' => [
                'warnings' => [22 => 1],
            ],
            'scalability-fail.php' => [
                'warnings' => [22 => 1, 42 => 1],
            ],
        ];
    }
}
