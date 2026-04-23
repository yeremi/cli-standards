<?php
/**
 * ConfirmPassthroughSniff
 *
 * @package CLIStandards
 *
 * Detects WP_CLI::confirm() calls that don't pass $assoc_args as the
 * second argument. Without this, the --yes flag cannot bypass the prompt
 * in automated/CI environments.
 *
 * Rule:    WP_CLI::confirm() must receive $assoc_args to allow --yes bypass in CI/CD
 * Level:   MUST — violation blocks CI
 * Fix:     Change WP_CLI::confirm($msg) to WP_CLI::confirm($msg, $assoc_args)
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Safety;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class ConfirmPassthroughSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return array<int>
     */
    public function register(): array
    {
        return [T_STRING];
    }

    /**
     * Processes this sniff when a string token is encountered.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  The position of the current token.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // Only interested in 'confirm'.
        if ($tokens[$stackPtr]['content'] !== 'confirm') {
            return;
        }

        // Verify this is WP_CLI::confirm().
        if (!$this->isWpCliMethod($phpcsFile, $stackPtr)) {
            return;
        }

        // Find the opening parenthesis.
        $openParen = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
        if ($openParen === false || $tokens[$openParen]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        // Find the closing parenthesis.
        if (!isset($tokens[$openParen]['parenthesis_closer'])) {
            return;
        }

        $closeParen = $tokens[$openParen]['parenthesis_closer'];

        // Count commas between parentheses to determine argument count.
        $commaCount  = 0;
        $nestingLevel = 0;

        for ($i = ($openParen + 1); $i < $closeParen; $i++) {
            if ($tokens[$i]['code'] === T_OPEN_PARENTHESIS) {
                $nestingLevel++;
            } elseif ($tokens[$i]['code'] === T_CLOSE_PARENTHESIS) {
                $nestingLevel--;
            } elseif ($tokens[$i]['code'] === T_COMMA && $nestingLevel === 0) {
                $commaCount++;
            }
        }

        // WP_CLI::confirm( message, $assoc_args ) requires 2 args (1 comma).
        if ($commaCount === 0) {
            $phpcsFile->addError(
                'WP_CLI::confirm() must receive $assoc_args as the second argument to enable --yes bypass in automation. Use: WP_CLI::confirm( $message, $assoc_args )',
                $stackPtr,
                'MissingAssocArgs'
            );
        }
    }

    /**
     * Check if the current token is a WP_CLI:: method call.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Position of the method name token.
     *
     * @return bool
     */
    private function isWpCliMethod(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        // Look for :: before the method name.
        $doubleColon = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);
        if ($doubleColon === false || $tokens[$doubleColon]['code'] !== T_DOUBLE_COLON) {
            return false;
        }

        // Look for WP_CLI before the ::.
        $className = $phpcsFile->findPrevious(T_WHITESPACE, ($doubleColon - 1), null, true);
        if ($className === false || $tokens[$className]['content'] !== 'WP_CLI') {
            return false;
        }

        return true;
    }
}
