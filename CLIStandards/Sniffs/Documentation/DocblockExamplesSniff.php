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

use CLIStandards\Helpers\AbstractCommandSniff;
use PHP_CodeSniffer\Files\File;

class DocblockExamplesSniff extends AbstractCommandSniff
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
        // Only public, non-magic methods that use WP-CLI are commands.
        if (!$this->isCommandHandler($phpcsFile, $stackPtr)) {
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
}
