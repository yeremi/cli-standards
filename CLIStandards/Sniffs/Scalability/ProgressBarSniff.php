<?php
/**
 * ProgressBarSniff
 *
 * @package CLIStandards
 *
 * Detects bulk WP-CLI commands that process items in a loop without
 * using WP_CLI\Utils\make_progress_bar() for user feedback.
 *
 * For commands that iterate over collections (foreach with WP_CLI calls),
 * progress bars provide essential feedback on long-running operations.
 *
 * Loops that cannot be long-running are skipped:
 * - foreach over an array literal or a constant (fixed, small collections)
 * - loops whose only WP_CLI calls are messages (error/warning/line) or
 *   command registration (add_command/add_hook), unless
 *   they iterate data from an unbounded source such as get_posts()
 *
 * Rule:    Bulk loops with WP-CLI calls should display a progress bar
 * Level:   SHOULD — violation reported as warning
 * Fix:     Use $progress = WP_CLI\Utils\make_progress_bar('Processing', $total) before loop
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Scalability;

use CLIStandards\Helpers\AbstractCommandSniff;
use CLIStandards\Helpers\Names;
use PHP_CodeSniffer\Files\File;

class ProgressBarSniff extends AbstractCommandSniff
{
    /**
     * Functions and methods whose results can grow without bound.
     *
     * Configurable from a ruleset (type="array").
     *
     * @var array<string>
     */
    public $unboundedSources = [
        'get_posts',
        'get_pages',
        'get_users',
        'get_terms',
        'get_comments',
        'get_sites',
        'get_results',
        'get_col',
        'wc_get_orders',
        'wc_get_products',
        'WP_Query',
        'WP_User_Query',
        'WP_Term_Query',
        'WP_Comment_Query',
    ];

    /**
     * WP_CLI methods that only print messages; a loop that only calls these
     * is reporting, not processing.
     *
     * @var array<string>
     */
    private const MESSAGING_METHODS = ['error', 'warning', 'line'];

    /**
     * Tokens that may form a constant reference: FOO, self::FOO, Foo\Bar::BAZ.
     *
     * @var array<int|string>
     */
    private const CONSTANT_TOKENS = [
        T_STRING,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
        T_SELF,
        T_STATIC,
        T_PARENT,
        T_DOUBLE_COLON,
        T_NS_SEPARATOR,
    ];

    /**
     * How far back to look for make_progress_bar() when a loop is not inside
     * a function (file scope).
     *
     * @var int
     */
    private const FILE_SCOPE_LOOKBACK = 50;

    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return array<int>
     */
    public function register(): array
    {
        return [T_FOREACH, T_WHILE, T_FOR];
    }

    /**
     * Processes this sniff when a loop token is encountered.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  The position of the current token.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $loopStart = $tokens[$stackPtr]['scope_opener'];
        $loopEnd   = $tokens[$stackPtr]['scope_closer'];

        // Only care about loops that contain WP_CLI calls (i.e., CLI command context).
        $cliMethods = $this->getWpCliMethods($phpcsFile, $loopStart, $loopEnd);
        if ($cliMethods === []) {
            return;
        }

        // Fixed collections (literals, constants) cannot be long-running.
        if ($this->iteratesFixedCollection($phpcsFile, $stackPtr)) {
            return;
        }

        // A loop that only reports messages or registers commands is not bulk
        // processing, unless the data it walks comes from a source that can
        // grow without bound.
        // In a registration loop, log/debug lines are about the registration too.
        $registers    = array_intersect($cliMethods, self::REGISTRATION_METHODS) !== [];
        $messages     = $registers ? array_merge(self::MESSAGING_METHODS, ['log', 'debug']) : self::MESSAGING_METHODS;
        $onlyMessages = array_diff($cliMethods, $messages, self::REGISTRATION_METHODS) === [];
        if ($onlyMessages && !$this->iteratesUnboundedSource($phpcsFile, $stackPtr)) {
            return;
        }

        if (!$this->hasProgressBar($phpcsFile, $stackPtr, $loopEnd)) {
            $phpcsFile->addWarning(
                'Bulk loop contains WP-CLI calls but does not use WP_CLI\Utils\make_progress_bar() for user feedback. Progress indicators are essential for long-running CLI operations.',
                $stackPtr,
                'MissingProgressBar'
            );
        }
    }

    /**
     * Whether make_progress_bar() is called before or inside the loop.
     *
     * The search covers the enclosing function, closure or arrow function from
     * its opening brace to the end of the loop, so a progress bar created in a
     * neighboring method never counts. At file scope, it falls back to the
     * last FILE_SCOPE_LOOKBACK tokens before the loop.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Position of the loop token.
     * @param int  $loopEnd   Position of the loop's closing brace.
     *
     * @return bool
     */
    private function hasProgressBar(File $phpcsFile, int $stackPtr, int $loopEnd): bool
    {
        $tokens   = $phpcsFile->getTokens();
        $callable = $this->findEnclosingCallable($phpcsFile, $stackPtr);

        if ($callable !== null && isset($tokens[$callable]['scope_opener'])) {
            $searchStart = $tokens[$callable]['scope_opener'];
        } else {
            $searchStart = max(0, $stackPtr - self::FILE_SCOPE_LOOKBACK);
        }

        for ($i = $searchStart; $i < $loopEnd; $i++) {
            if (Names::lastSegment($tokens[$i]) === 'make_progress_bar') {
                return true;
            }
        }

        return false;
    }

    /**
     * Names of the WP_CLI:: methods called in a scope (lowercase, unique).
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $start     Start of the scope.
     * @param int  $end       End of the scope.
     *
     * @return array<string>
     */
    private function getWpCliMethods(File $phpcsFile, int $start, int $end): array
    {
        $tokens  = $phpcsFile->getTokens();
        $methods = [];

        for ($i = $start; $i < $end; $i++) {
            if (!Names::isWpCliClass($tokens[$i])) {
                continue;
            }

            $next = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);
            if ($next === false || $tokens[$next]['code'] !== T_DOUBLE_COLON) {
                continue;
            }

            $method = $phpcsFile->findNext(T_WHITESPACE, ($next + 1), null, true);
            if ($method !== false && $tokens[$method]['code'] === T_STRING) {
                $methods[strtolower($tokens[$method]['content'])] = true;
            }
        }

        return array_keys($methods);
    }

    /**
     * Whether a foreach iterates an array literal or a constant.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Position of the loop token.
     *
     * @return bool
     */
    private function iteratesFixedCollection(File $phpcsFile, int $stackPtr): bool
    {
        $expression = $this->getForeachSubject($phpcsFile, $stackPtr);
        if ($expression === []) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();
        $first  = $tokens[$expression[0]]['code'];

        if ($first === T_OPEN_SHORT_ARRAY || $first === T_ARRAY) {
            return true;
        }

        foreach ($expression as $ptr) {
            if (!in_array($tokens[$ptr]['code'], self::CONSTANT_TOKENS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a loop iterates data from an unbounded source.
     *
     * Looks at the loop's parenthesized header and, for `foreach ( $var as …)`,
     * at the latest assignment to $var in the same function before the loop.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Position of the loop token.
     *
     * @return bool
     */
    private function iteratesUnboundedSource(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (!isset($tokens[$stackPtr]['parenthesis_opener'], $tokens[$stackPtr]['parenthesis_closer'])) {
            return false;
        }

        $opener = $tokens[$stackPtr]['parenthesis_opener'];
        $closer = $tokens[$stackPtr]['parenthesis_closer'];

        if ($this->containsUnboundedSource($phpcsFile, $opener, $closer)) {
            return true;
        }

        $expression = $this->getForeachSubject($phpcsFile, $stackPtr);
        if (count($expression) !== 1 || $tokens[$expression[0]]['code'] !== T_VARIABLE) {
            return false;
        }

        $variable = $tokens[$expression[0]]['content'];
        $callable = $this->findEnclosingCallable($phpcsFile, $stackPtr);
        $limit    = ($callable !== null && isset($tokens[$callable]['scope_opener']))
            ? $tokens[$callable]['scope_opener']
            : 0;

        for ($i = ($stackPtr - 1); $i > $limit; $i--) {
            if ($tokens[$i]['code'] !== T_VARIABLE || $tokens[$i]['content'] !== $variable) {
                continue;
            }

            $next = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);
            if ($next === false || $tokens[$next]['code'] !== T_EQUAL) {
                continue;
            }

            $end = $phpcsFile->findEndOfStatement($next);

            return $this->containsUnboundedSource($phpcsFile, $next, $end);
        }

        return false;
    }

    /**
     * Whether a token range calls one of the unbounded sources.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $start     First token.
     * @param int  $end       Last token.
     *
     * @return bool
     */
    private function containsUnboundedSource(File $phpcsFile, int $start, int $end): bool
    {
        $tokens  = $phpcsFile->getTokens();
        $sources = array_map('strtolower', (array) $this->unboundedSources);

        for ($i = $start; $i <= $end; $i++) {
            if (in_array(strtolower((string) Names::lastSegment($tokens[$i])), $sources, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Non-whitespace tokens of the iterated expression of a foreach.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  Position of the loop token.
     *
     * @return array<int> Token positions; empty when not a foreach.
     */
    private function getForeachSubject(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] !== T_FOREACH
            || !isset($tokens[$stackPtr]['parenthesis_opener'], $tokens[$stackPtr]['parenthesis_closer'])
        ) {
            return [];
        }

        $opener = $tokens[$stackPtr]['parenthesis_opener'];
        $as     = $phpcsFile->findNext(T_AS, ($opener + 1), $tokens[$stackPtr]['parenthesis_closer']);
        if ($as === false) {
            return [];
        }

        $expression = [];
        for ($i = ($opener + 1); $i < $as; $i++) {
            if ($tokens[$i]['code'] !== T_WHITESPACE && $tokens[$i]['code'] !== T_COMMENT) {
                $expression[] = $i;
            }
        }

        return $expression;
    }
}
