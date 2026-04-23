<?php
/**
 * SinceAnnotationSniff
 *
 * @package CLIStandards
 *
 * Detects WP-CLI command methods that lack a @since version annotation
 * in their docblock.
 *
 * @since annotations enable deprecation tracking and changelog generation.
 * Sparse across the ecosystem, but critical for plugins with evolving
 * CLI interfaces.
 *
 * Rule:    WP-CLI command methods should include a @since version annotation
 * Level:   SHOULD — violation reported as warning
 * Fix:     Add '@since 1.0.0' (or the plugin version that introduced the command)
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Documentation;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class SinceAnnotationSniff implements Sniff
{
    /**
     * CLI indicators for command handler detection.
     *
     * @var array<string>
     */
    private $cliIndicators = [
        'WP_CLI',
        'assoc_args',
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

        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $scopeStart = $tokens[$stackPtr]['scope_opener'];
        $scopeEnd   = $tokens[$stackPtr]['scope_closer'];

        if (!$this->isCommandHandler($phpcsFile, $scopeStart, $scopeEnd)) {
            return;
        }

        $docblock = $this->findDocblock($phpcsFile, $stackPtr);

        if ($docblock === false) {
            // No docblock — other sniffs handle MissingDocblock.
            return;
        }

        $docContent = $this->getDocblockContent($phpcsFile, $docblock);

        if (stripos($docContent, '@since') === false) {
            $nameToken = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
            $phpcsFile->addWarning(
                'WP-CLI command "%s" should have a @since annotation in its docblock for version tracking and deprecation management.',
                $nameToken,
                'MissingSince',
                [$tokens[$nameToken]['content'] ?? 'unknown']
            );
        }
    }

    private function isCommandHandler(File $phpcsFile, int $scopeStart, int $scopeEnd): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $scopeStart; $i < $scopeEnd; $i++) {
            if ($tokens[$i]['code'] === T_STRING || $tokens[$i]['code'] === T_VARIABLE) {
                foreach ($this->cliIndicators as $indicator) {
                    if (strpos($tokens[$i]['content'], $indicator) !== false) {
                        return true;
                    }
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
