<?php
/**
 * AvailableFieldsSniff
 *
 * @package CLIStandards
 *
 * Detects listing commands that lack a `## AVAILABLE FIELDS` section
 * in their docblock.
 *
 * For commands that output tabular data via format_items(), the
 * ## AVAILABLE FIELDS section documents which columns exist and
 * which are shown by default vs available via --fields.
 *
 * Rule:    Listing commands using format_items() must document fields in ## AVAILABLE FIELDS
 * Level:   SHOULD — violation reported as warning
 * Fix:     Add '## AVAILABLE FIELDS' section listing column names and defaults
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Output;

use CLIStandards\Helpers\AbstractCommandSniff;
use PHP_CodeSniffer\Files\File;

class AvailableFieldsSniff extends AbstractCommandSniff
{
    /**
     * Listing method name keywords.
     *
     * @var array<string>
     */
    private $listingKeywords = [
        'list',
        'get',
        'search',
        'index',
        'show',
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

        // Only check CLI command handlers that actually use format_items.
        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        if (!$this->isCommandHandler($phpcsFile, $stackPtr)
            || !$this->hasFormatItems($phpcsFile, $scopeStart, $scopeEnd)
        ) {
            return;
        }

        // Check for ## AVAILABLE FIELDS in docblock.
        $docblock = $this->findDocblock($phpcsFile, $stackPtr);
        if ($docblock === false) {
            return; // No docblock — other sniffs handle this.
        }

        $docContent = $this->getDocblockContent($phpcsFile, $docblock);

        if (!$this->docblockHasSection($docContent, 'AVAILABLE FIELDS')) {
            $phpcsFile->addWarning(
                'Listing command "%s" uses format_items() but lacks a "## AVAILABLE FIELDS" section in its docblock. Document which fields are available and which are shown by default.',
                $nameToken,
                'MissingAvailableFields',
                [$this->getCommandLabel($phpcsFile, $stackPtr)]
            );
        }
    }

    /**
     * Check if the function body calls format_items().
     */
    private function hasFormatItems(File $phpcsFile, int $scopeStart, int $scopeEnd): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $scopeStart; $i < $scopeEnd; $i++) {
            if ($tokens[$i]['code'] === T_STRING && $tokens[$i]['content'] === 'format_items') {
                return true;
            }
        }

        return false;
    }
}
