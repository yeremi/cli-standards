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
        // Closures count only when passed directly to WP_CLI::add_command().
        return [T_FUNCTION, T_CLOSURE, T_FN];
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

        // A 'longdesc' passed to WP_CLI::add_command() can hold the examples.
        $registration = $this->findRegistration($phpcsFile, $stackPtr);
        if ($registration !== null && $this->docblockHasSection((string) $registration['longdesc'], 'EXAMPLES')) {
            return;
        }

        $documentedByArgs = $registration !== null
            && ($registration['synopsis'] || $registration['shortdesc'] || $registration['longdesc'] !== null);

        $docblock = $this->findDocblock($phpcsFile, $stackPtr);

        if ($docblock === false && !$documentedByArgs) {
            // MissingDocblock is already reported by DocblockOptionsSniff.
            // Avoid duplicate noise — only flag the specific missing section.
            return;
        }

        $docContent = ($docblock !== false) ? $this->getDocblockContent($phpcsFile, $docblock) : '';

        if (!$this->docblockHasSection($docContent, 'EXAMPLES')) {
            $phpcsFile->addError(
                'WP-CLI command docblock must contain a "## EXAMPLES" section with at least one realistic invocation (or an "## EXAMPLES" heading in the "longdesc" passed to WP_CLI::add_command()).',
                $stackPtr,
                'MissingExamplesSection'
            );
        }
    }
}
