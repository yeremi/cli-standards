<?php
/**
 * Unit test for ProgressBarSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\Scalability;

use CLIStandards\Tests\FixtureSniffTestCase;

class ProgressBarUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'clilinter-fail.php' => [
                'warnings' => [60 => 1],
            ],
            'names-fail.php' => [
                'warnings' => [63 => 1],
            ],
            'scalability-fail.php' => [
                'warnings' => [48 => 1, 53 => 1, 66 => 1],
            ],
        ];
    }
}
