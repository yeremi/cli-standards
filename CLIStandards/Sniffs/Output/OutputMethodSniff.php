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
use CLIStandards\Helpers\Names;
use PHPCSUtils\BackCompat\BCTokens;
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

        $message = 'WP-CLI commands must not use %s for output. Use WP_CLI::log(), WP_CLI::success(), WP_CLI::line(), or WP_CLI::error() instead.';

        $plan = $this->planLineFix($phpcsFile, $stackPtr);
        if ($plan === null) {
            $phpcsFile->addError($message, $stackPtr, 'RawOutput', [$method]);
            return;
        }

        $fix = $phpcsFile->addFixableError($message, $stackPtr, 'RawOutput', [$method]);
        if ($fix !== true) {
            return;
        }

        // One replacement per token: fixer calls on the same token inside a
        // changeset overwrite each other.
        $changes = [
            $stackPtr => 'WP_CLI::line(' . ($tokens[$stackPtr + 1]['code'] === T_WHITESPACE ? '' : ' '),
        ];
        foreach ($plan['remove'] as $ptr) {
            $changes[$ptr] = '';
        }
        $changes[$plan['last']] = ($plan['replace'] ?? $tokens[$plan['last']]['content']) . ' )';

        $phpcsFile->fixer->beginChangeset();
        foreach ($changes as $ptr => $content) {
            $phpcsFile->fixer->replaceToken($ptr, $content);
        }
        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Plan the echo/print → WP_CLI::line() fix, when it cannot change output.
     *
     * WP_CLI::line() appends a newline, so only statements that already end
     * their output with one are fixable: `echo $x . PHP_EOL;` and
     * `echo "...\n";`. The trailing newline is removed by the fix. The
     * statement must stand alone (`echo` starts it, `;` ends it) and print a
     * single expression.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Position of the T_ECHO/T_PRINT token.
     *
     * @return array{last: int, remove: array<int>, replace: string|null}|null Replace is
     *         the new content of the last token.
     */
    private function planLineFix(File $phpcsFile, int $stackPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();

        // The statement starts with echo/print.
        $prev = $phpcsFile->findPrevious(BCTokens::emptyTokens(), ($stackPtr - 1), null, true);
        $starts = [T_SEMICOLON, T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET, T_OPEN_TAG, T_ELSE];
        if ($prev === false
            || (!in_array($tokens[$prev]['code'], $starts, true)
                && !($tokens[$prev]['code'] === T_CLOSE_PARENTHESIS
                    && isset($tokens[$prev]['parenthesis_owner'])
                    && in_array($tokens[$tokens[$prev]['parenthesis_owner']]['code'], [T_IF, T_ELSEIF, T_FOREACH, T_FOR, T_WHILE], true)))
        ) {
            return null;
        }

        // ... and ends with ";", with a single top-level expression.
        for ($i = ($stackPtr + 1); $i < $phpcsFile->numTokens; $i++) {
            $code = $tokens[$i]['code'];
            if ($code === T_OPEN_PARENTHESIS && isset($tokens[$i]['parenthesis_closer'])) {
                $i = $tokens[$i]['parenthesis_closer'];
                continue;
            }
            if (isset($tokens[$i]['bracket_closer']) && in_array($code, [T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET, T_OPEN_CURLY_BRACKET], true)) {
                $i = $tokens[$i]['bracket_closer'];
                continue;
            }
            if ($code === T_COMMA || $code === T_CLOSE_TAG || $code === T_INLINE_THEN) {
                return null;
            }
            if ($code === T_SEMICOLON) {
                break;
            }
        }

        if (!isset($tokens[$i]) || $tokens[$i]['code'] !== T_SEMICOLON) {
            return null;
        }

        $last = $phpcsFile->findPrevious(BCTokens::emptyTokens(), ($i - 1), $stackPtr, true);
        if ($last === false || $last === $stackPtr) {
            return null;
        }

        // `... . PHP_EOL`: drop the concatenation.
        if ($tokens[$last]['code'] === T_STRING && $tokens[$last]['content'] === 'PHP_EOL') {
            $dot = $phpcsFile->findPrevious(BCTokens::emptyTokens(), ($last - 1), $stackPtr, true);
            if ($dot === false || $tokens[$dot]['code'] !== T_STRING_CONCAT) {
                return null;
            }

            $before = $phpcsFile->findPrevious(BCTokens::emptyTokens(), ($dot - 1), $stackPtr, true);
            if ($before === false || $before === $stackPtr) {
                return null;
            }

            return ['last' => $before, 'remove' => range($before + 1, $last), 'replace' => null];
        }

        // `"...\n"`: drop the escape from the double-quoted literal.
        $content = $tokens[$last]['content'];
        if (in_array($tokens[$last]['code'], [T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING], true)
            && substr($content, -3) === '\n"'
            && substr($content, -4, 1) !== '\\'
            && ($tokens[$last]['code'] === T_DOUBLE_QUOTED_STRING || $content[0] === '"')
        ) {
            return ['last' => $last, 'remove' => [], 'replace' => substr($content, 0, -3) . '"'];
        }

        return null;
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
            if (!Names::isWpCliClass($tokens[$i])) {
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
