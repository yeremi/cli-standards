<?php
/**
 * ConfirmOnDeleteSniff
 *
 * @package CLIStandards
 *
 * Detects WP-CLI command methods with destructive semantics that do not
 * call WP_CLI::confirm() anywhere in their body.
 *
 * A command is destructive when either:
 * - a whole word of its command name (@subcommand, the name registered with
 *   WP_CLI::add_command() in the same file, or the method name) is a
 *   destructive keyword: delete, remove, flush, truncate, reset, purge,
 *   destroy, drop, clear, wipe, uninstall, erase ("dropdown" is not "drop");
 * - its body calls a content-destroying API (destructiveFunctions property):
 *   wp_delete_post(), wp_delete_user(), $wpdb->delete(), a $wpdb->query()
 *   with DELETE/TRUNCATE/DROP, ...
 *
 * This is the #1 ecosystem gap identified in the study: 40% of plugins
 * execute destructive operations with zero safety guardrails.
 *
 * Rule:    Destructive commands (delete, flush, reset, purge...) must call WP_CLI::confirm()
 * Level:   MUST — violation blocks CI
 * Fix:     Add WP_CLI::confirm( 'This will delete X items. Are you sure?', $assoc_args )
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Safety;

use CLIStandards\Helpers\AbstractCommandSniff;
use PHP_CodeSniffer\Files\File;

class ConfirmOnDeleteSniff extends AbstractCommandSniff
{
    /**
     * Keywords in method names that indicate destructive semantics.
     *
     * @var array<string>
     */
    private $destructiveKeywords = [
        'delete',
        'remove',
        'flush',
        'truncate',
        'reset',
        'purge',
        'destroy',
        'drop',
        'clear',
        'wipe',
        'uninstall',
        'erase',
    ];

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

        // Get the function name.
        $nameToken = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
        if ($nameToken === false || $tokens[$nameToken]['code'] !== T_STRING) {
            return;
        }

        // Ensure this function has a body to scan.
        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        if (!$this->isCommandHandler($phpcsFile, $stackPtr)) {
            return;
        }

        // Destructive by name (whole words of the command name) or by behavior.
        $keyword = $this->matchCommandKeyword($phpcsFile, $stackPtr, $this->destructiveKeywords);
        if ($keyword !== null) {
            $reason = sprintf('its name contains "%s"', $keyword);
        } else {
            $call = $this->findDestructiveCall($phpcsFile, $scopeStart, $scopeEnd);
            if ($call === null) {
                return;
            }
            $reason = sprintf('it calls %s', $call);
        }

        // Search the function body for WP_CLI::confirm().
        if (!$this->hasConfirmCall($phpcsFile, $scopeStart, $scopeEnd)) {
            $phpcsFile->addError(
                'Destructive command "%s" must call WP_CLI::confirm() before executing, because %s. This is the #1 safety gap in the WP-CLI ecosystem.',
                $nameToken,
                'MissingConfirm',
                [$tokens[$nameToken]['content'], $reason]
            );
        }
    }

    /**
     * Search a function body for a WP_CLI::confirm() call.
     *
     * @param File $phpcsFile  The file being scanned.
     * @param int  $scopeStart Start of function body.
     * @param int  $scopeEnd   End of function body.
     *
     * @return bool
     */
    private function hasConfirmCall(File $phpcsFile, int $scopeStart, int $scopeEnd): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $scopeStart; $i < $scopeEnd; $i++) {
            if ($tokens[$i]['code'] !== T_STRING || $tokens[$i]['content'] !== 'confirm') {
                continue;
            }

            // Verify it's WP_CLI::confirm().
            $doubleColon = $phpcsFile->findPrevious(T_WHITESPACE, ($i - 1), null, true);
            if ($doubleColon === false || $tokens[$doubleColon]['code'] !== T_DOUBLE_COLON) {
                continue;
            }

            $className = $phpcsFile->findPrevious(T_WHITESPACE, ($doubleColon - 1), null, true);
            if ($className !== false && $tokens[$className]['content'] === 'WP_CLI') {
                return true;
            }
        }

        return false;
    }
}
