<?php
/**
 * OutputMethodSniff
 *
 * @package CLIStandards
 *
 * Detects usage of `echo` and `print` in WP-CLI command code: command
 * handlers and the same-class methods they call. Web code in the same file
 * (admin notices, settings screens) is not reported.
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

use CLIStandards\Helpers\AbstractCommandSniff;
use PHP_CodeSniffer\Files\File;

class OutputMethodSniff extends AbstractCommandSniff
{
    /**
     * Per-file CLI context cache.
     *
     * @var array<string, bool>
     */
    private $cliContextCache = [];

    /**
     * Token ranges of command code per file, keyed by file and token count.
     *
     * @var array<string, array<int, array{0: int, 1: int}>>
     */
    private $commandRangeCache = [];

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

        // Only output produced while a command runs: inside a command handler
        // or a same-class method it calls. Admin screens, notices and other
        // web code in the same file are not CLI output.
        if (!$this->isInCommandCode($phpcsFile, $stackPtr)) {
            return;
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

    /**
     * Whether a token is inside a command handler or a same-class method
     * reachable from one (see AbstractCommandSniff::getReachableBodies()).
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Position of the token.
     *
     * @return bool
     */
    private function isInCommandCode(File $phpcsFile, int $stackPtr): bool
    {
        $key = $phpcsFile->getFilename() . ':' . $phpcsFile->numTokens;

        if (!isset($this->commandRangeCache[$key])) {
            $tokens = $phpcsFile->getTokens();
            $ranges = [];

            for ($i = 0; $i < $phpcsFile->numTokens; $i++) {
                $code = $tokens[$i]['code'];
                if (($code === T_FUNCTION || $code === T_CLOSURE || $code === T_FN)
                    && $this->isCommandHandler($phpcsFile, $i)
                ) {
                    foreach ($this->getReachableBodies($phpcsFile, $i) as $range) {
                        $ranges[] = $range;
                    }
                }
            }

            $this->commandRangeCache = [$key => $ranges];
        }

        foreach ($this->commandRangeCache[$key] as [$start, $end]) {
            if ($stackPtr > $start && $stackPtr < $end) {
                return true;
            }
        }

        return false;
    }
}
