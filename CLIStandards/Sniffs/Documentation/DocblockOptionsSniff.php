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

        // WP_CLI::add_command( $name, $callable, [ 'synopsis' => ... ] ) documents
        // the options without a docblock; so does an ## OPTIONS heading in 'longdesc'.
        $registration = $this->findRegistration($phpcsFile, $stackPtr);
        if ($registration !== null
            && ($registration['synopsis'] || $this->docblockHasSection((string) $registration['longdesc'], 'OPTIONS'))
        ) {
            return;
        }

        $documentedByArgs = $registration !== null
            && ($registration['shortdesc'] || $registration['longdesc'] !== null);

        // Look backward for a docblock.
        $docblock = $this->findDocblock($phpcsFile, $stackPtr);

        if ($docblock === false && !$documentedByArgs) {
            $phpcsFile->addError(
                'WP-CLI command methods must have a docblock containing a "## OPTIONS" section.',
                $stackPtr,
                'MissingDocblock'
            );
            return;
        }

        // Check if the docblock contains an ## OPTIONS heading.
        $docContent = ($docblock !== false) ? $this->getDocblockContent($phpcsFile, $docblock) : '';

        if (!$this->docblockHasSection($docContent, 'OPTIONS')) {
            $phpcsFile->addError(
                'WP-CLI command docblock must contain a "## OPTIONS" section documenting all flags (or pass a "synopsis" to WP_CLI::add_command()).',
                $stackPtr,
                'MissingOptionsSection'
            );
        }
    }
}
