<?php
/**
 * Unit test for DryRunSupportSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\Safety;

use CLIStandards\Tests\FixtureSniffTestCase;

class DryRunSupportUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'classification-fail.php' => [
                'warnings' => [25 => 1, 47 => 1, 69 => 1, 91 => 1],
            ],
            'clilinter-fail.php' => [
                'warnings' => [52 => 1],
            ],
            'comprehensive-fail.php' => [
                'warnings' => [18 => 1, 49 => 1],
            ],
            'registration-fail.php' => [
                'warnings' => [41 => 1],
            ],
            'safety-fail.php' => [
                'warnings' => [22 => 1, 41 => 1, 59 => 1],
            ],
        ];
    }
}
