<?php
/**
 * DocblockExamplesSniff
 *
 * @package CLIStandards
 *
 * Detects WP-CLI command methods that lack a `## EXAMPLES` section
 * in their docblock.
 *
 * The `## EXAMPLES` section provides realistic invocation examples
 * that appear in `wp help <command>` output.
 *
 * Rule:    Every WP-CLI command method must have a ## EXAMPLES section in its docblock
 * Level:   MUST — violation blocks CI
 * Fix:     Add '## EXAMPLES' with at least one realistic wp ... invocation
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Documentation;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class DocblockExamplesSniff implements Sniff
{
    /**
     * Command method indicators.
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
            // MissingDocblock is already reported by DocblockOptionsSniff.
            // Avoid duplicate noise — only flag the specific missing section.
            return;
        }

        $docContent = $this->getDocblockContent($phpcsFile, $docblock);

        if (stripos($docContent, '## EXAMPLES') === false) {
            $phpcsFile->addError(
                'WP-CLI command docblock must contain a "## EXAMPLES" section with at least one realistic invocation.',
                $stackPtr,
                'MissingExamplesSection'
            );
        }
    }

    /**
     * Determine if a function body contains WP-CLI indicators.
     *
     * @param File $phpcsFile  The file being scanned.
     * @param int  $scopeStart Start of function body.
     * @param int  $scopeEnd   End of function body.
     *
     * @return bool
     */
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

    /**
     * Find the docblock comment preceding a function declaration.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Position of the T_FUNCTION token.
     *
     * @return int|false
     */
    private function findDocblock(File $phpcsFile, int $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $skip = [
            T_WHITESPACE,
            T_STATIC,
            T_PUBLIC,
            T_PROTECTED,
            T_PRIVATE,
            T_ABSTRACT,
            T_FINAL,
        ];

        $prev = $phpcsFile->findPrevious($skip, ($stackPtr - 1), null, true);

        if ($prev !== false && $tokens[$prev]['code'] === T_DOC_COMMENT_CLOSE_TAG) {
            return $tokens[$prev]['comment_opener'];
        }

        return false;
    }

    /**
     * Extract the full text content of a docblock.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $opener    Position of the doc comment opener.
     *
     * @return string
     */
    private function getDocblockContent(File $phpcsFile, int $opener): string
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$opener]['comment_closer'];

        $content = '';
        for ($i = $opener; $i <= $closer; $i++) {
            $content .= $tokens[$i]['content'];
        }

        return $content;
    }
}
