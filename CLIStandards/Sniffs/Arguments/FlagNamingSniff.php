<?php
/**
 * FlagNamingSniff
 *
 * @package CLIStandards
 *
 * Detects non-kebab-case flag names in $assoc_args array access.
 *
 * WP-CLI convention requires kebab-case for all flags: --my-flag, not
 * --my_flag or --myFlag. This sniff checks array key access on $assoc_args
 * and flags any key that contains underscores or uppercase letters.
 *
 * Rule:    All flag and option names must use kebab-case
 * Level:   MUST — violation blocks CI
 * Fix:     Rename --my_flag to --my-flag, --postType to --post-type
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Arguments;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class FlagNamingSniff implements Sniff
{
    /**
     * Kebab-case validation pattern.
     *
     * Matches: a, my-flag, batch-size, per-page
     * Rejects: my_flag, myFlag, MY_FLAG, --flag (dashes at start)
     *
     * @var string
     */
    private $kebabCasePattern = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    /**
     * Standard WP-CLI flags that are always valid.
     *
     * @var array<string>
     */
    private $standardFlags = [
        'format',
        'fields',
        'field',
        'yes',
        'quiet',
        'color',
        'no-color',
        'debug',
        'prompt',
        'url',
        'user',
        'skip-plugins',
        'skip-themes',
        'path',
        'ssh',
        'http',
        'require',
    ];

    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return array<int>
     */
    public function register(): array
    {
        return [T_VARIABLE];
    }

    /**
     * Processes this sniff when a variable token is encountered.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  The position of the current token.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // Only interested in $assoc_args.
        if ($tokens[$stackPtr]['content'] !== '$assoc_args') {
            return;
        }

        // Look for array access: $assoc_args['key'].
        $next = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
        if ($next === false || $tokens[$next]['code'] !== T_OPEN_SQUARE_BRACKET) {
            return;
        }

        // Get the key token (should be a string constant).
        $keyToken = $phpcsFile->findNext(T_WHITESPACE, ($next + 1), null, true);
        if ($keyToken === false) {
            return;
        }

        // Only validate string literal keys, not variables ($assoc_args[$key]).
        if ($tokens[$keyToken]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            return;
        }

        // Remove quotes from the key.
        $key = trim($tokens[$keyToken]['content'], "\"'");

        // Skip empty keys.
        if ($key === '') {
            return;
        }

        // Skip known standard WP-CLI flags.
        if (in_array($key, $this->standardFlags, true)) {
            return;
        }

        // Validate kebab-case.
        if (!preg_match($this->kebabCasePattern, $key)) {
            $suggestion = str_replace('_', '-', strtolower(preg_replace('/[A-Z]/', '-$0', $key)));
            $suggestion = ltrim($suggestion, '-');

            $phpcsFile->addError(
                'WP-CLI flag name "%s" must use kebab-case. Did you mean "%s"?',
                $keyToken,
                'NonKebabCase',
                [$key, $suggestion]
            );
        }
    }
}
