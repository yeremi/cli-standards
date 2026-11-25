<?php
/**
 * Unit test for OutputMethodSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\Output;

use CLIStandards\Tests\FixtureSniffTestCase;

class OutputMethodUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'autofix-output.php' => [
                'errors'   => [21 => 1, 22 => 1, 23 => 1, 24 => 1, 25 => 1, 28 => 1, 31 => 1, 32 => 1],
            ],
            'comprehensive-fail.php' => [
                'errors'   => [20 => 1],
            ],
            'output-method-fail.php' => [
                'errors'   => [14 => 1, 17 => 1, 21 => 1, 31 => 1],
            ],
        ];
    }
}
