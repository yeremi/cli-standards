<?php
/**
 * Regenerate the expectations of the sniff unit tests.
 *
 * Writes CLIStandards/Tests/<Category>/<Sniff>UnitTest.php with the reports
 * each sniff gives today, per fixture and line. It records behavior, it does
 * not judge it: review the git diff and check every changed line against the
 * fixture comments (❌ / ⚠ / ✅) before committing.
 *
 * Usage: composer test:update
 */

$root = dirname(__DIR__);

$fixtures = [];
foreach (glob($root . '/tests/Fixtures/*/*.php') as $file) {
    if (!is_file(substr($file, 0, -4) . '.xml')) {
        $fixtures[] = $file;
    }
}
sort($fixtures, SORT_NATURAL);

foreach (glob($root . '/CLIStandards/Sniffs/*/*Sniff.php') as $sniffFile) {
    $category = basename(dirname($sniffFile));
    $sniff    = basename($sniffFile, 'Sniff.php');
    $code     = "CLIStandards.{$category}.{$sniff}";

    $map = [];
    foreach ($fixtures as $fixture) {
        $cmd  = escapeshellarg($root . '/vendor/bin/phpcs') . ' -q --standard=CLIStandards --sniffs=' . $code . ' --report=json ' . escapeshellarg($fixture);
        $json = json_decode(shell_exec($cmd), true);
        foreach ($json['files'] as $file) {
            foreach ($file['messages'] as $message) {
                $kind = $message['type'] === 'ERROR' ? 'errors' : 'warnings';
                $map[basename($fixture)][$kind][$message['line']] = ($map[basename($fixture)][$kind][$message['line']] ?? 0) + 1;
            }
        }
    }

    ksort($map, SORT_NATURAL);
    $body = '';
    foreach ($map as $name => $kinds) {
        $body .= "            '{$name}' => [\n";
        foreach (['errors', 'warnings'] as $kind) {
            if (empty($kinds[$kind])) {
                continue;
            }
            ksort($kinds[$kind]);
            $pairs = [];
            foreach ($kinds[$kind] as $line => $count) {
                $pairs[] = "{$line} => {$count}";
            }
            $pad   = str_pad("'{$kind}'", 10);
            $body .= "                {$pad} => [" . implode(', ', $pairs) . "],\n";
        }
        $body .= "            ],\n";
    }

    $return = ($body === '') ? "        return [];\n" : "        return [\n{$body}        ];\n";

    $php = <<<PHP
<?php
/**
 * Unit test for {$sniff}Sniff: expected reports per line in the shared
 * fixtures (tests/Fixtures/). Fixtures not listed expect no reports.
 * Regenerate with `composer test:update` and review the diff.
 *
 * @package CLIStandards
 */

namespace CLIStandards\\Tests\\{$category};

use CLIStandards\\Tests\\FixtureSniffTestCase;

class {$sniff}UnitTest extends FixtureSniffTestCase
{
    /**
     * @return array<string, array{errors?: array<int, int>, warnings?: array<int, int>}>
     */
    protected function getExpectations(): array
    {
{$return}    }
}

PHP;

    @mkdir("{$root}/CLIStandards/Tests/{$category}", 0777, true);
    file_put_contents("{$root}/CLIStandards/Tests/{$category}/{$sniff}UnitTest.php", $php);
    echo "{$code}: " . count($map) . " fixture(s) with reports\n";
}
