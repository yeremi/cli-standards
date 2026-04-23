<?php
/**
 * FlagExtractionSniff
 *
 * @package CLIStandards
 *
 * Detects direct $assoc_args['key'] access instead of using
 * WP_CLI\Utils\get_flag_value() for flag extraction.
 *
 * get_flag_value() provides consistent default handling and supports
 * the --no-<flag> negation pattern automatically.
 *
 * Rule:    Use get_flag_value() instead of direct $assoc_args array access
 * Level:   SHOULD — violation reported as warning
 * Fix:     Replace $assoc_args['key'] with get_flag_value($assoc_args,'key',$default)
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Arguments;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class FlagExtractionSniff implements Sniff
{
    /**
     * Per-file CLI context cache.
     *
     * @var array<string, bool>
     */
    private $cliContextCache = [];

    /**
     * Per-file flag to track if get_flag_value is already used in the file.
     *
     * @var array<string, bool>
     */
    private $fileUsesGetFlagValue = [];

    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return array<int>
     */
    public function register(): array
    {
        return [T_VARIABLE];
    }

    /**
     * Processes this sniff when a variable token is encountered.
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

        if ($tokens[$stackPtr]['content'] !== '$assoc_args') {
            return;
        }

        // Look for direct array access: $assoc_args['key'].
        $next = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
        if ($next === false || $tokens[$next]['code'] !== T_OPEN_SQUARE_BRACKET) {
            return;
        }

        // Verify the key is a string literal.
        $keyToken = $phpcsFile->findNext(T_WHITESPACE, ($next + 1), null, true);
        if ($keyToken === false || $tokens[$keyToken]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            return;
        }

        // Check if this is inside a null coalescing (??) or isset() — which is
        // a common default-value pattern. Still flag, but the suggestion is
        // more about consistency than correctness.
        $phpcsFile->addWarning(
            'Consider using WP_CLI\Utils\get_flag_value( $assoc_args, \'%s\', $default ) instead of direct array access. get_flag_value() provides consistent default handling and --no-<flag> negation support.',
            $stackPtr,
            'DirectAccess',
            [trim($tokens[$keyToken]['content'], "\"'")]
        );
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
