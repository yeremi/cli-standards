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

use CLIStandards\Helpers\AbstractCommandSniff;
use CLIStandards\Helpers\Names;
use PHP_CodeSniffer\Files\File;

class CommandRegistrationSniff extends AbstractCommandSniff
{
    /**
     * Top-level WP-CLI core command groups a plugin must not take over.
     *
     * @var array<string>
     */
    private const CORE_COMMANDS = [
        'cache', 'cap', 'cli', 'comment', 'config', 'core', 'cron', 'db', 'embed',
        'eval', 'eval-file', 'export', 'help', 'i18n', 'import', 'language',
        'maintenance-mode', 'media', 'menu', 'network', 'option', 'package',
        'plugin', 'post', 'post-type', 'rewrite', 'role', 'scaffold',
        'search-replace', 'server', 'shell', 'sidebar', 'site', 'super-admin',
        'taxonomy', 'term', 'theme', 'transient', 'user', 'widget',
    ];

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
        // (indicating namespace + subcommand). A class registered under a
        // single word is a command group: its methods become the subcommands
        // ("wp acme sync"), so the word already is the namespace. It still
        // must not take over a WP-CLI core command group.
        $arguments  = $this->splitArguments($phpcsFile, $openParen);
        $isGroup    = isset($arguments[1]) && $this->isClassCallable($phpcsFile, $arguments[1]);
        $isCoreName = in_array(strtolower($commandName), self::CORE_COMMANDS, true);

        if (strpos($commandName, ' ') === false && (!$isGroup || $isCoreName)) {
            $message = $isGroup
                ? 'WP_CLI::add_command() command group "%s" takes over a WP-CLI core command: use a plugin-specific name (e.g., "myplugin").'
                : 'WP_CLI::add_command() command name "%s" must include a namespace: use "vendor subcommand" format (e.g., "myplugin list" instead of "list").';
            $phpcsFile->addError($message, $firstArg, 'MissingNamespace', [$commandName]);
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
        if ($className === false || !Names::isWpCliClass($tokens[$className])) {
            return false;
        }

        return true;
    }

    /**
     * Whether an add_command() callable argument is a class (command group)
     * rather than a single command.
     *
     * Classes: `Foo::class`, `new Foo()`, a string class name such as
     * 'Acme_Command' or 'Acme\Command', or `__NAMESPACE__ . '\Command'`.
     * Functions ('acme_ping'), 'Class::method' strings, array callables and
     * closures are single commands.
     *
     * @param File               $phpcsFile The file being scanned.
     * @param array{0: int, 1: int} $range  Argument token range.
     *
     * @return bool
     */
    private function isClassCallable(File $phpcsFile, array $range): bool
    {
        $tokens = $phpcsFile->getTokens();
        $first  = $tokens[$range[0]];
        $last   = $tokens[$range[1]];

        if ($first['code'] === T_NEW) {
            return true;
        }

        if ($last['code'] === T_STRING && strtolower($last['content']) === 'class') {
            $colon = $phpcsFile->findPrevious(T_WHITESPACE, ($range[1] - 1), $range[0], true);

            return $colon !== false && $tokens[$colon]['code'] === T_DOUBLE_COLON;
        }

        // String literals, optionally concatenated with __NAMESPACE__.
        $text = '';
        for ($i = $range[0]; $i <= $range[1]; $i++) {
            $code = $tokens[$i]['code'];
            if ($code === T_CONSTANT_ENCAPSED_STRING) {
                $text .= substr($tokens[$i]['content'], 1, -1);
            } elseif ($code === T_NS_C) {
                $text .= 'Namespace\\';
            } elseif ($code !== T_WHITESPACE && $code !== T_STRING_CONCAT) {
                return false;
            }
        }

        if ($text === '' || strpos($text, '::') !== false) {
            return false;
        }

        // Class names are namespaced or start with an uppercase letter;
        // function names are conventionally lowercase.
        return strpos($text, '\\') !== false || ctype_upper(ltrim($text, '\\')[0] ?? '');
    }
}
