<?php
/**
 * CommandRegistrationSniff
 *
 * @package CLIStandards
 *
 * Detects WP_CLI::add_command() calls where the command name lacks
 * proper namespace format (at least `vendor subcommand`).
 *
 * WP-CLI commands must follow the `wp <vendor> <noun> [<verb>]` pattern
 * to avoid namespace collisions and provide consistent discoverability.
 *
 * Rule:    Commands must be registered with a namespaced name
 * Level:   MUST — violation blocks CI
 * Fix:     Use "myplugin list" instead of just "list"
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\CommandStructure;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class CommandRegistrationSniff implements Sniff
{
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return array<int>
     */
    public function register(): array
    {
        return [T_STRING];
    }

    /**
     * Processes this sniff when a string token is encountered.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  The position of the current token.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // Only interested in 'add_command'.
        if ($tokens[$stackPtr]['content'] !== 'add_command') {
            return;
        }

        // Verify this is WP_CLI::add_command().
        if (!$this->isWpCliMethod($phpcsFile, $stackPtr)) {
            return;
        }

        // Find the opening parenthesis.
        $openParen = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
        if ($openParen === false || $tokens[$openParen]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        // Find the first argument (command name).
        $firstArg = $phpcsFile->findNext(T_WHITESPACE, ($openParen + 1), null, true);
        if ($firstArg === false) {
            return;
        }

        // Only validate string literal command names.
        if ($tokens[$firstArg]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            // Variable or constant — can't validate statically.
            return;
        }

        $commandName = trim($tokens[$firstArg]['content'], "\"'");

        // Validate: command name must contain at least one space
        // (indicating namespace + subcommand).
        if (strpos($commandName, ' ') === false) {
            $phpcsFile->addError(
                'WP_CLI::add_command() command name "%s" must include a namespace: use "vendor subcommand" format (e.g., "myplugin list" instead of "list").',
                $firstArg,
                'MissingNamespace',
                [$commandName]
            );
            return;
        }

        // Validate: segments should be lowercase and use hyphens.
        $segments = explode(' ', $commandName);
        foreach ($segments as $segment) {
            if ($segment !== strtolower($segment)) {
                $phpcsFile->addWarning(
                    'Command name segment "%s" should be lowercase. WP-CLI conventions use lowercase hyphenated names.',
                    $firstArg,
                    'UppercaseSegment',
                    [$segment]
                );
            }
        }
    }

    /**
     * Check if the current token is a WP_CLI:: method call.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Position of the method name token.
     *
     * @return bool
     */
    private function isWpCliMethod(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $doubleColon = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);
        if ($doubleColon === false || $tokens[$doubleColon]['code'] !== T_DOUBLE_COLON) {
            return false;
        }

        $className = $phpcsFile->findPrevious(T_WHITESPACE, ($doubleColon - 1), null, true);
        if ($className === false || $tokens[$className]['content'] !== 'WP_CLI') {
            return false;
        }

        return true;
    }
}
