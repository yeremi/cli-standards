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

        // Listing command: a listing keyword is a whole word of the command name.
        if ($this->matchCommandKeyword($phpcsFile, $stackPtr, $this->listingKeywords) === null) {
            return;
        }

        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        // Only check CLI command handlers.
        if (!$this->isCommandHandler($phpcsFile, $stackPtr)) {
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
}
