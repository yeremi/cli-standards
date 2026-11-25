<?php
/**
 * Unit test for DocblockExamplesSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\Documentation;

use CLIStandards\Tests\FixtureSniffTestCase;

class DocblockExamplesUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'comprehensive-fail.php' => [
                'errors'   => [18 => 1, 49 => 1],
            ],
            'documentation-fail.php' => [
                'errors'   => [32 => 1, 45 => 1],
            ],
            'registration-fail.php' => [
                'errors'   => [23 => 1],
            ],
        ];
    }
}
