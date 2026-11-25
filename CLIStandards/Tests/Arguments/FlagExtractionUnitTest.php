<?php
/**
 * Unit test for FlagExtractionSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\Arguments;

use CLIStandards\Tests\FixtureSniffTestCase;

class FlagExtractionUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'arguments-fail.php' => [
                'warnings' => [24 => 1, 45 => 1],
            ],
            'clilinter-fail.php' => [
                'warnings' => [24 => 1],
            ],
            'comprehensive-fail.php' => [
                'warnings' => [23 => 1],
            ],
            'documented-flags-fail.php' => [
                'warnings' => [32 => 2, 63 => 1, 64 => 1],
            ],
            'registration-fail.php' => [
                'warnings' => [61 => 1],
            ],
            'safety-fail.php' => [
                'warnings' => [61 => 1],
            ],
        ];
    }
}
