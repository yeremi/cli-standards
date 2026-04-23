<?php
/**
 * FormatSupportSniff
 *
 * @package CLIStandards
 *
 * Detects WP-CLI listing commands that don't support the --format flag.
 *
 * Listing commands (methods named list, get, search, index, show, status)
 * should accept --format via WP_CLI\Utils\format_items() to enable
 * machine-parseable output for scripting and pipelines.
 *
 * Rule:    Listing commands must support --format via format_items()
 * Level:   SHOULD — violation reported as warning
 * Fix:     Call WP_CLI\Utils\format_items( $assoc_args['format'], $items, $fields )
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Output;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class FormatSupportSniff implements Sniff
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
        return [T_FUNCTION];
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

        $nameToken = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
        if ($nameToken === false || $tokens[$nameToken]['code'] !== T_STRING) {
            return;
        }

        $functionName = strtolower($tokens[$nameToken]['content']);

        // Check if this is a listing-type command.
        $isListing = false;
        foreach ($this->listingKeywords as $keyword) {
            if ($functionName === $keyword || strpos($functionName, $keyword . '_') === 0
                || strpos($functionName, '_' . $keyword) !== false
            ) {
                $isListing = true;
                break;
            }
        }

        if (!$isListing) {
            return;
        }

        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        // Only check CLI command handlers.
        if (!$this->isCommandHandler($phpcsFile, $scopeStart, $scopeEnd)) {
            return;
        }

        // Search for format_items() call in the function body.
        if (!$this->hasFormatItems($phpcsFile, $scopeStart, $scopeEnd)) {
            $phpcsFile->addWarning(
                'Listing command "%s" should support --format via WP_CLI\Utils\format_items(). This enables json, csv, yaml, and table output for scripting.',
                $nameToken,
                'MissingFormatSupport',
                [$tokens[$nameToken]['content']]
            );
        }
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
            if ($tokens[$i]['code'] === T_STRING && $tokens[$i]['content'] === 'format_items') {
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

    /**
     * Determine if a function body contains WP-CLI indicators.
     *
     * @param File $phpcsFile  The file being scanned.
     * @param int  $scopeStart Start of function body.
     * @param int  $scopeEnd   End of function body.
     *
     * @return bool
     */
    private function isCommandHandler(File $phpcsFile, int $scopeStart, int $scopeEnd): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $scopeStart; $i < $scopeEnd; $i++) {
            if ($tokens[$i]['code'] === T_STRING && $tokens[$i]['content'] === 'WP_CLI') {
                $next = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);
                if ($next !== false && $tokens[$next]['code'] === T_DOUBLE_COLON) {
                    return true;
                }
            }
        }

        return false;
    }
}
