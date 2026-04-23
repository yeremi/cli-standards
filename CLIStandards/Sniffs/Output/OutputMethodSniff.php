<?php
/**
 * OutputMethodSniff
 *
 * @package CLIStandards
 *
 * Detects usage of `echo` and `print` in WP-CLI command files.
 *
 * WP-CLI commands must use WP_CLI::log(), WP_CLI::success(), WP_CLI::error(),
 * or WP_CLI::line() instead of raw output functions.
 *
 * Rule:    Never use echo or print in WP-CLI command files
 * Level:   MUST — violation blocks CI
 * Fix:     Replace echo/print with WP_CLI::log(), ::success(), ::line(), or ::error()
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Output;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class OutputMethodSniff implements Sniff
{
    /**
     * Per-file CLI context cache.
     *
     * @var array<string, bool>
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
            T_ECHO,
            T_PRINT,
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

        // Determine WP-CLI context once per file.
        if (!isset($this->cliContextCache[$filename])) {
            $this->cliContextCache[$filename] = $this->detectCliContext($phpcsFile);

            // If not a CLI file, skip the entire file.
            if (!$this->cliContextCache[$filename]) {
                return $phpcsFile->numTokens;
            }
        }

        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

        $method = ($token['code'] === T_ECHO) ? 'echo' : 'print';

        $phpcsFile->addError(
            'WP-CLI commands must not use %s for output. Use WP_CLI::log(), WP_CLI::success(), WP_CLI::line(), or WP_CLI::error() instead.',
            $stackPtr,
            'RawOutput',
            [$method]
        );
    }

    /**
     * Detect whether the current file is a WP-CLI command context.
     *
     * A file is considered a CLI context if it contains any of:
     * - WP_CLI::add_command(
     * - WP_CLI::log(
     * - WP_CLI::success(
     * - WP_CLI::error(
     * - WP_CLI::warning(
     * - WP_CLI::confirm(
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

            // Check for :: following WP_CLI.
            $next = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);
            if ($next !== false && $tokens[$next]['code'] === T_DOUBLE_COLON) {
                return true;
            }
        }

        return false;
    }
}
