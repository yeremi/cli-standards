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
     * Calls that destroy content, used as a behavior signal by the safety
     * sniffs (case-insensitive).
     *
     * Plain names are functions (wp_delete_post). Names starting with "->"
     * are $wpdb methods (->delete). "->query" only counts when its arguments
     * contain a DELETE, TRUNCATE or DROP statement in a string literal.
     * Configurable from a ruleset (type="array").
     *
     * Option, meta and transient deletions are not included by default: they
     * are routinely used for housekeeping (locks, caches) inside commands that
     * are not destructive.
     *
     * @var array<string>|string
     */
    public $destructiveFunctions = [
        'wp_delete_post',
        'wp_delete_attachment',
        'wp_delete_user',
        'wpmu_delete_user',
        'wp_delete_term',
        'wp_delete_comment',
        'wp_delete_nav_menu',
        'wp_delete_site',
        'wpmu_delete_blog',
        '->delete',
        '->query',
    ];

    /**
     * Cache for getRegisteredCommandNames(), keyed by file and token count.
     *
     * @var array<string, array<string, string>>
     */
    private $registeredCommandCache = [];

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
     * Skips modifiers and PHP 8 attributes placed between the docblock and
     * the function keyword.
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

        while ($prev !== false
            && $tokens[$prev]['code'] === T_ATTRIBUTE_END
            && isset($tokens[$prev]['attribute_opener'])
        ) {
            $prev = $phpcsFile->findPrevious($skip, ($tokens[$prev]['attribute_opener'] - 1), null, true);
        }

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
     * Whether docblock text contains a WP-CLI section heading, e.g. "## OPTIONS".
     *
     * The heading must stand on its own line (after the optional leading "*");
     * a mention of the section inside prose does not count. Case-insensitive.
     *
     * @param string $docContent Full docblock text.
     * @param string $section    Section name without "##", e.g. "OPTIONS".
     *
     * @return bool
     */
    protected function docblockHasSection(string $docContent, string $section): bool
    {
        $name = implode('[ \t]+', array_map(
            static function (string $word): string {
                return preg_quote($word, '/');
            },
            preg_split('/\s+/', trim($section))
        ));

        $pattern = '/^[ \t]*(?:\/\*\*)?[ \t]*\*?[ \t]*##[ \t]+' . $name . '[ \t]*\r?$/mi';

        return preg_match($pattern, $docContent) === 1;
    }

    /**
     * Find which keyword, if any, names the command a function implements.
     *
     * The command name is the one users type: the @subcommand annotation,
     * else the name given to WP_CLI::add_command() in the same file, else the
     * function name. It is split into whole words (snake_case, camelCase,
     * kebab-case) and each word is compared with the keywords, so
     * "dropdown_stats" does not match "drop".
     *
     * @param File          $phpcsFile   The file being scanned.
     * @param int           $functionPtr Position of the T_FUNCTION token.
     * @param array<string> $keywords    Lowercase keywords.
     *
     * @return string|null The matched keyword, or null.
     */
    protected function matchCommandKeyword(File $phpcsFile, int $functionPtr, array $keywords): ?string
    {
        $words = $this->splitWords($this->getCommandName($phpcsFile, $functionPtr));

        foreach ($keywords as $keyword) {
            if (in_array($keyword, $words, true)) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * The name users type for the command a function implements.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the T_FUNCTION token.
     *
     * @return string
     */
    protected function getCommandName(File $phpcsFile, int $functionPtr): string
    {
        $subcommand = $this->getSubcommandAnnotation($phpcsFile, $functionPtr);
        if ($subcommand !== null) {
            return $subcommand;
        }

        $declared   = (string) $phpcsFile->getDeclarationName($functionPtr);
        $registered = $this->getRegisteredCommandNames($phpcsFile);

        return $registered[strtolower($declared)] ?? $declared;
    }

    /**
     * Split an identifier or command name into lowercase words.
     *
     * @param string $name e.g. "dropdown_stats", "bulkDelete", "cache-flush".
     *
     * @return array<string>
     */
    protected function splitWords(string $name): array
    {
        $name  = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $name);
        $words = preg_split('/[^A-Za-z0-9]+/', strtolower($name));

        return array_values(array_filter((array) $words, 'strlen'));
    }

    /**
     * Find the first destructive call in a token range.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $start     First token of the range.
     * @param int  $end       Last token of the range.
     *
     * @return string|null A label such as "wp_delete_post()" or "$wpdb->query(DELETE)".
     */
    protected function findDestructiveCall(File $phpcsFile, int $start, int $end): ?string
    {
        $tokens    = $phpcsFile->getTokens();
        $functions = [];
        $methods   = [];

        foreach ($this->normalizeList($this->destructiveFunctions) as $name) {
            if (strpos($name, '->') === 0) {
                $methods[] = substr($name, 2);
            } else {
                $functions[] = $name;
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            if ($tokens[$i]['code'] !== T_STRING) {
                continue;
            }

            $open = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);
            if ($open === false || $tokens[$open]['code'] !== T_OPEN_PARENTHESIS) {
                continue;
            }

            $name = strtolower($tokens[$i]['content']);
            $prev = $phpcsFile->findPrevious(T_WHITESPACE, ($i - 1), null, true);
            $isMethodCall = $prev !== false
                && ($tokens[$prev]['code'] === T_OBJECT_OPERATOR || $tokens[$prev]['code'] === T_NULLSAFE_OBJECT_OPERATOR);

            if (!$isMethodCall) {
                $isDeclarationOrStatic = $prev !== false
                    && in_array($tokens[$prev]['code'], [T_FUNCTION, T_DOUBLE_COLON, T_NEW], true);
                if (!$isDeclarationOrStatic && in_array($name, $functions, true)) {
                    return $tokens[$i]['content'] . '()';
                }
                continue;
            }

            if (!in_array($name, $methods, true) || !$this->isWpdbObject($phpcsFile, $prev)) {
                continue;
            }

            if ($name !== 'query') {
                return '$wpdb->' . $tokens[$i]['content'] . '()';
            }

            $statement = $this->findDestructiveSql($phpcsFile, $open);
            if ($statement !== null) {
                return '$wpdb->query(' . $statement . ')';
            }
        }

        return null;
    }

    /**
     * Normalize a list property that may come from a ruleset as a string.
     *
     * @param array<string>|string $list The configured value.
     *
     * @return array<string> Lowercase, trimmed, non-empty values.
     */
    protected function normalizeList($list): array
    {
        if (is_string($list)) {
            $list = explode(',', $list);
        }

        return array_values(array_filter(array_map(
            static function ($value): string {
                return strtolower(trim((string) $value));
            },
            (array) $list
        )));
    }

    /**
     * Read the @subcommand annotation of a function's docblock.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the T_FUNCTION token.
     *
     * @return string|null
     */
    private function getSubcommandAnnotation(File $phpcsFile, int $functionPtr): ?string
    {
        $docblock = $this->findDocblock($phpcsFile, $functionPtr);
        if ($docblock === false) {
            return null;
        }

        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$docblock]['comment_closer'];

        for ($i = $docblock; $i < $closer; $i++) {
            if ($tokens[$i]['code'] !== T_DOC_COMMENT_TAG || strtolower($tokens[$i]['content']) !== '@subcommand') {
                continue;
            }

            $value = $phpcsFile->findNext(T_DOC_COMMENT_WHITESPACE, ($i + 1), $closer, true);
            if ($value !== false && $tokens[$value]['code'] === T_DOC_COMMENT_STRING) {
                $parts = preg_split('/\s+/', trim($tokens[$value]['content']));

                return $parts[0] ?? null;
            }
        }

        return null;
    }

    /**
     * Map callables registered with WP_CLI::add_command() in this file to
     * the last word of their command name.
     *
     * Handles literal callables only: 'my_function', 'Class::method',
     * [ $object, 'method' ] and array( Class::class, 'method' ).
     *
     * @param File $phpcsFile The file being scanned.
     *
     * @return array<string, string> Lowercase function/method name => command word.
     */
    protected function getRegisteredCommandNames(File $phpcsFile): array
    {
        $key = $phpcsFile->getFilename() . ':' . $phpcsFile->numTokens;
        if (isset($this->registeredCommandCache[$key])) {
            return $this->registeredCommandCache[$key];
        }

        $tokens = $phpcsFile->getTokens();
        $map    = [];

        for ($i = 0; $i < $phpcsFile->numTokens; $i++) {
            if ($tokens[$i]['code'] !== T_STRING || strtolower($tokens[$i]['content']) !== 'add_command') {
                continue;
            }

            $colon = $phpcsFile->findPrevious(T_WHITESPACE, ($i - 1), null, true);
            $class = ($colon !== false) ? $phpcsFile->findPrevious(T_WHITESPACE, ($colon - 1), null, true) : false;
            $open  = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);

            if ($class === false || $tokens[$colon]['code'] !== T_DOUBLE_COLON
                || $tokens[$class]['content'] !== 'WP_CLI'
                || $open === false || !isset($tokens[$open]['parenthesis_closer'])
            ) {
                continue;
            }

            $arguments = $this->splitArguments($phpcsFile, $open);
            if (count($arguments) < 2) {
                continue;
            }

            $command = $this->literalString($phpcsFile, $arguments[0]);
            $handler = $this->callableMethodName($phpcsFile, $arguments[1]);
            if ($command === null || $handler === null) {
                continue;
            }

            $commandWords = preg_split('/\s+/', trim($command));
            $map[strtolower($handler)] = (string) end($commandWords);
        }

        $this->registeredCommandCache = [$key => $map];

        return $map;
    }

    /**
     * Whether the token before "->" is the $wpdb object ($wpdb or ->wpdb).
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $operator  Position of the object operator.
     *
     * @return bool
     */
    private function isWpdbObject(File $phpcsFile, int $operator): bool
    {
        $tokens = $phpcsFile->getTokens();
        $object = $phpcsFile->findPrevious(T_WHITESPACE, ($operator - 1), null, true);

        if ($object === false) {
            return false;
        }

        return ($tokens[$object]['code'] === T_VARIABLE && $tokens[$object]['content'] === '$wpdb')
            || ($tokens[$object]['code'] === T_STRING && $tokens[$object]['content'] === 'wpdb');
    }

    /**
     * Find a destructive SQL keyword in the string literals of a call.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $open      Position of the call's opening parenthesis.
     *
     * @return string|null DELETE, TRUNCATE or DROP.
     */
    private function findDestructiveSql(File $phpcsFile, int $open): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $close  = $tokens[$open]['parenthesis_closer'];
        $string = [T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING, T_HEREDOC, T_NOWDOC];

        for ($i = ($open + 1); $i < $close; $i++) {
            if (in_array($tokens[$i]['code'], $string, true)
                && preg_match('/^\W*(DELETE|TRUNCATE|DROP)\b/i', trim($tokens[$i]['content'], "\"' \t\n\r"), $match)
            ) {
                return strtoupper($match[1]);
            }
        }

        return null;
    }

    /**
     * Split the arguments of a call into token ranges.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $open      Position of the call's opening parenthesis.
     *
     * @return array<int, array{0: int, 1: int}> First and last non-whitespace token of each argument.
     */
    private function splitArguments(File $phpcsFile, int $open): array
    {
        $tokens    = $phpcsFile->getTokens();
        $close     = $tokens[$open]['parenthesis_closer'];
        $arguments = [];
        $start     = $open + 1;

        for ($i = $start; $i <= $close; $i++) {
            if ($i < $close && isset($tokens[$i]['parenthesis_closer']) && $tokens[$i]['code'] === T_OPEN_PARENTHESIS) {
                $i = $tokens[$i]['parenthesis_closer'];
                continue;
            }

            if ($i < $close && isset($tokens[$i]['bracket_closer'])
                && in_array($tokens[$i]['code'], [T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET, T_OPEN_CURLY_BRACKET], true)
            ) {
                $i = $tokens[$i]['bracket_closer'];
                continue;
            }

            if ($i === $close || $tokens[$i]['code'] === T_COMMA) {
                $first = $phpcsFile->findNext(T_WHITESPACE, $start, $i, true);
                $last  = $phpcsFile->findPrevious(T_WHITESPACE, ($i - 1), ($start - 1), true);
                if ($first !== false && $last !== false) {
                    $arguments[] = [$first, $last];
                }
                $start = $i + 1;
            }
        }

        return $arguments;
    }

    /**
     * Value of an argument that is a single string literal.
     *
     * @param File               $phpcsFile The file being scanned.
     * @param array{0: int, 1: int} $range  Argument token range.
     *
     * @return string|null
     */
    private function literalString(File $phpcsFile, array $range): ?string
    {
        $tokens = $phpcsFile->getTokens();

        if ($range[0] !== $range[1] || $tokens[$range[0]]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        return substr($tokens[$range[0]]['content'], 1, -1);
    }

    /**
     * Function or method name of a literal callable argument.
     *
     * @param File               $phpcsFile The file being scanned.
     * @param array{0: int, 1: int} $range  Argument token range.
     *
     * @return string|null
     */
    private function callableMethodName(File $phpcsFile, array $range): ?string
    {
        $tokens = $phpcsFile->getTokens();

        $string = $this->literalString($phpcsFile, $range);
        if ($string !== null) {
            $parts = explode('::', $string);

            return (string) end($parts);
        }

        $first = $tokens[$range[0]]['code'];
        if ($first !== T_OPEN_SHORT_ARRAY && $first !== T_ARRAY) {
            return null;
        }

        // [ $object, 'method' ] / array( Class::class, 'method' ): last string literal.
        $last = $phpcsFile->findPrevious(T_CONSTANT_ENCAPSED_STRING, $range[1], $range[0]);

        return ($last !== false) ? substr($tokens[$last]['content'], 1, -1) : null;
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
