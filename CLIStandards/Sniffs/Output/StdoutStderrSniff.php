<?php
/**
 * StdoutStderrSniff
 *
 * @package CLIStandards
 *
 * Heuristic check for stdout/stderr separation in WP-CLI commands.
 *
 * Detects commands that mix WP_CLI::log() (which writes to stdout) with
 * data output in the same method, suggesting potential stdout pollution
 * that breaks pipeline integration.
 *
 * This is the most heuristic-heavy SHOULD-level check. It warns when
 * a function uses both WP_CLI::log() and format_items() — suggesting
 * the log() messages may contaminate structured output.
 *
 * Rule:    Do not mix human-readable log() with structured format_items() output
 * Level:   SHOULD — violation reported as warning
 * Fix:     Use WP_CLI::debug() or ::warning() for status messages to keep stdout clean
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Output;

use CLIStandards\Helpers\Names;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class StdoutStderrSniff implements Sniff
{
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

        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        $hasLog         = false;
        $hasFormatItems = false;
        $logPosition    = 0;

        for ($i = $scopeStart; $i < $scopeEnd; $i++) {
            $segment = Names::lastSegment($tokens[$i]);
            if ($segment === null) {
                continue;
            }

            // Detect WP_CLI::log().
            if ($tokens[$i]['code'] === T_STRING && $segment === 'log') {
                $doubleColon = $phpcsFile->findPrevious(T_WHITESPACE, ($i - 1), null, true);
                if ($doubleColon !== false && $tokens[$doubleColon]['code'] === T_DOUBLE_COLON) {
                    $className = $phpcsFile->findPrevious(T_WHITESPACE, ($doubleColon - 1), null, true);
                    if ($className !== false && Names::isWpCliClass($tokens[$className])) {
                        $hasLog      = true;
                        $logPosition = $i;
                    }
                }
            }

            // Detect format_items().
            if ($segment === 'format_items') {
                $hasFormatItems = true;
            }
        }

        // Only warn if both are present — mixing human messages with structured data.
        if ($hasLog && $hasFormatItems) {
            $nameToken = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
            $phpcsFile->addWarning(
                'Command "%s" mixes WP_CLI::log() with format_items() output. Consider using WP_CLI::debug() or WP_CLI::warning() for status messages to keep stdout clean for structured data (e.g., when piping to jq).',
                $logPosition,
                'MixedOutput',
                [$tokens[$nameToken]['content'] ?? 'unknown']
            );
        }
    }
}
