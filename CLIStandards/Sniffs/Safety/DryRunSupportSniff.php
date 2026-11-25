<?php
/**
 * DryRunSupportSniff
 *
 * @package CLIStandards
 *
 * Detects state-changing WP-CLI commands that don't support --dry-run.
 *
 * Commands that modify data should support --dry-run to preview changes
 * without executing them. A command is state-changing when a whole word of
 * its command name is a state-changing keyword (create, update, delete,
 * migrate, sync, import, flush, reset, ...), or when its body calls a
 * content-destroying API (see ConfirmOnDelete). Only 20% of plugins implement this, but 100% of
 * top-tier plugins in the study do.
 *
 * Rule:    State-changing commands should support --dry-run to preview without executing
 * Level:   SHOULD — violation reported as warning
 * Fix:     Check get_flag_value($assoc_args,'dry-run',false) and skip writes when true
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Safety;

use CLIStandards\Helpers\AbstractCommandSniff;
use PHP_CodeSniffer\Files\File;

class DryRunSupportSniff extends AbstractCommandSniff
{
    /**
     * Keywords in method names indicating state-changing operations.
     *
     * @var array<string>
     */
    private $stateChangingKeywords = [
        'create',
        'update',
        'delete',
        'remove',
        'migrate',
        'sync',
        'import',
        'flush',
        'reset',
        'purge',
        'install',
        'uninstall',
        'activate',
        'deactivate',
        'regenerate',
        'rebuild',
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

        $nameToken = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
        if ($nameToken === false || $tokens[$nameToken]['code'] !== T_STRING) {
            return;
        }

        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        // Verify it's a CLI command handler.
        if (!$this->isCommandHandler($phpcsFile, $stackPtr)) {
            return;
        }

        // State-changing by name (whole words of the command name), or
        // destructive by behavior.
        if ($this->matchCommandKeyword($phpcsFile, $stackPtr, $this->stateChangingKeywords) === null
            && $this->findDestructiveCall($phpcsFile, $scopeStart, $scopeEnd) === null
        ) {
            return;
        }

        // Check docblock for --dry-run.
        $docblock = $this->findDocblock($phpcsFile, $stackPtr);
        $hasDryRunDoc = false;

        if ($docblock !== false) {
            $docContent = $this->getDocblockContent($phpcsFile, $docblock);
            $hasDryRunDoc = (stripos($docContent, 'dry-run') !== false
                || stripos($docContent, 'dry_run') !== false);
        }

        // Also check function body for dry-run handling.
        $hasDryRunBody = $this->hasDryRunInBody($phpcsFile, $scopeStart, $scopeEnd);

        if (!$hasDryRunDoc && !$hasDryRunBody) {
            $phpcsFile->addWarning(
                'State-changing command "%s" should support --dry-run to preview changes without executing. Only 20%% of plugins implement this, but 100%% of top-tier plugins do.',
                $nameToken,
                'MissingDryRun',
                [$tokens[$nameToken]['content']]
            );
        }
    }

    /**
     * Check for dry-run references in function body.
     */
    private function hasDryRunInBody(File $phpcsFile, int $scopeStart, int $scopeEnd): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $scopeStart; $i < $scopeEnd; $i++) {
            if ($tokens[$i]['code'] === T_CONSTANT_ENCAPSED_STRING) {
                $val = trim($tokens[$i]['content'], "\"'");
                if ($val === 'dry-run' || $val === 'dry_run') {
                    return true;
                }
            }
        }

        return false;
    }
}
