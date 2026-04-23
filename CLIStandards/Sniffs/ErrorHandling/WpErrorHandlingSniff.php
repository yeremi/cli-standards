<?php
/**
 * WpErrorHandlingSniff
 *
 * @package CLIStandards
 *
 * Detects WordPress API function calls known to return WP_Error where
 * the return value is not checked with is_wp_error().
 *
 * This is the most heuristic-heavy sniff in the standard. It triggers
 * warnings (not errors) because false positives are expected for:
 * - Return values checked via instanceof
 * - Error handling delegated to a called method
 * - Intentionally discarded return values
 *
 * Rule:    Check return values of WP_Error-returning functions with is_wp_error()
 * Level:   SHOULD — violation reported as warning (heuristic; false positives expected)
 * Fix:     Add is_wp_error($result) check before using the return value
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\ErrorHandling;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class WpErrorHandlingSniff implements Sniff
{
    /**
     * Whether the current file is a WP-CLI context.
     *
     * @var bool|null
     */
    private $cliContextCache = [];

    /**
     * WordPress functions known to return WP_Error on failure.
     *
     * @var array<string>
     */
    private $wpErrorFunctions = [
        'wp_insert_post',
        'wp_update_post',
        'wp_delete_post',
        'wp_insert_term',
        'wp_update_term',
        'wp_delete_term',
        'wp_insert_user',
        'wp_update_user',
        'wp_delete_user',
        'wp_remote_get',
        'wp_remote_post',
        'wp_remote_request',
        'wp_upload_bits',
        'wp_handle_upload',
        'wp_insert_attachment',
        'wp_set_object_terms',
        'wp_create_category',
        'media_handle_upload',
        'media_handle_sideload',
        'wp_crop_image',
    ];

    /**
     * Number of tokens to search forward for an is_wp_error() check.
     *
     * @var int
     */
    private $searchWindow = 100;

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
     * @return void|int
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $filename = $phpcsFile->getFilename();
        if (!isset($this->cliContextCache[$filename])) {
            $this->cliContextCache[$filename] = $this->detectCliContext($phpcsFile);

            if (!$this->cliContextCache[$filename]) {
                return $phpcsFile->numTokens;
            }
        }

        $tokens = $phpcsFile->getTokens();
        $functionName = strtolower($tokens[$stackPtr]['content']);

        // Check if this is a WordPress function that returns WP_Error.
        if (!in_array($functionName, $this->wpErrorFunctions, true)) {
            return;
        }

        // Verify it's actually a function call (followed by open parenthesis).
        $next = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
        if ($next === false || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        // Make sure it's not a method call (->wp_insert_post or ::wp_insert_post).
        $prev = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);
        if ($prev !== false) {
            if ($tokens[$prev]['code'] === T_OBJECT_OPERATOR
                || $tokens[$prev]['code'] === T_DOUBLE_COLON
            ) {
                return;
            }
        }

        // Find the variable that captures the return value.
        // Pattern: $result = wp_insert_post( ... )
        $assignmentVar = $this->findAssignmentVariable($phpcsFile, $stackPtr);

        if ($assignmentVar === false) {
            // Return value not captured at all — flag it.
            $phpcsFile->addWarning(
                'Return value of %s() is not captured. This function can return WP_Error — check with is_wp_error() before proceeding.',
                $stackPtr,
                'UncapturedWpError',
                [$tokens[$stackPtr]['content']]
            );
            return;
        }

        // Search forward for is_wp_error( $variable ).
        $varName  = $tokens[$assignmentVar]['content'];
        $endLimit = min($stackPtr + $this->searchWindow, $phpcsFile->numTokens - 1);

        if (!$this->hasWpErrorCheck($phpcsFile, $stackPtr, $endLimit, $varName)) {
            $phpcsFile->addWarning(
                '%s() returns WP_Error on failure, but %s is not checked with is_wp_error() within the next %d lines. Unchecked WP_Error objects cause silent failures in CLI commands.',
                $stackPtr,
                'UncheckedWpError',
                [$tokens[$stackPtr]['content'], $varName, 20]
            );
        }
    }

    /**
     * Find the variable on the left side of an assignment.
     *
     * Looks for the pattern: $var = function_call()
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Position of the function name.
     *
     * @return int|false Position of the variable token, or false.
     */
    private function findAssignmentVariable(File $phpcsFile, int $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Look backward past whitespace for '='.
        $equals = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), max(0, $stackPtr - 10), true);
        if ($equals === false || $tokens[$equals]['code'] !== T_EQUAL) {
            return false;
        }

        // Look backward past whitespace for the variable.
        $variable = $phpcsFile->findPrevious(T_WHITESPACE, ($equals - 1), max(0, $equals - 5), true);
        if ($variable === false || $tokens[$variable]['code'] !== T_VARIABLE) {
            return false;
        }

        return $variable;
    }

    /**
     * Check if is_wp_error() is called on a specific variable within a range.
     *
     * @param File   $phpcsFile The file being scanned.
     * @param int    $start     Start position to search from.
     * @param int    $end       End position to search to.
     * @param string $varName   Variable name to look for (e.g., '$result').
     *
     * @return bool
     */
    private function hasWpErrorCheck(File $phpcsFile, int $start, int $end, string $varName): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $start; $i <= $end; $i++) {
            if ($tokens[$i]['code'] !== T_STRING) {
                continue;
            }

            if ($tokens[$i]['content'] !== 'is_wp_error') {
                continue;
            }

            // Found is_wp_error — check if it references our variable.
            $openParen = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);
            if ($openParen === false || $tokens[$openParen]['code'] !== T_OPEN_PARENTHESIS) {
                continue;
            }

            $argToken = $phpcsFile->findNext(T_WHITESPACE, ($openParen + 1), null, true);
            if ($argToken !== false
                && $tokens[$argToken]['code'] === T_VARIABLE
                && $tokens[$argToken]['content'] === $varName
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect whether the current file is a WP-CLI command context.
     *
     * @param File $phpcsFile The file being scanned.
     *
     * @return bool
     */
    private function detectCliContext(File $phpcsFile): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = 0; $i < $phpcsFile->numTokens; $i++) {
            if ($tokens[$i]['code'] !== T_STRING || $tokens[$i]['content'] !== 'WP_CLI') {
                continue;
            }

            $next = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);
            if ($next !== false && $tokens[$next]['code'] === T_DOUBLE_COLON) {
                return true;
            }
        }

        return false;
    }
}
