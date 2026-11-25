<?php
/**
 * Unit test for CommandRegistrationSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\CommandStructure;

use CLIStandards\Tests\FixtureSniffTestCase;

class CommandRegistrationUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'command-structure-fail.php' => [
                'errors'   => [7 => 1, 10 => 1, 13 => 1],
            ],
            'comprehensive-fail.php' => [
                'errors'   => [9 => 1],
            ],
        ];
    }
}
