<?php
/**
 * DryRunSupportSniff
 *
 * @package CLIStandards
 *
 * Detects state-changing WP-CLI commands that don't support --dry-run.
 *
 * Commands that modify data (create, update, delete, migrate, sync, import,
 * flush, reset) should support --dry-run to preview changes without
 * executing them. Only 20% of plugins implement this, but 100% of
 * top-tier plugins in the study do.
 *
 * Rule:    State-changing commands should support --dry-run to preview without executing
 * Level:   SHOULD — violation reported as warning
 * Fix:     Check get_flag_value($assoc_args,'dry-run',false) and skip writes when true
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Safety;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class DryRunSupportSniff implements Sniff
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

        $functionName = strtolower($tokens[$nameToken]['content']);

        // Check if this is a state-changing command.
        $isStateChanging = false;
        foreach ($this->stateChangingKeywords as $keyword) {
            if (strpos($functionName, $keyword) !== false) {
                $isStateChanging = true;
                break;
            }
        }

        if (!$isStateChanging) {
            return;
        }

        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        // Verify it's a CLI command handler.
        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        if (!$this->isCommandHandler($phpcsFile, $scopeStart, $scopeEnd)) {
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

    private function isCommandHandler(File $phpcsFile, int $scopeStart, int $scopeEnd): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $scopeStart; $i < $scopeEnd; $i++) {
            if ($tokens[$i]['code'] === T_STRING && $tokens[$i]['content'] === 'WP_CLI') {
                $next = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);
                if ($next !== false && $tokens[$next]['code'] === T_DOUBLE_COLON) {
                    return true;
                }
            }
        }

        return false;
    }

    private function findDocblock(File $phpcsFile, int $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $skip   = [T_WHITESPACE, T_STATIC, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_ABSTRACT, T_FINAL];
        $prev   = $phpcsFile->findPrevious($skip, ($stackPtr - 1), null, true);

        if ($prev !== false && $tokens[$prev]['code'] === T_DOC_COMMENT_CLOSE_TAG) {
            return $tokens[$prev]['comment_opener'];
        }

        return false;
    }

    private function getDocblockContent(File $phpcsFile, int $opener): string
    {
        $tokens  = $phpcsFile->getTokens();
        $closer  = $tokens[$opener]['comment_closer'];
        $content = '';

        for ($i = $opener; $i <= $closer; $i++) {
            $content .= $tokens[$i]['content'];
        }

        return $content;
    }
}
