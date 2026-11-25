<?php
/**
 * FormatSupportSniff
 *
 * @package CLIStandards
 *
 * Detects WP-CLI listing commands that don't support the --format flag.
 *
 * Listing commands (a whole word of the command name is list, get, search,
 * index, show, status, info or report)
 * should accept --format via WP_CLI\Utils\format_items() to enable
 * machine-parseable output for scripting and pipelines.
 *
 * Rule:    Listing commands must support --format via format_items()
 * Level:   SHOULD — violation reported as warning
 * Fix:     Call WP_CLI\Utils\format_items( $assoc_args['format'], $items, $fields )
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Output;

use CLIStandards\Helpers\AbstractCommandSniff;
use CLIStandards\Helpers\Names;
use PHP_CodeSniffer\Files\File;

class FormatSupportSniff extends AbstractCommandSniff
{
    /**
     * Method names that indicate listing commands.
     *
     * @var array<string>
     */
    private $listingKeywords = [
        'list',
        'get',
        'search',
        'index',
        'show',
        'status',
        'info',
        'report',
    ];

    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return array<int>
     */
    public function register(): array
    {
        // Closures count only when passed directly to WP_CLI::add_command().
        return [T_FUNCTION, T_CLOSURE, T_FN];
    }

    /**
     * Processes this sniff when a function token is encountered.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  The position of the current token.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        $nameToken = $this->getReportPointer($phpcsFile, $stackPtr);

        // Listing command: a listing keyword is a whole word of the command name.
        if ($this->matchCommandKeyword($phpcsFile, $stackPtr, $this->listingKeywords) === null) {
            return;
        }

        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        // Only check CLI command handlers.
        if (!$this->isCommandHandler($phpcsFile, $stackPtr)) {
            return;
        }

        // A documented delegation to code PHPCS cannot follow (needs a reason).
        $delegation = $this->getDelegation($phpcsFile, $stackPtr, 'format-delegated');
        if ($delegation !== null && $delegation !== '') {
            return;
        }

        // Search for format_items() in the command and the same-class methods it calls.
        foreach ($this->getReachableBodies($phpcsFile, $stackPtr) as [$start, $end]) {
            if ($this->hasFormatItems($phpcsFile, $start, $end)) {
                return;
            }
        }

        $phpcsFile->addWarning(
            'Listing command "%s" should support --format via WP_CLI\Utils\format_items(). This enables json, csv, yaml, and table output for scripting.%s',
            $nameToken,
            'MissingFormatSupport',
            [
                $this->getCommandLabel($phpcsFile, $stackPtr),
                ($delegation === '') ? ' The "@cli-standards format-delegated" tag needs a reason.' : '',
            ]
        );
    }

    /**
     * Check if the function body contains a format_items() call.
     *
     * @param File $phpcsFile  The file being scanned.
     * @param int  $scopeStart Start of function body.
     * @param int  $scopeEnd   End of function body.
     *
     * @return bool
     */
    private function hasFormatItems(File $phpcsFile, int $scopeStart, int $scopeEnd): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $scopeStart; $i < $scopeEnd; $i++) {
            if (Names::lastSegment($tokens[$i]) === 'format_items') {
                return true;
            }

            // Also accept direct --format checking via $assoc_args['format'].
            if ($tokens[$i]['code'] === T_CONSTANT_ENCAPSED_STRING) {
                $val = trim($tokens[$i]['content'], "\"'");
                if ($val === 'format') {
                    return true;
                }
            }
        }

        return false;
    }
}
