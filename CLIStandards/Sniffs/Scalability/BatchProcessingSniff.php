<?php
/**
 * BatchProcessingSniff
 *
 * @package CLIStandards
 *
 * Detects unbounded query patterns inside WP-CLI command files that
 * would load all records into memory at once.
 *
 * Common patterns detected:
 * - 'numberposts' => -1
 * - 'posts_per_page' => -1
 * - 'nopaging' => true
 *
 * These work fine on small datasets but crash on production sites
 * with 100k+ records when PHP hits its memory limit.
 *
 * Rule:    Avoid unbounded queries (numberposts=-1, nopaging=true) in CLI commands
 * Level:   SHOULD — violation reported as warning
 * Fix:     Process in pages: get_posts(['numberposts'=>$batch,'offset'=>$offset])
 * @since   1.0.0
 */

namespace CLIStandards\Sniffs\Scalability;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class BatchProcessingSniff implements Sniff
{
    /**
     * Whether the current file is a WP-CLI context.
     *
     * @var bool|null
     */
    private $cliContextCache = [];

    /**
     * Array keys that indicate unbounded queries when set to -1 or true.
     *
     * @var array<string, string>
     */
    private $unboundedKeys = [
        'numberposts'    => '-1',
        'posts_per_page' => '-1',
        'nopaging'       => 'true',
        'number'         => '-1',
    ];

    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return array<int>
     */
    public function register(): array
    {
        return [T_CONSTANT_ENCAPSED_STRING];
    }

    /**
     * Processes this sniff when a string constant is encountered.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  The position of the current token.
     *
     * @return void|int
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $filename = $phpcsFile->getFilename();
        if (!isset($this->cliContextCache[$filename])) {
            $this->cliContextCache[$filename] = $this->detectCliContext($phpcsFile);

            if (!$this->cliContextCache[$filename]) {
                return $phpcsFile->numTokens;
            }
        }

        $tokens = $phpcsFile->getTokens();
        $key    = trim($tokens[$stackPtr]['content'], "\"'");

        // Check if this is one of the unbounded query keys.
        if (!isset($this->unboundedKeys[$key])) {
            return;
        }

        $expectedDangerousValue = $this->unboundedKeys[$key];

        // Look forward for => operator.
        $arrow = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
        if ($arrow === false || $tokens[$arrow]['code'] !== T_DOUBLE_ARROW) {
            return;
        }

        // Get the value after =>.
        $valueToken = $phpcsFile->findNext(T_WHITESPACE, ($arrow + 1), null, true);
        if ($valueToken === false) {
            return;
        }

        // Handle negative numbers: T_MINUS followed by T_LNUMBER.
        $value = '';
        if ($tokens[$valueToken]['code'] === T_MINUS) {
            $numberToken = $phpcsFile->findNext(T_WHITESPACE, ($valueToken + 1), null, true);
            if ($numberToken !== false && $tokens[$numberToken]['code'] === T_LNUMBER) {
                $value = '-' . $tokens[$numberToken]['content'];
            }
        } elseif ($tokens[$valueToken]['code'] === T_LNUMBER) {
            $value = $tokens[$valueToken]['content'];
        } elseif ($tokens[$valueToken]['code'] === T_STRING) {
            $value = strtolower($tokens[$valueToken]['content']);
        } elseif ($tokens[$valueToken]['code'] === T_TRUE) {
            $value = 'true';
        }

        // Only flag literal dangerous values, not variables.
        if ($value === $expectedDangerousValue) {
            $phpcsFile->addWarning(
                'Unbounded query detected: "%s" => %s loads all records into memory. In CLI commands processing large datasets, use batched iteration with a --batch-size flag and wp_cache_flush_runtime() between batches.',
                $stackPtr,
                'UnboundedQuery',
                [$key, $value]
            );
        }
    }

    /**
     * Detect whether the current file is a WP-CLI command context.
     *
     * @param File $phpcsFile The file being scanned.
     *
     * @return bool
     */
    private function detectCliContext(File $phpcsFile): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = 0; $i < $phpcsFile->numTokens; $i++) {
            if ($tokens[$i]['code'] !== T_STRING || $tokens[$i]['content'] !== 'WP_CLI') {
                continue;
            }

            $next = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), null, true);
            if ($next !== false && $tokens[$next]['code'] === T_DOUBLE_COLON) {
                return true;
            }
        }

        return false;
    }
}
