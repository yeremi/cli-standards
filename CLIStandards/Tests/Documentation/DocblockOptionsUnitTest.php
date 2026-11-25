<?php
/**
 * Unit test for DocblockOptionsSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\Documentation;

use CLIStandards\Tests\FixtureSniffTestCase;

class DocblockOptionsUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'classes-fail.php' => [
                'errors'   => [11 => 1, 18 => 1, 25 => 1, 32 => 1],
            ],
            'comprehensive-fail.php' => [
                'errors'   => [18 => 1],
            ],
            'documentation-fail.php' => [
                'errors'   => [9 => 1, 14 => 1, 19 => 1, 45 => 1, 82 => 1, 88 => 1],
            ],
            'output-method-fail.php' => [
                'errors'   => [10 => 1],
            ],
            'registration-fail.php' => [
                'errors'   => [7 => 1],
            ],
        ];
    }
}
