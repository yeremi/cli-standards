<?php
/**
 * Unit test for WpErrorHandlingSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\ErrorHandling;

use CLIStandards\Tests\FixtureSniffTestCase;

class WpErrorHandlingUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'comprehensive-fail.php' => [
                'warnings' => [32 => 1],
            ],
            'error-handling-fail.php' => [
                'warnings' => [29 => 1, 35 => 1, 39 => 1, 42 => 1],
            ],
            'names-fail.php' => [
                'warnings' => [41 => 1],
            ],
        ];
    }
}
