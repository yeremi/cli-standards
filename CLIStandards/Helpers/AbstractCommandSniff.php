<?php
/**
 * AbstractCommandSniff
 *
 * @package CLIStandards
 *
 * Shared base for sniffs that only apply to WP-CLI command handlers.
 *
 * WP-CLI exposes every public, non-magic method of a registered command
 * class as a subcommand (`__invoke` is the exception and is a command too).
 * Constructors, private helpers and methods that only register commands are
 * not commands, so they are excluded here, in one place, for all sniffs.
 *
 * Ruleset property available on every sniff extending this class:
 *
 *     <rule ref="CLIStandards.Documentation.DocblockOptions">
 *         <properties>
 *             <property name="ignoreMethods" type="array">
 *                 <element value="services"/>
 *             </property>
 *         </properties>
 *     </rule>
 *
 * @since   1.1.0
 */

namespace CLIStandards\Helpers;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

abstract class AbstractCommandSniff implements Sniff
{
    /**
     * Method names (case-insensitive) that are never treated as commands.
     *
     * Configurable from a ruleset. Use it for public lifecycle methods of
     * command classes that WP-CLI would otherwise expose as subcommands.
     *
     * @var array<string>|string
     */
    public $ignoreMethods = [];

    /**
     * Conventional names of the WP-CLI associative arguments parameter.
     *
     * @var array<string>
     */
    protected const ASSOC_ARGS_NAMES = ['$assoc_args', '$assocArgs'];

    /**
     * WP_CLI static methods that only register commands or hooks.
     *
     * @var array<string>
     */
    protected const REGISTRATION_METHODS = ['add_command', 'add_hook'];

    /**
     * Tokens that open a nested scope whose body belongs to another callable.
     *
     * @var array<int|string>
     */
    private const NESTED_CALLABLES = [T_CLOSURE, T_FUNCTION, T_FN, T_ANON_CLASS];

    /**
     * Class-like scopes in which a function is a method.
     *
     * @var array<int|string>
     */
    private const OO_SCOPES = [T_CLASS, T_TRAIT, T_ANON_CLASS, T_INTERFACE];

    /**
     * Determine whether a T_FUNCTION token declares a WP-CLI command handler.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the T_FUNCTION token.
     *
     * @return bool
     */
    protected function isCommandHandler(File $phpcsFile, int $functionPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$functionPtr]['code'] !== T_FUNCTION
            || !isset($tokens[$functionPtr]['scope_opener'], $tokens[$functionPtr]['scope_closer'])
        ) {
            return false;
        }

        $name = $phpcsFile->getDeclarationName($functionPtr);
        if ($name === null || $name === '') {
            return false;
        }

        if (in_array(strtolower($name), $this->getIgnoredMethods(), true)) {
            return false;
        }

        if ($this->isMethod($phpcsFile, $functionPtr)) {
            $properties = $phpcsFile->getMethodProperties($functionPtr);
            if ($properties['scope'] !== 'public') {
                return false;
            }

            // Magic methods are never subcommands, except __invoke.
            if (strpos($name, '__') === 0 && strtolower($name) !== '__invoke') {
                return false;
            }
        }

        $usage = $this->scanCliUsage(
            $phpcsFile,
            $tokens[$functionPtr]['scope_opener'],
            $tokens[$functionPtr]['scope_closer']
        );

        // Methods that only register commands are bootstraps, not commands.
        if ($usage['registration'] && !$usage['direct']) {
            return false;
        }

        if ($usage['direct'] || $usage['nested']) {
            return true;
        }

        $assocArgs = $this->getAssocArgsName($phpcsFile, $functionPtr);

        return $assocArgs !== null && in_array($assocArgs, self::ASSOC_ARGS_NAMES, true);
    }

    /**
     * Name of the associative arguments parameter of a command handler.
     *
     * WP-CLI calls handlers as `( $args, $assoc_args )`, so the second
     * parameter holds the flags whatever it is named.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the T_FUNCTION token.
     *
     * @return string|null The variable name including "$", or null.
     */
    protected function getAssocArgsName(File $phpcsFile, int $functionPtr): ?string
    {
        $parameters = $phpcsFile->getMethodParameters($functionPtr);

        if (!isset($parameters[1]['name'])) {
            return null;
        }

        return $parameters[1]['name'];
    }

    /**
     * Determine whether a T_VARIABLE token refers to the WP-CLI assoc args.
     *
     * True for the conventional names, and for the second parameter of the
     * enclosing command handler whatever its name.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $variablePtr Position of the T_VARIABLE token.
     *
     * @return bool
     */
    protected function isAssocArgsVariable(File $phpcsFile, int $variablePtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $name   = $tokens[$variablePtr]['content'];

        if (in_array($name, self::ASSOC_ARGS_NAMES, true)) {
            return true;
        }

        $functionPtr = $this->findEnclosingCallable($phpcsFile, $variablePtr);
        if ($functionPtr === null || $tokens[$functionPtr]['code'] !== T_FUNCTION) {
            return false;
        }

        return $this->getAssocArgsName($phpcsFile, $functionPtr) === $name
            && $this->isCommandHandler($phpcsFile, $functionPtr);
    }

    /**
     * Find the docblock preceding a function declaration.
     *
     * Skips modifiers placed between the docblock and the function keyword.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Position of the T_FUNCTION token.
     *
     * @return int|false Position of the doc comment opener, or false.
     */
    protected function findDocblock(File $phpcsFile, int $stackPtr)
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
    protected function getDocblockContent(File $phpcsFile, int $opener): string
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$opener]['comment_closer'];

        $content = '';
        for ($i = $opener; $i <= $closer; $i++) {
            $content .= $tokens[$i]['content'];
        }

        return $content;
    }

    /**
     * Find the innermost function, closure or arrow function around a token.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Position of the token.
     *
     * @return int|null Position of the T_FUNCTION/T_CLOSURE/T_FN token.
     */
    protected function findEnclosingCallable(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        // Arrow functions are not part of "conditions"; check them first.
        $fn = $phpcsFile->findPrevious(T_FN, $stackPtr);
        while ($fn !== false) {
            if (isset($tokens[$fn]['scope_closer']) && $tokens[$fn]['scope_closer'] >= $stackPtr) {
                return $fn;
            }
            $fn = $phpcsFile->findPrevious(T_FN, ($fn - 1));
        }

        $conditions = array_reverse($tokens[$stackPtr]['conditions'], true);
        foreach ($conditions as $ptr => $code) {
            if ($code === T_FUNCTION || $code === T_CLOSURE) {
                return $ptr;
            }
        }

        return null;
    }

    /**
     * Whether a T_FUNCTION token is a method of a class-like structure.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the T_FUNCTION token.
     *
     * @return bool
     */
    private function isMethod(File $phpcsFile, int $functionPtr): bool
    {
        $tokens     = $phpcsFile->getTokens();
        $conditions = $tokens[$functionPtr]['conditions'];

        if ($conditions === []) {
            return false;
        }

        end($conditions);

        return in_array(current($conditions), self::OO_SCOPES, true);
    }

    /**
     * Classify WP-CLI usage inside a function body.
     *
     * - direct:       WP_CLI usage (other than registration) or an assoc-args
     *                 variable in the body itself
     * - nested:       the same, but only inside nested closures/functions
     * - registration: WP_CLI::add_command()/add_hook() in the body itself
     *
     * @param File $phpcsFile  The file being scanned.
     * @param int  $scopeStart Start of the function body.
     * @param int  $scopeEnd   End of the function body.
     *
     * @return array{direct: bool, nested: bool, registration: bool}
     */
    private function scanCliUsage(File $phpcsFile, int $scopeStart, int $scopeEnd): array
    {
        $tokens = $phpcsFile->getTokens();
        $usage  = ['direct' => false, 'nested' => false, 'registration' => false];
        $nestedUntil = -1;

        for ($i = ($scopeStart + 1); $i < $scopeEnd; $i++) {
            $code = $tokens[$i]['code'];

            if ($i > $nestedUntil
                && in_array($code, self::NESTED_CALLABLES, true)
                && isset($tokens[$i]['scope_closer'])
            ) {
                $nestedUntil = $tokens[$i]['scope_closer'];
                continue;
            }

            $key = ($i <= $nestedUntil) ? 'nested' : 'direct';

            if ($code === T_VARIABLE && in_array($tokens[$i]['content'], self::ASSOC_ARGS_NAMES, true)) {
                $usage[$key] = true;
                continue;
            }

            if ($code !== T_STRING || $tokens[$i]['content'] !== 'WP_CLI') {
                continue;
            }

            $next = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);
            if ($next === false) {
                continue;
            }

            if ($tokens[$next]['code'] === T_NS_SEPARATOR) {
                // Namespaced API, e.g. WP_CLI\Utils\format_items().
                $usage[$key] = true;
                continue;
            }

            if ($tokens[$next]['code'] !== T_DOUBLE_COLON) {
                continue;
            }

            $method = $phpcsFile->findNext(T_WHITESPACE, ($next + 1), null, true);
            $isRegistration = $method !== false
                && $tokens[$method]['code'] === T_STRING
                && in_array(strtolower($tokens[$method]['content']), self::REGISTRATION_METHODS, true);

            if ($isRegistration) {
                if ($key === 'direct') {
                    $usage['registration'] = true;
                }
                continue;
            }

            $usage[$key] = true;
        }

        return $usage;
    }

    /**
     * Normalized list of ignored method names.
     *
     * @return array<string>
     */
    private function getIgnoredMethods(): array
    {
        $ignored = $this->ignoreMethods;

        if (is_string($ignored)) {
            $ignored = explode(',', $ignored);
        }

        return array_values(array_filter(array_map(
            static function ($name): string {
                return strtolower(trim((string) $name));
            },
            (array) $ignored
        )));
    }
}
