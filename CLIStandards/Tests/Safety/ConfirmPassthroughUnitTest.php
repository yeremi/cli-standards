<?php
/**
 * Unit test for ConfirmPassthroughSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\Safety;

use CLIStandards\Tests\FixtureSniffTestCase;

class ConfirmPassthroughUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'autofix-confirm.php' => [
                'errors'   => [24 => 1, 32 => 1, 38 => 1],
            ],
            'confirm-fail.php' => [
                'errors'   => [27 => 1, 52 => 1],
            ],
            'names-fail.php' => [
                'errors'   => [31 => 1],
            ],
        ];
    }
}
