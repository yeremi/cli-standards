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

use CLIStandards\Helpers\AbstractCommandSniff;
use PHP_CodeSniffer\Files\File;

class SinceAnnotationSniff extends AbstractCommandSniff
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

        // Only public, non-magic methods that use WP-CLI are commands.
        if (!$this->isCommandHandler($phpcsFile, $stackPtr)) {
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
}
