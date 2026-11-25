<?php
/**
 * Unit test for FlagNamingSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\Arguments;

use CLIStandards\Tests\FixtureSniffTestCase;

class FlagNamingUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'arguments-fail.php' => [
                'errors'   => [24 => 1, 45 => 1],
            ],
            'comprehensive-fail.php' => [
                'errors'   => [23 => 1],
            ],
            'registration-fail.php' => [
                'errors'   => [61 => 1],
            ],
        ];
    }
}
