<?php
/**
 * ProgressBarSniff
 *
 * @package CLIStandards
 *
 * Detects bulk WP-CLI commands that process items in a loop without
 * using WP_CLI\Utils\make_progress_bar() for user feedback.
 *
 * For commands that iterate over collections (foreach with WP_CLI calls),
 * progress bars provide essential feedback on long-running operations.
 *
 * Rule:    Bulk loops with WP-CLI calls should display a progress bar
 * Level:   SHOULD — violation reported as warning
 * Fix:     Use $progress = WP_CLI\Utils\make_progress_bar('Processing', $total) before loop
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Scalability;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class ProgressBarSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return array<int>
     */
    public function register(): array
    {
        return [T_FOREACH, T_WHILE, T_FOR];
    }

    /**
     * Processes this sniff when a loop token is encountered.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  The position of the current token.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $loopStart = $tokens[$stackPtr]['scope_opener'];
        $loopEnd   = $tokens[$stackPtr]['scope_closer'];

        // Only care about loops that contain WP_CLI calls (i.e., CLI command context).
        if (!$this->hasWpCliCall($phpcsFile, $loopStart, $loopEnd)) {
            return;
        }

        // Check if make_progress_bar() is called before or inside the loop.
        // Look backward up to 30 tokens for the progress bar creation.
        $searchStart = max(0, $stackPtr - 50);
        $hasProgressBar = false;

        for ($i = $searchStart; $i < $loopEnd; $i++) {
            if ($tokens[$i]['code'] === T_STRING && $tokens[$i]['content'] === 'make_progress_bar') {
                $hasProgressBar = true;
                break;
            }
        }

        if (!$hasProgressBar) {
            $phpcsFile->addWarning(
                'Bulk loop contains WP-CLI calls but does not use WP_CLI\Utils\make_progress_bar() for user feedback. Progress indicators are essential for long-running CLI operations.',
                $stackPtr,
                'MissingProgressBar'
            );
        }
    }

    /**
     * Check if a scope contains WP_CLI:: method calls.
     */
    private function hasWpCliCall(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $start; $i < $end; $i++) {
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
