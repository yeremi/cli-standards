<?php
/**
 * Unit test for SinceAnnotationSniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\Tests\Documentation;

use CLIStandards\Tests\FixtureSniffTestCase;

class SinceAnnotationUnitTest extends FixtureSniffTestCase
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
            'comprehensive-fail.php' => [
                'warnings' => [18 => 1, 49 => 1],
            ],
            'documentation-fail.php' => [
                'warnings' => [32 => 1],
            ],
            'registration-fail.php' => [
                'warnings' => [59 => 1],
            ],
            'safety-fail.php' => [
                'warnings' => [22 => 1, 41 => 1, 59 => 1],
            ],
        ];
    }
}
