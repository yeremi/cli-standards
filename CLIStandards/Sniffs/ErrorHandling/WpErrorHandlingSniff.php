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

use CLIStandards\Helpers\Names;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class WpErrorHandlingSniff implements Sniff
{
    /**
     * Functions that only return WP_Error when their $wp_error argument is
     * true: name => zero-based position of that argument.
     *
     * @var array<string, int>
     */
    private const CONDITIONAL_WP_ERROR = [
        'wp_insert_post'       => 1,
        'wp_update_post'       => 1,
        'wp_insert_attachment' => 3,
    ];

    /**
     * Whether the current file is a WP-CLI context.
     *
     * @var bool|null
     */
    private $cliContextCache = [];

    /**
     * WordPress functions documented to return WP_Error on failure.
     *
     * Functions that never return WP_Error are deliberately absent, e.g.
     * wp_delete_post() (WP_Post|false|null), wp_delete_user() (bool),
     * wp_create_category() (int), wp_upload_bits() and wp_handle_upload()
     * (arrays with an "error" key).
     *
     * @var array<string>
     */
    private $wpErrorFunctions = [
        'wp_insert_post',
        'wp_update_post',
        'wp_insert_term',
        'wp_update_term',
        'wp_delete_term',
        'wp_insert_user',
        'wp_update_user',
        'wp_remote_get',
        'wp_remote_post',
        'wp_remote_request',
        'wp_insert_attachment',
        'wp_set_object_terms',
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
        // Plain, fully qualified (\wp_insert_post) and namespaced names.
        return array_values(Names::nameTokens());
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
        $functionName = strtolower((string) Names::lastSegment($tokens[$stackPtr]));

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

        // Some functions only return WP_Error when asked to ($wp_error = true).
        $condition = '';
        if (isset(self::CONDITIONAL_WP_ERROR[$functionName])) {
            if (!$this->requestsWpError($phpcsFile, $next, self::CONDITIONAL_WP_ERROR[$functionName])) {
                return;
            }
            $condition = ' (called with $wp_error = true)';
        }

        // Find the variable that captures the return value.
        // Pattern: $result = wp_insert_post( ... )
        $assignmentVar = $this->findAssignmentVariable($phpcsFile, $stackPtr);

        if ($assignmentVar === false) {
            // Return value not captured at all — flag it.
            $phpcsFile->addWarning(
                'Return value of %s()%s is not captured. This function can return WP_Error — check with is_wp_error() before proceeding.',
                $stackPtr,
                'UncapturedWpError',
                [$functionName, $condition]
            );
            return;
        }

        // Search forward for is_wp_error( $variable ).
        $varName  = $tokens[$assignmentVar]['content'];
        $endLimit = min($stackPtr + $this->searchWindow, $phpcsFile->numTokens - 1);

        if (!$this->hasWpErrorCheck($phpcsFile, $stackPtr, $endLimit, $varName)) {
            $phpcsFile->addWarning(
                '%s()%s returns WP_Error on failure, but %s is not checked with is_wp_error() within the next %d lines. Unchecked WP_Error objects cause silent failures in CLI commands.',
                $stackPtr,
                'UncheckedWpError',
                [$functionName, $condition, $varName, 20]
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

        // Look backward past whitespace, and past a namespace prefix split
        // into T_STRING/T_NS_SEPARATOR tokens by PHPCS 3, for '='.
        $equals = $phpcsFile->findPrevious([T_WHITESPACE, T_NS_SEPARATOR, T_STRING], ($stackPtr - 1), max(0, $stackPtr - 10), true);
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
            if (Names::lastSegment($tokens[$i]) !== 'is_wp_error') {
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
            if (!Names::isWpCliClass($tokens[$i])) {
                continue;
            }

            $next = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);
            if ($next !== false && $tokens[$next]['code'] === T_DOUBLE_COLON) {
                return true;
            }
        }

        return false;
    }
    /**
     * Whether a call passes a $wp_error argument that may be true.
     *
     * The argument counts when it is present at the given position (or as
     * the named argument "wp_error:") and is not the literal false.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $open      Position of the call's opening parenthesis.
     * @param int  $position  Zero-based position of the $wp_error argument.
     *
     * @return bool
     */
    private function requestsWpError(File $phpcsFile, int $open, int $position): bool
    {
        $tokens = $phpcsFile->getTokens();
        if (!isset($tokens[$open]['parenthesis_closer'])) {
            return false;
        }

        $close    = $tokens[$open]['parenthesis_closer'];
        $index    = 0;
        $argStart = $open + 1;

        for ($i = $argStart; $i <= $close; $i++) {
            $code = $tokens[$i]['code'];

            if ($i < $close && $code === T_OPEN_PARENTHESIS && isset($tokens[$i]['parenthesis_closer'])) {
                $i = $tokens[$i]['parenthesis_closer'];
                continue;
            }

            if ($i < $close && isset($tokens[$i]['bracket_closer'])
                && in_array($code, [T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET, T_OPEN_CURLY_BRACKET], true)
            ) {
                $i = $tokens[$i]['bracket_closer'];
                continue;
            }

            if ($i !== $close && $code !== T_COMMA) {
                continue;
            }

            $first = $phpcsFile->findNext(T_WHITESPACE, $argStart, $i, true);
            if ($first !== false) {
                $valueStart = $first;
                $isNamed    = false;

                if ($tokens[$first]['code'] === T_PARAM_NAME) {
                    $isNamed    = true;
                    $colon      = $phpcsFile->findNext(T_WHITESPACE, ($first + 1), $i, true);
                    $valueStart = ($colon !== false) ? $phpcsFile->findNext(T_WHITESPACE, ($colon + 1), $i, true) : false;
                }

                $matches = $isNamed
                    ? (strtolower($tokens[$first]['content']) === 'wp_error')
                    : ($index === $position);

                if ($matches) {
                    $last = $phpcsFile->findPrevious(T_WHITESPACE, ($i - 1), $argStart - 1, true);

                    return !($valueStart !== false && $valueStart === $last && $tokens[$valueStart]['code'] === T_FALSE);
                }
            }

            $index++;
            $argStart = $i + 1;
        }

        return false;
    }
}
