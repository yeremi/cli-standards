<?php
/**
 * Unit test for ConfirmOnDeleteSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\Safety;

use CLIStandards\Tests\FixtureSniffTestCase;

class ConfirmOnDeleteUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'classification-fail.php' => [
                'errors'   => [25 => 1, 47 => 1, 69 => 1, 91 => 1],
            ],
            'comprehensive-fail.php' => [
                'errors'   => [18 => 1, 49 => 1],
            ],
            'delegation-fail.php' => [
                'errors'   => [25 => 1, 44 => 1, 63 => 1],
            ],
            'registration-fail.php' => [
                'errors'   => [41 => 1],
            ],
            'safety-fail.php' => [
                'errors'   => [22 => 1, 41 => 1, 59 => 1],
            ],
        ];
    }
}
