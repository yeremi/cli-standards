<?php
/**
 * Names
 *
 * @package CLIStandards
 *
 * Name matching that works with both PHPCS 3 and PHPCS 4 tokenization.
 *
 * PHPCS 3 splits `WP_CLI\Utils\format_items` and `\WP_CLI` into T_STRING
 * and T_NS_SEPARATOR tokens. PHPCS 4, like PHP 8, emits one
 * T_NAME_QUALIFIED / T_NAME_FULLY_QUALIFIED / T_NAME_RELATIVE token.
 * @since   1.2.0
 */

namespace CLIStandards\Helpers;

use PHP_CodeSniffer\Files\File;
use PHPCSUtils\Tokens\Collections;

final class Names
{
    /**
     * Tokens that can hold a (possibly qualified) name: T_STRING plus the
     * PHP 8 / PHPCS 4 name tokens.
     *
     * @return array<int|string, int|string>
     */
    public static function nameTokens(): array
    {
        return Collections::nameTokens();
    }

    /**
     * Unqualified name of a name token: `format_items` for
     * `WP_CLI\Utils\format_items`, `WP_CLI` for `\WP_CLI`.
     *
     * @param array<string, mixed> $token Token array.
     *
     * @return string|null Null when the token is not a name.
     */
    public static function lastSegment(array $token): ?string
    {
        if (!isset(self::nameTokens()[$token['code']])) {
            return null;
        }

        $position = strrpos($token['content'], '\\');

        return ($position === false) ? $token['content'] : substr($token['content'], ($position + 1));
    }

    /**
     * Whether a token is the WP_CLI class name (`WP_CLI` or `\WP_CLI`).
     *
     * @param array<string, mixed> $token Token array.
     *
     * @return bool
     */
    public static function isWpCliClass(array $token): bool
    {
        return isset(self::nameTokens()[$token['code']]) && ltrim($token['content'], '\\') === 'WP_CLI';
    }

    /**
     * Whether a token starts a name in the WP_CLI namespace, such as
     * `WP_CLI\Utils\format_items`.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Token position.
     *
     * @return bool
     */
    public static function isWpCliNamespaced(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $token  = $tokens[$stackPtr];

        if ($token['code'] === T_STRING) {
            // PHPCS 3: WP_CLI followed by a namespace separator.
            $next = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);

            return $token['content'] === 'WP_CLI' && $next !== false && $tokens[$next]['code'] === T_NS_SEPARATOR;
        }

        return isset(self::nameTokens()[$token['code']])
            && strpos(ltrim($token['content'], '\\'), 'WP_CLI\\') === 0;
    }

    /**
     * Whether a token is a call to a function with the given unqualified
     * name, written plain, fully qualified or namespaced.
     *
     * @param File   $phpcsFile The file being scanned.
     * @param int    $stackPtr  Token position.
     * @param string $name      Lowercase function name.
     *
     * @return bool
     */
    public static function isFunctionCall(File $phpcsFile, int $stackPtr, string $name): bool
    {
        $tokens  = $phpcsFile->getTokens();
        $segment = self::lastSegment($tokens[$stackPtr]);
        if ($segment === null || strtolower($segment) !== $name) {
            return false;
        }

        $next = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);

        return $next !== false && $tokens[$next]['code'] === T_OPEN_PARENTHESIS;
    }
}
