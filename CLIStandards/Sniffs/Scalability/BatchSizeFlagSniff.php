<?php
/**
 * BatchSizeFlagSniff
 *
 * @package CLIStandards
 *
 * Detects bulk WP-CLI commands that process batches but don't expose
 * a --batch-size or --per-page flag to let users tune batch volume.
 *
 * Critical for resource-constrained environments where the default
 * batch size may be too large or too small.
 *
 * Rule:    Bulk commands should expose --batch-size (or --per-page) for user control
 * Level:   SHOULD — violation reported as warning
 * Fix:     Add --batch-size flag: $batch = (int) get_flag_value($assoc_args,'batch-size',100)
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Scalability;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class BatchSizeFlagSniff implements Sniff
{
    /**
     * Keywords in method names indicating bulk/batch operations.
     *
     * @var array<string>
     */
    private $bulkKeywords = [
        'migrate',
        'sync',
        'import',
        'export',
        'regenerate',
        'rebuild',
        'process',
        'batch',
        'bulk',
        'reindex',
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

        $isBulk = false;
        foreach ($this->bulkKeywords as $keyword) {
            if (strpos($functionName, $keyword) !== false) {
                $isBulk = true;
                break;
            }
        }

        if (!$isBulk) {
            return;
        }

        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        if (!$this->isCommandHandler($phpcsFile, $scopeStart, $scopeEnd)) {
            return;
        }

        // Check docblock and body for batch-size or per-page flag.
        $hasBatchSizeFlag = false;

        // Check docblock.
        $docblock = $this->findDocblock($phpcsFile, $stackPtr);
        if ($docblock !== false) {
            $docContent = $this->getDocblockContent($phpcsFile, $docblock);
            if (stripos($docContent, 'batch-size') !== false
                || stripos($docContent, 'batch_size') !== false
                || stripos($docContent, 'per-page') !== false
                || stripos($docContent, 'per_page') !== false
            ) {
                $hasBatchSizeFlag = true;
            }
        }

        // Check function body for batch-size key access.
        if (!$hasBatchSizeFlag) {
            for ($i = $scopeStart; $i < $scopeEnd; $i++) {
                if ($tokens[$i]['code'] === T_CONSTANT_ENCAPSED_STRING) {
                    $val = trim($tokens[$i]['content'], "\"'");
                    if (in_array($val, ['batch-size', 'batch_size', 'per-page', 'per_page'], true)) {
                        $hasBatchSizeFlag = true;
                        break;
                    }
                }
            }
        }

        if (!$hasBatchSizeFlag) {
            $phpcsFile->addWarning(
                'Bulk command "%s" should accept a --batch-size (or --per-page) flag to let users tune batch volume for their environment. Default batch sizes may be too large for resource-constrained hosts.',
                $nameToken,
                'MissingBatchSizeFlag',
                [$tokens[$nameToken]['content']]
            );
        }
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
