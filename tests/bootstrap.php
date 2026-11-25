<?php
/**
 * PHPUnit bootstrap for the sniff unit tests (CLIStandards/Tests).
 *
 * Works with PHP_CodeSniffer 3 and 4:
 * - PHPCS 3 names the base class AbstractSniffUnitTest and finds the
 *   standard and test directories through globals set by its own suite;
 * - PHPCS 4 names it AbstractSniffTestCase and derives the sniff file from
 *   the test file (CLIStandards/Tests/... -> CLIStandards/Sniffs/...).
 */

$root  = dirname(__DIR__);
$phpcs = $root . '/vendor/squizlabs/php_codesniffer';

if (!is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "Run composer install first.\n");
    exit(1);
}

require_once $root . '/vendor/autoload.php';
require_once $phpcs . '/tests/bootstrap.php';

if (class_exists('PHP_CodeSniffer\Tests\Standards\AbstractSniffTestCase')) {
    class_alias('PHP_CodeSniffer\Tests\Standards\AbstractSniffTestCase', 'CLIStandards\Tests\PhpcsSniffTestCase');
} else {
    class_alias('PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest', 'CLIStandards\Tests\PhpcsSniffTestCase');

    // PHPCS 3 reads these globals, normally set by its AllTests/AllSniffs suites.
    $GLOBALS['PHP_CODESNIFFER_SNIFF_CODES']      = [];
    $GLOBALS['PHP_CODESNIFFER_FIXABLE_CODES']    = [];
    $GLOBALS['PHP_CODESNIFFER_SNIFF_CASE_FILES'] = [];
    $GLOBALS['PHP_CODESNIFFER_STANDARD_DIRS']    = [];
    $GLOBALS['PHP_CODESNIFFER_TEST_DIRS']        = [];

    $standardDir = $root . '/CLIStandards';
    foreach (glob($standardDir . '/Tests/*/*UnitTest.php') as $file) {
        $class = 'CLIStandards\\Tests\\' . basename(dirname($file)) . '\\' . basename($file, '.php');

        $GLOBALS['PHP_CODESNIFFER_STANDARD_DIRS'][$class] = $standardDir;
        $GLOBALS['PHP_CODESNIFFER_TEST_DIRS'][$class]     = $standardDir . DIRECTORY_SEPARATOR . 'Tests' . DIRECTORY_SEPARATOR;
    }
}
