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

use CLIStandards\Helpers\AbstractCommandSniff;
use CLIStandards\Helpers\Names;
use PHP_CodeSniffer\Files\File;

class ConfirmPassthroughSniff extends AbstractCommandSniff
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

        // WP_CLI::confirm( message, $assoc_args ) requires 2 args (1 comma),
        // unless the call is guarded by an explicit --yes check.
        if ($commaCount !== 0 || $this->isGuardedByYesFlag($phpcsFile, $stackPtr)) {
            return;
        }

        $message = 'WP_CLI::confirm() must receive $assoc_args as the second argument to enable --yes bypass in automation. Use: WP_CLI::confirm( $message, $assoc_args )';

        // Fixable only when the message is there and the enclosing command
        // handler names its assoc args, so the fix cannot pick a wrong variable.
        $lastArg   = $phpcsFile->findPrevious(T_WHITESPACE, ($closeParen - 1), $openParen, true);
        $assocName = $this->getFixAssocArgsName($phpcsFile, $stackPtr);

        if ($lastArg === false || $assocName === null) {
            $phpcsFile->addError($message, $stackPtr, 'MissingAssocArgs');
            return;
        }

        $fix = $phpcsFile->addFixableError($message, $stackPtr, 'MissingAssocArgs');
        if ($fix === true) {
            $phpcsFile->fixer->addContent($lastArg, ', ' . $assocName);
        }
    }

    /**
     * Assoc-args variable to pass to confirm() in the enclosing function.
     *
     * The second parameter of a command handler, or a parameter of any other
     * function named $assoc_args / $assocArgs (helpers the command passes
     * them to).
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Token inside the function.
     *
     * @return string|null
     */
    private function getFixAssocArgsName(File $phpcsFile, int $stackPtr): ?string
    {
        $functionPtr = $this->findEnclosingCallable($phpcsFile, $stackPtr);
        if ($functionPtr === null) {
            return null;
        }

        if ($this->isCommandHandler($phpcsFile, $functionPtr)) {
            return $this->getAssocArgsName($phpcsFile, $functionPtr);
        }

        foreach ($phpcsFile->getMethodParameters($functionPtr) as $parameter) {
            if (in_array($parameter['name'], self::ASSOC_ARGS_NAMES, true)) {
                return $parameter['name'];
            }
        }

        return null;
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
        if ($className === false || !Names::isWpCliClass($tokens[$className])) {
            return false;
        }

        return true;
    }

    /**
     * Whether a confirm() call only runs when --yes was not passed.
     *
     * Accepts an enclosing `if`/`elseif` (with or without braces) whose
     * condition mentions the 'yes' flag together with the assoc args, e.g.
     * `if ( ! isset( $assoc_args['yes'] ) )` or
     * `if ( ! get_flag_value( $assoc_args, 'yes' ) )`.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Position of the "confirm" token.
     *
     * @return bool
     */
    private function isGuardedByYesFlag(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $owners = [];

        // if/elseif with braces: they are conditions of the call.
        foreach ($tokens[$stackPtr]['conditions'] as $ptr => $code) {
            if ($code === T_IF || $code === T_ELSEIF) {
                $owners[] = $ptr;
            }
        }

        // if/elseif without braces: PHPCS reports the `if` itself as the start
        // of the statement, or the statement follows the closing parenthesis.
        $start = $phpcsFile->findStartOfStatement($stackPtr);
        if (in_array($tokens[$start]['code'], [T_IF, T_ELSEIF], true)
            && isset($tokens[$start]['parenthesis_closer'])
            && $tokens[$start]['parenthesis_closer'] < $stackPtr
        ) {
            $owners[] = $start;
        }

        $prev = $phpcsFile->findPrevious(T_WHITESPACE, ($start - 1), null, true);
        if ($prev !== false && $tokens[$prev]['code'] === T_CLOSE_PARENTHESIS
            && isset($tokens[$prev]['parenthesis_owner'])
            && in_array($tokens[$tokens[$prev]['parenthesis_owner']]['code'], [T_IF, T_ELSEIF], true)
        ) {
            $owners[] = $tokens[$prev]['parenthesis_owner'];
        }

        foreach ($owners as $owner) {
            if (!isset($tokens[$owner]['parenthesis_opener'], $tokens[$owner]['parenthesis_closer'])) {
                continue;
            }

            $hasYes       = false;
            $hasAssocArgs = false;
            for ($i = $tokens[$owner]['parenthesis_opener']; $i < $tokens[$owner]['parenthesis_closer']; $i++) {
                if ($tokens[$i]['code'] === T_CONSTANT_ENCAPSED_STRING && trim($tokens[$i]['content'], "\"'") === 'yes') {
                    $hasYes = true;
                } elseif ($tokens[$i]['code'] === T_VARIABLE && $this->isAssocArgsVariable($phpcsFile, $i)) {
                    $hasAssocArgs = true;
                }
            }

            if ($hasYes && $hasAssocArgs) {
                return true;
            }
        }

        return false;
    }
}
