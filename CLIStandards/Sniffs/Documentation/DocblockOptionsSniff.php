<?php
/**
 * DocblockOptionsSniff
 *
 * @package CLIStandards
 *
 * Detects WP-CLI command methods that lack a `## OPTIONS` section
 * in their docblock.
 *
 * WP-CLI uses docblock comments as the source of truth for command
 * help text. The `## OPTIONS` section documents all available flags.
 *
 * Rule:    Every WP-CLI command method must have a docblock with ## OPTIONS
 * Level:   MUST — violation blocks CI
 * Fix:     Add a @since-style docblock with '## OPTIONS' section before the method
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Documentation;

use CLIStandards\Helpers\AbstractCommandSniff;
use PHP_CodeSniffer\Files\File;

class DocblockOptionsSniff extends AbstractCommandSniff
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

        // Look backward for a docblock.
        $docblock = $this->findDocblock($phpcsFile, $stackPtr);

        if ($docblock === false) {
            $phpcsFile->addError(
                'WP-CLI command methods must have a docblock containing a "## OPTIONS" section.',
                $stackPtr,
                'MissingDocblock'
            );
            return;
        }

        // Check if the docblock contains an ## OPTIONS heading.
        $docContent = $this->getDocblockContent($phpcsFile, $docblock);

        if (!$this->docblockHasSection($docContent, 'OPTIONS')) {
            $phpcsFile->addError(
                'WP-CLI command docblock must contain a "## OPTIONS" section documenting all flags.',
                $stackPtr,
                'MissingOptionsSection'
            );
        }
    }
}
