<?php
/**
 * Generate docs/sniffs.md from CLIStandards/Docs/<Category>/<Sniff>Standard.xml.
 *
 * Usage:
 *   php scripts/generate-docs.php          Write docs/sniffs.md.
 *   php scripts/generate-docs.php --check  Fail when a sniff has no doc, a doc
 *                                          is invalid, or docs/sniffs.md is stale.
 */

$root   = dirname(__DIR__);
$check  = in_array('--check', $argv, true);
$target = $root . '/docs/sniffs.md';
$phpcs  = $root . '/vendor/bin/phpcs';

if (!is_file($phpcs)) {
    fwrite(STDERR, "PHPCS binary not found at {$phpcs}. Run composer install first.\n");
    exit(1);
}

// Every sniff needs a doc, and every doc must be well-formed XML.
$errors = [];
foreach (glob($root . '/CLIStandards/Sniffs/*/*Sniff.php') as $sniff) {
    $category = basename(dirname($sniff));
    $name     = basename($sniff, 'Sniff.php');
    $doc      = "{$root}/CLIStandards/Docs/{$category}/{$name}Standard.xml";

    if (!is_file($doc)) {
        $errors[] = "Missing doc for {$category}.{$name}: CLIStandards/Docs/{$category}/{$name}Standard.xml";
        continue;
    }

    $previous = libxml_use_internal_errors(true);
    $xml      = simplexml_load_file($doc);
    libxml_use_internal_errors($previous);

    if ($xml === false || $xml->getName() !== 'documentation' || !isset($xml->standard)) {
        $errors[] = "Invalid doc for {$category}.{$name}: expected <documentation> with a <standard>.";
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpcs) . ' --standard=CLIStandards --generator=Markdown';
exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "PHPCS documentation generator failed:\n" . implode("\n", $output) . "\n");
    exit(1);
}

// Drop the "Documentation generated on <date>" footer so the file is stable.
$output = array_filter($output, static function (string $line): bool {
    return strpos($line, 'Documentation generated on') !== 0;
});
$markdown = rtrim(implode("\n", $output)) . "\n";

if ($check) {
    $current = is_file($target) ? (string) file_get_contents($target) : '';
    if ($current !== $markdown) {
        fwrite(STDERR, "docs/sniffs.md is out of date. Run: composer docs\n");
        exit(1);
    }

    echo "Sniff docs are complete and docs/sniffs.md is up to date.\n";
    exit(0);
}

if (!is_dir(dirname($target))) {
    mkdir(dirname($target), 0777, true);
}

file_put_contents($target, $markdown);
echo "Wrote docs/sniffs.md.\n";
