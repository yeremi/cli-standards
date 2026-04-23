<?php
/**
 * ErrorSeveritySniff
 *
 * @package CLIStandards
 *
 * Detects usage of exit(), die(), and wp_die() inside WP-CLI command files.
 *
 * WP-CLI commands must use WP_CLI::error() for fatal failures and
 * WP_CLI::warning() for non-fatal conditions. Direct exit/die bypasses
 * WP-CLI's error handling, producing incorrect exit codes and no
 * formatted output.
 *
 * Rule:    Never use exit(), die(), or wp_die() in WP-CLI commands
 * Level:   MUST — violation blocks CI
 * Fix:     Use WP_CLI::error() for fatal failures, WP_CLI::halt() for exit-code-only commands
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\ErrorHandling;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class ErrorSeveritySniff implements Sniff
{
    /**
     * Whether the current file is a WP-CLI context.
     *
     * @var bool|null
     */
    private $cliContextCache = [];

    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return array<int>
     */
    public function register(): array
    {
        return [
            T_EXIT,     // Covers both exit() and die().
            T_STRING,   // Catches wp_die() function calls.
        ];
    }

    /**
     * Processes this sniff when one of its tokens is encountered.
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
        $token  = $tokens[$stackPtr];

        if ($token['code'] === T_EXIT) {
            // T_EXIT covers both exit() and die().
            $keyword = strtolower($token['content']);

            $phpcsFile->addError(
                'WP-CLI commands must not use %s(). Use WP_CLI::error() for fatal failures or WP_CLI::halt() for exit-code-only commands.',
                $stackPtr,
                'DirectExit',
                [$keyword]
            );
            return;
        }

        // Check for wp_die() calls.
        if ($token['code'] === T_STRING && strtolower($token['content']) === 'wp_die') {
            // Verify it's actually a function call (followed by open parenthesis).
            $next = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
            if ($next !== false && $tokens[$next]['code'] === T_OPEN_PARENTHESIS) {
                $phpcsFile->addError(
                    'WP-CLI commands must not use wp_die(). Use WP_CLI::error() instead — wp_die() is designed for HTTP request handling, not CLI.',
                    $stackPtr,
                    'WpDieInCli'
                );
            }
        }
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
