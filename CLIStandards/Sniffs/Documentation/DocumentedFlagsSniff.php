<?php
/**
 * DocumentedFlagsSniff
 *
 * @package CLIStandards
 *
 * Detects flags a WP-CLI command reads but does not document, and flags it
 * documents but never reads.
 *
 * WP-CLI builds `wp help` and validates the command line from the synopsis
 * (the `## OPTIONS` section, or the `synopsis` passed to add_command()).
 * A flag read in code but missing from the synopsis is invisible to users,
 * and rejected as unknown once the synopsis exists.
 *
 * Rule:    Every flag a command reads must be documented (G1)
 * Level:   SHOULD — advisory
 * Fix:     Add `[--name=<value>]` with a description to ## OPTIONS, or remove the stale entry
 * @since   1.2.0
 */

namespace CLIStandards\Sniffs\Documentation;

use CLIStandards\Helpers\AbstractCommandSniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

class DocumentedFlagsSniff extends AbstractCommandSniff
{
    /**
     * WP-CLI global parameters: accepted by every command, never documented.
     *
     * @var array<string>
     */
    private const GLOBAL_PARAMETERS = [
        'url',
        'path',
        'user',
        'skip-plugins',
        'skip-themes',
        'skip-packages',
        'require',
        'exec',
        'context',
        'debug',
        'quiet',
        'color',
        'no-color',
        'prompt',
        'ssh',
        'http',
    ];

    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return array<int>
     */
    public function register(): array
    {
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
        if (!$this->isCommandHandler($phpcsFile, $stackPtr)) {
            return;
        }

        // Without a synopsis there is nothing to compare against;
        // DocblockOptions reports the missing ## OPTIONS section.
        $documented = $this->getDocumentedFlags($phpcsFile, $stackPtr);
        if ($documented === null) {
            return;
        }

        $usage = $this->getUsedFlags($phpcsFile, $stackPtr);

        if (!$documented['generic']) {
            foreach ($usage['flags'] as $flag => $pointer) {
                if (!isset($documented['flags'][$flag]) && !in_array($flag, self::GLOBAL_PARAMETERS, true)) {
                    $phpcsFile->addWarning(
                        'Flag "--%s" is read by the command but not documented in its synopsis (## OPTIONS). Undocumented flags are missing from "wp help" and rejected by WP-CLI as unknown.',
                        $pointer,
                        'UndocumentedFlag',
                        [$flag]
                    );
                }
            }
        }

        // The assoc args leave the handler (passed to a helper, a formatter,
        // wp_parse_args(), ...): reads cannot be followed, so do not guess.
        if ($usage['escapes']) {
            return;
        }

        foreach (array_keys($documented['flags']) as $flag) {
            if (isset($usage['flags'][$flag]) || in_array($flag, self::GLOBAL_PARAMETERS, true)) {
                continue;
            }

            $phpcsFile->addWarning(
                'Flag "--%s" is documented for command "%s" but never read by it.',
                $this->getReportPointer($phpcsFile, $stackPtr),
                'UnusedFlag',
                [$flag, $this->getCommandLabel($phpcsFile, $stackPtr)]
            );
        }
    }

    /**
     * Flags documented for a command, from the place WP-CLI reads them.
     *
     * The `synopsis` argument of add_command() wins over the docblock, as in
     * WP-CLI; otherwise the docblock's ## OPTIONS section, then an
     * ## OPTIONS heading in the `longdesc` argument.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the handler token.
     *
     * @return array{flags: array<string, true>, generic: bool}|null Null when
     *         no synopsis can be read.
     */
    private function getDocumentedFlags(File $phpcsFile, int $functionPtr): ?array
    {
        $registration = $this->findRegistration($phpcsFile, $functionPtr);

        if ($registration !== null && $registration['synopsis']) {
            return ($registration['synopsisRange'] !== null)
                ? $this->parseSynopsisArgument($phpcsFile, $registration['synopsisRange'])
                : null;
        }

        $docblock = $this->findDocblock($phpcsFile, $functionPtr);
        if ($docblock !== false) {
            $flags = $this->parseOptionsSection($this->getDocblockContent($phpcsFile, $docblock));
            if ($flags !== null) {
                return $flags;
            }
        }

        if ($registration !== null && $registration['longdesc'] !== null) {
            return $this->parseOptionsSection($registration['longdesc']);
        }

        return null;
    }

    /**
     * Flags listed in the ## OPTIONS section of docblock or longdesc text.
     *
     * @param string $text Docblock or longdesc text.
     *
     * @return array{flags: array<string, true>, generic: bool}|null Null when
     *         there is no ## OPTIONS section.
     */
    private function parseOptionsSection(string $text): ?array
    {
        $result  = ['flags' => [], 'generic' => false];
        $inside  = false;
        $section = false;

        foreach (preg_split('/\r?\n/', $text) as $line) {
            $line = preg_replace('/^[ \t]*(?:\/\*\*|\*\/|\*)?[ \t]*/', '', $line);

            if (preg_match('/^##[ \t]+(.+?)[ \t]*$/', $line, $heading)) {
                $inside  = strtoupper($heading[1]) === 'OPTIONS';
                $section = $section || $inside;
                continue;
            }

            if ($inside) {
                $this->addSynopsisToken($result, $line);
            }
        }

        return $section ? $result : null;
    }

    /**
     * Flags of the `synopsis` argument of add_command(): a literal array of
     * [ 'type' => ..., 'name' => ... ] entries, or a synopsis string.
     *
     * @param File                  $phpcsFile The file being scanned.
     * @param array{0: int, 1: int} $range     Token range of the value.
     *
     * @return array{flags: array<string, true>, generic: bool}|null Null when
     *         the synopsis is built at runtime.
     */
    private function parseSynopsisArgument(File $phpcsFile, array $range): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $result = ['flags' => [], 'generic' => false];

        $string = $this->literalString($phpcsFile, $range);
        if ($string !== null) {
            foreach (preg_split('/\s+/', $string) as $part) {
                $this->addSynopsisToken($result, $part);
            }

            return $result;
        }

        $list = $this->arrayItems($phpcsFile, $range[0]);
        if ($list === null || $tokens[$range[1]]['code'] !== $this->closerCode($tokens[$range[0]]['code'])) {
            return null;
        }

        foreach ($list as $entry) {
            $fields = $this->arrayItems($phpcsFile, $entry[0]);
            if ($fields === null) {
                return null;
            }

            $type = null;
            $name = null;
            foreach ($fields as [$start, $end]) {
                $arrow = $phpcsFile->findNext(T_DOUBLE_ARROW, $start, ($end + 1));
                if ($arrow === false) {
                    continue;
                }

                $key   = $this->literalString($phpcsFile, [$start, $phpcsFile->findPrevious(T_WHITESPACE, ($arrow - 1), null, true)]);
                $value = $this->literalString($phpcsFile, [$phpcsFile->findNext(T_WHITESPACE, ($arrow + 1), null, true), $end]);

                if ($key === 'type') {
                    $type = $value;
                } elseif ($key === 'name') {
                    $name = $value;
                }
            }

            if ($type === 'generic') {
                $result['generic'] = true;
            } elseif ($type === 'assoc' || $type === 'flag') {
                if ($name === null) {
                    return null;
                }
                $result['flags'][$name] = true;
            } elseif ($type === null) {
                return null;
            }
        }

        return $result;
    }

    /**
     * Record the flag a synopsis token or ## OPTIONS line starts with.
     *
     * Accepts `--name`, `[--name]`, `--name=<value>`, `[--name[=<value>]]`;
     * `[--<field>=<value>]` accepts any flag.
     *
     * @param array{flags: array<string, true>, generic: bool} $result Accumulator.
     * @param string                                           $text   Token or line.
     *
     * @return void
     */
    private function addSynopsisToken(array &$result, string $text): void
    {
        if (!preg_match('/^\[?--(<[^>]*>|[A-Za-z0-9][\w-]*)/', $text, $match)) {
            return;
        }

        if ($match[1][0] === '<') {
            $result['generic'] = true;
            return;
        }

        $result['flags'][$match[1]] = true;
    }

    /**
     * Flags the handler reads from its assoc args, and whether the assoc args
     * are used in any other way.
     *
     * Reads: `$assoc_args['name']` (also inside isset()/empty()/??),
     * `get_flag_value( $assoc_args, 'name' )`,
     * `array_key_exists( 'name', $assoc_args )`, and `yes` when
     * `WP_CLI::confirm( $message, $assoc_args )` is called.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $functionPtr Position of the handler token.
     *
     * @return array{flags: array<string, int>, escapes: bool} Flag => first pointer.
     */
    private function getUsedFlags(File $phpcsFile, int $functionPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $result = ['flags' => [], 'escapes' => false];
        $name   = $this->getAssocArgsName($phpcsFile, $functionPtr);

        if ($name === null || !isset($tokens[$functionPtr]['scope_opener'], $tokens[$functionPtr]['scope_closer'])) {
            return $result;
        }

        $end = $tokens[$functionPtr]['scope_closer'];
        for ($i = ($tokens[$functionPtr]['scope_opener'] + 1); $i < $end; $i++) {
            if ($tokens[$i]['code'] !== T_VARIABLE || $tokens[$i]['content'] !== $name) {
                continue;
            }

            $read = $this->readFlag($phpcsFile, $i);
            if ($read === null) {
                $result['escapes'] = true;
                continue;
            }

            if ($read[0] !== '' && !isset($result['flags'][$read[0]])) {
                $result['flags'][$read[0]] = $read[1];
            }
        }

        return $result;
    }

    /**
     * Which flag an occurrence of the assoc-args variable reads.
     *
     * @param File $phpcsFile   The file being scanned.
     * @param int  $variablePtr Position of the assoc-args T_VARIABLE.
     *
     * @return array{0: string, 1: int}|null Flag name ('' for a dynamic key)
     *         and the token to report on, or null when the variable is used
     *         in another way.
     */
    private function readFlag(File $phpcsFile, int $variablePtr): ?array
    {
        $tokens = $phpcsFile->getTokens();

        // $assoc_args['name'].
        $next = $phpcsFile->findNext(T_WHITESPACE, ($variablePtr + 1), null, true);
        if ($next !== false && $tokens[$next]['code'] === T_OPEN_SQUARE_BRACKET) {
            $key = $phpcsFile->findNext(T_WHITESPACE, ($next + 1), null, true);
            if ($key === false || $tokens[$key]['code'] !== T_CONSTANT_ENCAPSED_STRING
                || !isset($tokens[$next]['bracket_closer'])
                || $phpcsFile->findNext(T_WHITESPACE, ($key + 1), null, true) !== $tokens[$next]['bracket_closer']
            ) {
                return ['', $variablePtr];
            }

            // unset( $assoc_args['name'] ) and $assoc_args['name'] = ... write, not read.
            $after = $phpcsFile->findNext(T_WHITESPACE, ($tokens[$next]['bracket_closer'] + 1), null, true);
            if (($after !== false && isset(Tokens::$assignmentTokens[$tokens[$after]['code']]))
                || $this->isUnsetArgument($phpcsFile, $variablePtr)
            ) {
                return ['', $variablePtr];
            }

            return [substr($tokens[$key]['content'], 1, -1), $key];
        }

        // An argument of get_flag_value(), array_key_exists() or WP_CLI::confirm().
        if (empty($tokens[$variablePtr]['nested_parenthesis'])) {
            return null;
        }

        $parentheses = $tokens[$variablePtr]['nested_parenthesis'];
        $open        = (int) array_key_last($parentheses);
        $function    = $phpcsFile->findPrevious(T_WHITESPACE, ($open - 1), null, true);
        if ($function === false || $tokens[$function]['code'] !== T_STRING) {
            return null;
        }

        $arguments = $this->splitArguments($phpcsFile, $open);
        $position  = null;
        foreach ($arguments as $index => [$start, $argEnd]) {
            if ($start === $variablePtr && $argEnd === $variablePtr) {
                $position = $index;
            }
        }

        $callee = strtolower($tokens[$function]['content']);

        if ($callee === 'get_flag_value' && $position === 0 && isset($arguments[1])) {
            return $this->literalKey($phpcsFile, $arguments[1], $variablePtr);
        }

        if ($callee === 'array_key_exists' && $position === 1) {
            return $this->literalKey($phpcsFile, $arguments[0], $variablePtr);
        }

        if ($callee === 'confirm' && $position === 1) {
            $colon = $phpcsFile->findPrevious(T_WHITESPACE, ($function - 1), null, true);
            $class = ($colon !== false) ? $phpcsFile->findPrevious(T_WHITESPACE, ($colon - 1), null, true) : false;
            if ($class !== false && $tokens[$colon]['code'] === T_DOUBLE_COLON && $tokens[$class]['content'] === 'WP_CLI') {
                return ['yes', $variablePtr];
            }
        }

        return null;
    }

    /**
     * Whether a token is directly inside unset( ... ).
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Token position.
     *
     * @return bool
     */
    private function isUnsetArgument(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        if (empty($tokens[$stackPtr]['nested_parenthesis'])) {
            return false;
        }

        $open  = (int) array_key_last($tokens[$stackPtr]['nested_parenthesis']);
        $owner = $phpcsFile->findPrevious(T_WHITESPACE, ($open - 1), null, true);

        return $owner !== false && $tokens[$owner]['code'] === T_UNSET;
    }

    /**
     * Flag name of a string-literal argument; '' when it is dynamic.
     *
     * @param File                  $phpcsFile   The file being scanned.
     * @param array{0: int, 1: int} $range       Argument token range.
     * @param int                   $variablePtr Fallback report pointer.
     *
     * @return array{0: string, 1: int}
     */
    private function literalKey(File $phpcsFile, array $range, int $variablePtr): array
    {
        $key = $this->literalString($phpcsFile, $range);

        return ($key !== null) ? [$key, $range[0]] : ['', $variablePtr];
    }

    /**
     * Items of a literal array (`[...]` or `array(...)`).
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $start     First token of the array.
     *
     * @return array<int, array{0: int, 1: int}>|null Null when not an array literal.
     */
    private function arrayItems(File $phpcsFile, int $start): ?array
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$start]['code'] === T_OPEN_SHORT_ARRAY && isset($tokens[$start]['bracket_closer'])) {
            return $this->splitList($phpcsFile, $start, $tokens[$start]['bracket_closer']);
        }

        if ($tokens[$start]['code'] === T_ARRAY && isset($tokens[$start]['parenthesis_opener'])) {
            $open = $tokens[$start]['parenthesis_opener'];

            return $this->splitList($phpcsFile, $open, $tokens[$open]['parenthesis_closer']);
        }

        return null;
    }

    /**
     * Token code that closes an array literal starting with $code.
     *
     * @param int|string $code Opening token code.
     *
     * @return int|string
     */
    private function closerCode($code)
    {
        return ($code === T_OPEN_SHORT_ARRAY) ? T_CLOSE_SHORT_ARRAY : T_CLOSE_PARENTHESIS;
    }
}
