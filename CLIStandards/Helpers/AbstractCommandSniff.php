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
     * Class names (case-insensitive; short or fully qualified) whose methods
     * are never treated as commands. Configurable from a ruleset.
     *
     * @var array<string>|string
     */
    public $ignoreClasses = [];

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
     * Cache for getRegistrations(), keyed by file and token count.
     *
     * @var array<string, array>
     */
    private $registrationCache = [];

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
     * Determine whether a function token declares a WP-CLI command handler.
     *
     * T_FUNCTION: see the class description. T_CLOSURE/T_FN: only when the
     * closure is passed directly to WP_CLI::add_command().
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the T_FUNCTION/T_CLOSURE/T_FN token.
     *
     * @return bool
     */
    protected function isCommandHandler(File $phpcsFile, int $functionPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $code   = $tokens[$functionPtr]['code'];

        if (!isset($tokens[$functionPtr]['scope_opener'], $tokens[$functionPtr]['scope_closer'])) {
            return false;
        }

        if ($code === T_CLOSURE || $code === T_FN) {
            return $this->findRegistration($phpcsFile, $functionPtr) !== null;
        }

        if ($code !== T_FUNCTION) {
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

            // Helper classes (fetchers, utilities, ...) are not command classes.
            $conditions = $tokens[$functionPtr]['conditions'];
            end($conditions);
            if (!$this->isCommandClass($phpcsFile, (int) key($conditions))) {
                return false;
            }
        }

        $usage = $this->scanCliUsage(
            $phpcsFile,
            $tokens[$functionPtr]['scope_opener'],
            $tokens[$functionPtr]['scope_closer']
        );

        // Methods that register commands are bootstraps, not commands, when they
        // do nothing else with WP-CLI or do not take the assoc args: command
        // handlers receive ( $args, $assoc_args ), registrars usually nothing.
        if ($usage['registration']
            && (!$usage['direct'] || $this->getAssocArgsName($phpcsFile, $functionPtr) === null)
        ) {
            return false;
        }

        // A registrar can also delegate add_command() to a same-class helper.
        if (!$usage['registration']
            && $this->getAssocArgsName($phpcsFile, $functionPtr) === null
            && $this->registersThroughHelpers($phpcsFile, $functionPtr)
        ) {
            return false;
        }

        if ($usage['direct'] || $usage['nested']) {
            return true;
        }

        $assocArgs = $this->getAssocArgsName($phpcsFile, $functionPtr);

        return $assocArgs !== null && in_array($assocArgs, self::ASSOC_ARGS_NAMES, true);
    }

    /**
     * Whether a same-class method the function calls (up to two levels)
     * registers commands with WP_CLI::add_command() or add_hook().
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the T_FUNCTION token.
     *
     * @return bool
     */
    private function registersThroughHelpers(File $phpcsFile, int $functionPtr): bool
    {
        foreach ($this->getReachableBodies($phpcsFile, $functionPtr) as $index => [$start, $end]) {
            if ($index > 0 && $this->scanCliUsage($phpcsFile, $start, $end)['registration']) {
                return true;
            }
        }

        return false;
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
        if ($functionPtr === null) {
            return false;
        }

        return $this->getAssocArgsName($phpcsFile, $functionPtr) === $name
            && $this->isCommandHandler($phpcsFile, $functionPtr);
    }

    /**
     * Find the docblock preceding a function declaration.
     *
     * Skips modifiers and PHP 8 attributes placed between the docblock and
     * the function keyword. For a closure registered with
     * WP_CLI::add_command(), the docblock is the one before that statement.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Position of the T_FUNCTION/T_CLOSURE/T_FN token.
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

        if ($tokens[$stackPtr]['code'] === T_CLOSURE || $tokens[$stackPtr]['code'] === T_FN) {
            $registration = $this->findRegistration($phpcsFile, $stackPtr);
            if ($registration === null) {
                return false;
            }

            $stackPtr = $registration['statement'];
            $skip[]   = T_NS_SEPARATOR;
        }

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

        $registration = $this->findRegistration($phpcsFile, $functionPtr);
        if ($registration !== null) {
            return $registration['word'];
        }

        return (string) $this->getFunctionName($phpcsFile, $functionPtr);
    }

    /**
     * Human-readable name of a command for messages.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the T_FUNCTION/T_CLOSURE/T_FN token.
     *
     * @return string The function name, or the registered command for closures.
     */
    protected function getCommandLabel(File $phpcsFile, int $functionPtr): string
    {
        $name = $this->getFunctionName($phpcsFile, $functionPtr);
        if ($name !== null) {
            return $name;
        }

        $registration = $this->findRegistration($phpcsFile, $functionPtr);

        return ($registration !== null) ? $registration['command'] : 'closure';
    }

    /**
     * Token to attach a command report to: the function name, or the
     * closure keyword.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the T_FUNCTION/T_CLOSURE/T_FN token.
     *
     * @return int
     */
    protected function getReportPointer(File $phpcsFile, int $functionPtr): int
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$functionPtr]['code'] === T_FUNCTION) {
            $name = $phpcsFile->findNext([T_WHITESPACE, T_BITWISE_AND], ($functionPtr + 1), null, true);
            if ($name !== false && $tokens[$name]['code'] === T_STRING) {
                return $name;
            }
        }

        return $functionPtr;
    }

    /**
     * Declared name of a named function, or null for closures.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the function token.
     *
     * @return string|null
     */
    protected function getFunctionName(File $phpcsFile, int $functionPtr): ?string
    {
        if ($phpcsFile->getTokens()[$functionPtr]['code'] !== T_FUNCTION) {
            return null;
        }

        return $phpcsFile->getDeclarationName($functionPtr);
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
        $name  = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $name); // CLIRunner -> CLI Runner
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

        $nameTokens = Names::nameTokens();
        for ($i = $start; $i <= $end; $i++) {
            if (!isset($nameTokens[$tokens[$i]['code']])) {
                continue;
            }

            $open = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);
            if ($open === false || $tokens[$open]['code'] !== T_OPEN_PARENTHESIS) {
                continue;
            }

            $name = strtolower((string) Names::lastSegment($tokens[$i]));
            $prev = $phpcsFile->findPrevious(T_WHITESPACE, ($i - 1), null, true);
            $isMethodCall = $prev !== false
                && ($tokens[$prev]['code'] === T_OBJECT_OPERATOR || $tokens[$prev]['code'] === T_NULLSAFE_OBJECT_OPERATOR);

            if (!$isMethodCall) {
                $isDeclarationOrStatic = $prev !== false
                    && in_array($tokens[$prev]['code'], [T_FUNCTION, T_DOUBLE_COLON, T_NEW], true);
                if (!$isDeclarationOrStatic && in_array($name, $functions, true)) {
                    return Names::lastSegment($tokens[$i]) . '()';
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
     * The WP_CLI::add_command() registration of a command handler, if it is
     * in the same file.
     *
     * Named functions and methods match by name ('my_function',
     * 'Class::method', [ $object, 'method' ], array( Class::class, 'method' )).
     * Closures and arrow functions match when passed directly.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the T_FUNCTION/T_CLOSURE/T_FN token.
     *
     * @return array{command: string, word: string, statement: int, synopsis: bool, synopsisRange: array{0: int, 1: int}|null, shortdesc: bool, longdesc: string|null}|null
     */
    protected function findRegistration(File $phpcsFile, int $functionPtr): ?array
    {
        $registrations = $this->getRegistrations($phpcsFile);
        $code          = $phpcsFile->getTokens()[$functionPtr]['code'];

        if ($code === T_CLOSURE || $code === T_FN) {
            return $registrations['closures'][$functionPtr] ?? null;
        }

        $name = $this->getFunctionName($phpcsFile, $functionPtr);

        return ($name !== null) ? ($registrations['names'][strtolower($name)] ?? null) : null;
    }

    /**
     * Parse every WP_CLI::add_command() call with a literal command name.
     *
     * @param File $phpcsFile The file being scanned.
     *
     * @return array{names: array<string, array>, closures: array<int, array>}
     */
    private function getRegistrations(File $phpcsFile): array
    {
        $key = $phpcsFile->getFilename() . ':' . $phpcsFile->numTokens;
        if (isset($this->registrationCache[$key])) {
            return $this->registrationCache[$key];
        }

        $tokens = $phpcsFile->getTokens();
        $result = ['names' => [], 'closures' => []];

        for ($i = 0; $i < $phpcsFile->numTokens; $i++) {
            if ($tokens[$i]['code'] !== T_STRING || strtolower($tokens[$i]['content']) !== 'add_command') {
                continue;
            }

            $colon = $phpcsFile->findPrevious(T_WHITESPACE, ($i - 1), null, true);
            $class = ($colon !== false) ? $phpcsFile->findPrevious(T_WHITESPACE, ($colon - 1), null, true) : false;
            $open  = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);

            if ($class === false || $tokens[$colon]['code'] !== T_DOUBLE_COLON
                || !Names::isWpCliClass($tokens[$class])
                || $open === false || !isset($tokens[$open]['parenthesis_closer'])
            ) {
                continue;
            }

            $arguments = $this->splitArguments($phpcsFile, $open);
            $command   = isset($arguments[0]) ? $this->literalString($phpcsFile, $arguments[0]) : null;
            if ($command === null || !isset($arguments[1])) {
                continue;
            }

            $words        = preg_split('/\s+/', trim($command));
            $registration = [
                'command'       => $command,
                'word'          => (string) end($words),
                'statement'     => $class,
                'synopsis'      => false,
                'synopsisRange' => null,
                'shortdesc'     => false,
                'longdesc'      => null,
            ];

            if (isset($arguments[2])) {
                $registration = array_merge($registration, $this->parseRegistrationArgs($phpcsFile, $arguments[2]));
            }

            $handler = $arguments[1];
            $first   = $handler[0];
            if ($tokens[$first]['code'] === T_STATIC) {
                $first = $phpcsFile->findNext(T_WHITESPACE, ($first + 1), null, true);
            }

            if ($first !== false && ($tokens[$first]['code'] === T_CLOSURE || $tokens[$first]['code'] === T_FN)) {
                $result['closures'][$first] = $registration;
                continue;
            }

            $name = $this->callableMethodName($phpcsFile, $handler);
            if ($name !== null) {
                $result['names'][strtolower($name)] = $registration;
            }
        }

        $this->registrationCache = [$key => $result];

        return $result;
    }

    /**
     * Read shortdesc, longdesc and synopsis from add_command()'s $args array.
     *
     * @param File               $phpcsFile The file being scanned.
     * @param array{0: int, 1: int} $range  Argument token range.
     *
     * @return array{synopsis?: bool, synopsisRange?: array{0: int, 1: int}, shortdesc?: bool, longdesc?: string}
     */
    private function parseRegistrationArgs(File $phpcsFile, array $range): array
    {
        $tokens = $phpcsFile->getTokens();
        $first  = $range[0];

        if ($tokens[$first]['code'] === T_OPEN_SHORT_ARRAY && isset($tokens[$first]['bracket_closer'])) {
            $open  = $first;
            $close = $tokens[$first]['bracket_closer'];
        } elseif ($tokens[$first]['code'] === T_ARRAY && isset($tokens[$first]['parenthesis_opener'])) {
            $open  = $tokens[$first]['parenthesis_opener'];
            $close = $tokens[$open]['parenthesis_closer'];
        } else {
            return [];
        }

        $found = [];
        foreach ($this->splitList($phpcsFile, $open, $close) as [$start, $end]) {
            $arrow = $phpcsFile->findNext(T_DOUBLE_ARROW, $start, ($end + 1));
            if ($tokens[$start]['code'] !== T_CONSTANT_ENCAPSED_STRING || $arrow === false) {
                continue;
            }

            $key = strtolower(substr($tokens[$start]['content'], 1, -1));
            if ($key === 'synopsis') {
                $found['synopsis'] = true;
                $value             = $phpcsFile->findNext(T_WHITESPACE, ($arrow + 1), ($end + 1), true);
                if ($value !== false) {
                    $found['synopsisRange'] = [$value, $end];
                }
            } elseif ($key === 'shortdesc') {
                $found['shortdesc'] = true;
            } elseif ($key === 'longdesc') {
                $found['longdesc'] = $this->stringLiteralText($phpcsFile, ($arrow + 1), $end);
            }
        }

        return $found;
    }

    /**
     * Concatenated text of the string literals in a token range, with "\n"
     * escapes of double-quoted strings turned into newlines.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $start     First token.
     * @param int  $end       Last token.
     *
     * @return string
     */
    private function stringLiteralText(File $phpcsFile, int $start, int $end): string
    {
        $tokens = $phpcsFile->getTokens();
        $text   = '';
        $quoted = [T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING];

        for ($i = $start; $i <= $end; $i++) {
            $code = $tokens[$i]['code'];

            if ($code === T_HEREDOC || $code === T_NOWDOC) {
                $text .= $tokens[$i]['content'];
                continue;
            }

            if (!in_array($code, $quoted, true)) {
                continue;
            }

            // A multi-line string literal is split into one token per line:
            // join the run before removing the quotes.
            $raw = '';
            while ($i <= $end && $tokens[$i]['code'] === $code) {
                $raw .= $tokens[$i]['content'];
                $i++;
            }
            $i--;

            $quote = $raw[0];
            $body  = substr($raw, 1, -1);
            $text .= ($quote === '"') ? str_replace('\\n', "\n", $body) : $body;
        }

        return $text;
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
    protected function splitArguments(File $phpcsFile, int $open): array
    {
        return $this->splitList($phpcsFile, $open, $phpcsFile->getTokens()[$open]['parenthesis_closer']);
    }

    /**
     * Split the top-level, comma-separated items between two tokens.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $open      Opening parenthesis or bracket.
     * @param int  $close     Matching closer.
     *
     * @return array<int, array{0: int, 1: int}> First and last non-whitespace token of each item.
     */
    protected function splitList(File $phpcsFile, int $open, int $close): array
    {
        $tokens    = $phpcsFile->getTokens();
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
    protected function literalString(File $phpcsFile, array $range): ?string
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
     * Bodies a command reaches: its own, plus same-class methods it calls.
     *
     * Follows $this->method(), self::method() and static::method() calls to
     * methods declared in the same class, breadth-first, up to $maxDepth
     * levels, visiting each method once (recursion-safe). Code in other
     * classes cannot be followed reliably; see getDelegation().
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the command's T_FUNCTION token.
     * @param int  $maxDepth    How many call levels to follow.
     *
     * @return array<int, array{0: int, 1: int}> Scope opener and closer of each body.
     */
    protected function getReachableBodies(File $phpcsFile, int $functionPtr, int $maxDepth = 2): array
    {
        $tokens = $phpcsFile->getTokens();
        if (!isset($tokens[$functionPtr]['scope_opener'], $tokens[$functionPtr]['scope_closer'])) {
            return [];
        }

        $methods = $this->getSiblingMethods($phpcsFile, $functionPtr);
        $bodies  = [];
        $visited = [$functionPtr => true];
        $queue   = [[$functionPtr, 0]];

        while ($queue !== []) {
            [$ptr, $depth] = array_shift($queue);
            $start = $tokens[$ptr]['scope_opener'];
            $end   = $tokens[$ptr]['scope_closer'];
            $bodies[] = [$start, $end];

            if ($depth >= $maxDepth || $methods === []) {
                continue;
            }

            foreach ($this->findSameClassCalls($phpcsFile, $start, $end) as $name) {
                $target = $methods[$name] ?? null;
                if ($target !== null && !isset($visited[$target])) {
                    $visited[$target] = true;
                    $queue[] = [$target, $depth + 1];
                }
            }
        }

        return $bodies;
    }

    /**
     * Whether a command reads its positional or associative arguments.
     *
     * Looks for the handler's first parameter ($args) and the assoc args
     * (see isAssocArgsVariable()) in the command body and in the same-class
     * methods it calls. A handler without parameters reads nothing.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the T_FUNCTION/T_CLOSURE/T_FN token.
     *
     * @return bool
     */
    protected function readsArguments(File $phpcsFile, int $functionPtr): bool
    {
        $tokens     = $phpcsFile->getTokens();
        $parameters = $phpcsFile->getMethodParameters($functionPtr);
        $positional = $parameters[0]['name'] ?? null;

        foreach ($this->getReachableBodies($phpcsFile, $functionPtr) as $index => [$start, $end]) {
            for ($i = ($start + 1); $i < $end; $i++) {
                if ($tokens[$i]['code'] !== T_VARIABLE) {
                    continue;
                }

                // $args only means the positional arguments in the command itself.
                if ($index === 0 && $positional !== null && $tokens[$i]['content'] === $positional) {
                    return true;
                }

                if ($this->isAssocArgsVariable($phpcsFile, $i)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Read a delegation declaration from the command's docblock.
     *
     *     @cli-standards confirm-delegated Purger::purge() calls WP_CLI::confirm()
     *
     * @param File   $phpcsFile   The file being scanned.
     * @param int    $functionPtr Position of the T_FUNCTION token.
     * @param string $kind        e.g. "confirm-delegated".
     *
     * @return string|null The reason (empty string when missing), or null when
     *                     the tag is absent.
     */
    protected function getDelegation(File $phpcsFile, int $functionPtr, string $kind): ?string
    {
        $docblock = $this->findDocblock($phpcsFile, $functionPtr);
        if ($docblock === false) {
            return null;
        }

        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$docblock]['comment_closer'];

        for ($i = $docblock; $i < $closer; $i++) {
            if ($tokens[$i]['code'] !== T_DOC_COMMENT_TAG || strtolower($tokens[$i]['content']) !== '@cli-standards') {
                continue;
            }

            $value = $phpcsFile->findNext(T_DOC_COMMENT_WHITESPACE, ($i + 1), $closer, true);
            if ($value === false || $tokens[$value]['code'] !== T_DOC_COMMENT_STRING) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($tokens[$value]['content']), 2);
            if (strtolower($parts[0]) === strtolower($kind)) {
                return trim($parts[1] ?? '');
            }
        }

        return null;
    }

    /**
     * Methods declared in the same class as a method, by lowercase name.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the T_FUNCTION token.
     *
     * @return array<string, int> Name => T_FUNCTION position (methods with a body only).
     */
    private function getSiblingMethods(File $phpcsFile, int $functionPtr): array
    {
        if (!$this->isMethod($phpcsFile, $functionPtr)) {
            return [];
        }

        $tokens     = $phpcsFile->getTokens();
        $conditions = $tokens[$functionPtr]['conditions'];
        end($conditions);
        $classPtr = key($conditions);

        if (!isset($tokens[$classPtr]['scope_opener'], $tokens[$classPtr]['scope_closer'])) {
            return [];
        }

        $methods = [];
        $i       = $tokens[$classPtr]['scope_opener'];
        $end     = $tokens[$classPtr]['scope_closer'];

        while (($i = $phpcsFile->findNext(T_FUNCTION, ($i + 1), $end)) !== false) {
            $owner = $tokens[$i]['conditions'];
            end($owner);
            if (key($owner) === $classPtr && isset($tokens[$i]['scope_closer'])) {
                $name = $phpcsFile->getDeclarationName($i);
                if ($name !== null) {
                    $methods[strtolower($name)] = $i;
                }
            }
        }

        return $methods;
    }

    /**
     * Names of methods called on the current class in a token range.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $start     First token.
     * @param int  $end       Last token.
     *
     * @return array<string> Lowercase method names.
     */
    private function findSameClassCalls(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $names  = [];

        for ($i = $start; $i < $end; $i++) {
            $isThis = $tokens[$i]['code'] === T_VARIABLE && $tokens[$i]['content'] === '$this';
            $isSelf = $tokens[$i]['code'] === T_SELF || $tokens[$i]['code'] === T_STATIC;
            if (!$isThis && !$isSelf) {
                continue;
            }

            $operator = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), $end, true);
            $expected = $isThis ? [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR] : [T_DOUBLE_COLON];
            if ($operator === false || !in_array($tokens[$operator]['code'], $expected, true)) {
                continue;
            }

            $name  = $phpcsFile->findNext(T_WHITESPACE, ($operator + 1), $end, true);
            $paren = ($name !== false) ? $phpcsFile->findNext(T_WHITESPACE, ($name + 1), $end, true) : false;
            if ($name !== false && $tokens[$name]['code'] === T_STRING
                && $paren !== false && $tokens[$paren]['code'] === T_OPEN_PARENTHESIS
            ) {
                $names[strtolower($tokens[$name]['content'])] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * Whether a class-like scope holds WP-CLI commands.
     *
     * Not every command class extends WP_CLI_Command (some implement their
     * own interface and are registered from another file), so a class counts
     * when any of these holds:
     * - it extends a class whose name contains "Command";
     * - it implements an interface whose name contains "Command";
     * - its own name has "Command(s)" or "CLI" as a word (CLIRunner, Acme_CLI);
     * - its file registers commands with WP_CLI::add_command();
     * - it is an anonymous class.
     * A class is never a command class when listed in ignoreClasses or
     * tagged "@cli-standards not-a-command" in its docblock.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $classPtr  Position of the class-like token.
     *
     * @return bool
     */
    protected function isCommandClass(File $phpcsFile, int $classPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $code   = $tokens[$classPtr]['code'];

        if ($code === T_ANON_CLASS) {
            return true;
        }

        $name = (string) $phpcsFile->getDeclarationName($classPtr);

        if ($this->isIgnoredClass($phpcsFile, $classPtr, $name) || $this->isTaggedNotACommand($phpcsFile, $classPtr)) {
            return false;
        }

        $words = $this->splitWords($name);
        if (array_intersect($words, ['command', 'commands', 'cli']) !== []) {
            return true;
        }

        if ($code === T_CLASS) {
            $parent = $phpcsFile->findExtendedClassName($classPtr);
            if ($parent !== false && stripos($parent, 'command') !== false) {
                return true;
            }
        }

        if ($code === T_CLASS || $code === T_ANON_CLASS) {
            foreach ((array) $phpcsFile->findImplementedInterfaceNames($classPtr) as $interface) {
                if (stripos($interface, 'command') !== false) {
                    return true;
                }
            }
        }

        return $this->fileRegistersCommands($phpcsFile);
    }

    /**
     * Whether the file calls WP_CLI::add_command() at all (also with
     * non-literal names, e.g. in a loop).
     *
     * @param File $phpcsFile The file being scanned.
     *
     * @return bool
     */
    private function fileRegistersCommands(File $phpcsFile): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = 0; $i < $phpcsFile->numTokens; $i++) {
            if ($tokens[$i]['code'] !== T_STRING || strtolower($tokens[$i]['content']) !== 'add_command') {
                continue;
            }

            $colon = $phpcsFile->findPrevious(T_WHITESPACE, ($i - 1), null, true);
            $class = ($colon !== false) ? $phpcsFile->findPrevious(T_WHITESPACE, ($colon - 1), null, true) : false;
            if ($class !== false && $tokens[$colon]['code'] === T_DOUBLE_COLON && Names::isWpCliClass($tokens[$class])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a class is listed in the ignoreClasses property.
     *
     * @param File   $phpcsFile The file being scanned.
     * @param int    $classPtr  Position of the class-like token.
     * @param string $name      Short class name.
     *
     * @return bool
     */
    private function isIgnoredClass(File $phpcsFile, int $classPtr, string $name): bool
    {
        $ignored = $this->normalizeList($this->ignoreClasses);
        if ($ignored === [] || $name === '') {
            return false;
        }

        $candidates = [strtolower($name)];

        $namespace = $phpcsFile->findPrevious(T_NAMESPACE, $classPtr);
        if ($namespace !== false) {
            $end   = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], ($namespace + 1));
            $parts = '';
            for ($i = ($namespace + 1); $end !== false && $i < $end; $i++) {
                $parts .= trim($phpcsFile->getTokens()[$i]['content']);
            }
            if ($parts !== '') {
                $candidates[] = strtolower($parts . '\\' . $name);
            }
        }

        foreach ($ignored as $entry) {
            if (in_array(ltrim($entry, '\\'), $candidates, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a class docblock carries "@cli-standards not-a-command".
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $classPtr  Position of the class-like token.
     *
     * @return bool
     */
    private function isTaggedNotACommand(File $phpcsFile, int $classPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $skip   = [T_WHITESPACE, T_ABSTRACT, T_FINAL, T_READONLY];

        $prev = $phpcsFile->findPrevious($skip, ($classPtr - 1), null, true);
        while ($prev !== false && $tokens[$prev]['code'] === T_ATTRIBUTE_END && isset($tokens[$prev]['attribute_opener'])) {
            $prev = $phpcsFile->findPrevious($skip, ($tokens[$prev]['attribute_opener'] - 1), null, true);
        }

        if ($prev === false || $tokens[$prev]['code'] !== T_DOC_COMMENT_CLOSE_TAG) {
            return false;
        }

        for ($i = $tokens[$prev]['comment_opener']; $i < $prev; $i++) {
            if ($tokens[$i]['code'] === T_DOC_COMMENT_TAG && strtolower($tokens[$i]['content']) === '@cli-standards') {
                $value = $phpcsFile->findNext(T_DOC_COMMENT_WHITESPACE, ($i + 1), $prev, true);
                if ($value !== false && $tokens[$value]['code'] === T_DOC_COMMENT_STRING
                    && strtolower(strtok(trim($tokens[$value]['content']), " \t")) === 'not-a-command'
                ) {
                    return true;
                }
            }
        }

        return false;
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

            if (Names::isWpCliNamespaced($phpcsFile, $i)) {
                // Namespaced API, e.g. WP_CLI\Utils\format_items().
                $usage[$key] = true;
                continue;
            }

            if (!Names::isWpCliClass($tokens[$i])) {
                continue;
            }

            $next = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);
            if ($next === false) {
                continue;
            }

            if ($tokens[$next]['code'] !== T_DOUBLE_COLON) {
                continue;
            }

            $method = $phpcsFile->findNext(T_WHITESPACE, ($next + 1), null, true);

            // WP_CLI::class is a class-name constant, not a WP-CLI call.
            if ($method !== false && strtolower($tokens[$method]['content']) === 'class') {
                continue;
            }

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
