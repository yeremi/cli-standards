<?php
/**
 * Unit test for BatchSizeFlagSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\Scalability;

use CLIStandards\Tests\FixtureSniffTestCase;

class BatchSizeFlagUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'clilinter-fail.php' => [
                'warnings' => [52 => 1],
            ],
        ];
    }
}
