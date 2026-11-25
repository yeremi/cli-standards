<?php
/**
 * Unit test for AvailableFieldsSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\Output;

use CLIStandards\Tests\FixtureSniffTestCase;

class AvailableFieldsUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'clilinter-fail.php' => [
                'warnings' => [22 => 1],
            ],
            'documentation-fail.php' => [
                'warnings' => [67 => 1],
            ],
        ];
    }
}
