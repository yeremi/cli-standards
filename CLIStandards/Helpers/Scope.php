<?php
/**
 * Scope
 *
 * @package CLIStandards
 *
 * Scope lookups PHPCS does not record in a token's "conditions".
 * @since   1.2.1
 */

namespace CLIStandards\Helpers;

use PHP_CodeSniffer\Files\File;

final class Scope
{
    /**
     * Innermost arrow function (`fn() => ...`) whose body contains a token.
     *
     * Arrow functions are not part of a token's "conditions", so they are
     * found by their scope opener and closer.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Token position.
     *
     * @return int|null Position of the T_FN token.
     */
    public static function enclosingArrowFunction(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $fn     = $phpcsFile->findPrevious(T_FN, $stackPtr);

        while ($fn !== false) {
            if (isset($tokens[$fn]['scope_closer']) && $tokens[$fn]['scope_closer'] >= $stackPtr) {
                return $fn;
            }
            $fn = $phpcsFile->findPrevious(T_FN, ($fn - 1));
        }

        return null;
    }
}
