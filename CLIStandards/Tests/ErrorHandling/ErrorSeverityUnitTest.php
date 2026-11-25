<?php
/**
 * Unit test for ErrorSeveritySniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\ErrorHandling;

use CLIStandards\Tests\FixtureSniffTestCase;

class ErrorSeverityUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'comprehensive-fail.php' => [
                'errors'   => [36 => 1, 56 => 1],
            ],
            'error-handling-fail.php' => [
                'errors'   => [63 => 1],
            ],
            'names-fail.php' => [
                'errors'   => [45 => 1],
            ],
        ];
    }
}
