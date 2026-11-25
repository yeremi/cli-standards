<?php
/**
 * Unit test for StdoutStderrSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\Output;

use CLIStandards\Tests\FixtureSniffTestCase;

class StdoutStderrUnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
        return [
            'clilinter-fail.php' => [
                'warnings' => [29 => 1],
            ],
        ];
    }
}
