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

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class AvailableFieldsSniff implements Sniff
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

        // Only check CLI command handlers that actually use format_items.
        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        if (!$this->hasFormatItems($phpcsFile, $scopeStart, $scopeEnd)) {
            return;
        }

        // Check for ## AVAILABLE FIELDS in docblock.
        $docblock = $this->findDocblock($phpcsFile, $stackPtr);
        if ($docblock === false) {
            return; // No docblock — other sniffs handle this.
        }

        $docContent = $this->getDocblockContent($phpcsFile, $docblock);

        if (stripos($docContent, '## AVAILABLE FIELDS') === false) {
            $phpcsFile->addWarning(
                'Listing command "%s" uses format_items() but lacks a "## AVAILABLE FIELDS" section in its docblock. Document which fields are available and which are shown by default.',
                $nameToken,
                'MissingAvailableFields',
                [$tokens[$nameToken]['content']]
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

    /**
     * Find the docblock preceding a function.
     */
    private function findDocblock(File $phpcsFile, int $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $skip = [T_WHITESPACE, T_STATIC, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_ABSTRACT, T_FINAL];
        $prev = $phpcsFile->findPrevious($skip, ($stackPtr - 1), null, true);

        if ($prev !== false && $tokens[$prev]['code'] === T_DOC_COMMENT_CLOSE_TAG) {
            return $tokens[$prev]['comment_opener'];
        }

        return false;
    }

    /**
     * Extract the full text content of a docblock.
     */
    private function getDocblockContent(File $phpcsFile, int $opener): string
    {
        $tokens  = $phpcsFile->getTokens();
        $closer  = $tokens[$opener]['comment_closer'];
        $content = '';

        for ($i = $opener; $i <= $closer; $i++) {
            $content .= $tokens[$i]['content'];
        }

        return $content;
    }
}
