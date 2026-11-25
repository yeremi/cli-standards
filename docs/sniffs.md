# CLIStandards Coding Standard

## Flag Extraction

Read flags with WP_CLI\Utils\get_flag_value() instead of indexing $assoc_args directly. It gives every flag a default in one place and supports --no-&lt;flag&gt; negation.

Requirement: B2 (SHOULD, warning).
Error codes: DirectAccess.
  <table>
   <tr>
    <th>Valid: get_flag_value() with a default.</th>
    <th>Invalid: Direct array access.</th>
   </tr>
   <tr>
<td>

    $dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

</td>
<td>

    $dry_run = isset( $assoc_args['dry-run'] );

</td>
   </tr>
  </table>

## Flag Naming

Flag and option names must use kebab-case (--batch-size), as WP-CLI core and its handbook do. snake_case or camelCase flags are inconsistent for users and break muscle memory. Standard WP-CLI flags (format, fields, yes, url, ...) are always accepted.

Requirement: B1 (MUST, error).
Error codes: NonKebabCase.
  <table>
   <tr>
    <th>Valid: kebab-case flag.</th>
    <th>Invalid: snake_case flag.</th>
   </tr>
   <tr>
<td>

    $size = WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 100 );

</td>
<td>

    $size = $assoc_args['batch_size'];

</td>
   </tr>
  </table>

## Command Registration

Register commands with WP_CLI::add_command() under a vendor namespace: wp &lt;vendor&gt; &lt;noun&gt; [&lt;verb&gt;]. A bare name such as &quot;list&quot; collides with other plugins and with WP-CLI core commands. A class registered as a single word (wp acme) is a command group, which is allowed unless the word is a WP-CLI core command such as user or cache. Command name segments must be lowercase.

Requirement: A1 (MUST, error).
Error codes: MissingNamespace, UppercaseSegment.
  <table>
   <tr>
    <th>Valid: Namespaced command name.</th>
    <th>Invalid: Bare or core command name.</th>
   </tr>
   <tr>
<td>

    WP_CLI::add_command( 'acme cache purge', 'acme_purge_cache' );

</td>
<td>

    WP_CLI::add_command( 'purge', 'acme_purge_cache' );

</td>
   </tr>
  </table>

## Docblock Examples

Every command docblock needs an ## EXAMPLES section with at least one realistic invocation; &quot;wp help&quot; shows it to users. An ## EXAMPLES heading in the longdesc passed to WP_CLI::add_command() counts as well.

Requirement: G2 (MUST, error).
Error codes: MissingExamplesSection.
Properties: ignoreMethods (method names that are never commands), ignoreClasses (classes that are never command classes).
  <table>
   <tr>
    <th>Valid: Example given.</th>
    <th>Invalid: No examples.</th>
   </tr>
   <tr>
<td>

    /**
     * Reindex posts.
     *
     * ## EXAMPLES
     *
     *     wp acme reindex --batch-size=200
     */

</td>
<td>

    /**
     * Reindex posts.
     */

</td>
   </tr>
  </table>

## Docblock Options

Every command needs a docblock, and one with an ## OPTIONS section when it reads its arguments. WP-CLI builds &quot;wp help&quot; and validates the command line from it. A synopsis (or an ## OPTIONS heading in longdesc) passed to WP_CLI::add_command() counts as well. The heading must stand on its own line.

Requirement: G1 (MUST, error).
Error codes: MissingDocblock, MissingOptionsSection.
Properties: ignoreMethods (method names that are never commands), ignoreClasses (classes that are never command classes).
  <table>
   <tr>
    <th>Valid: Options documented.</th>
    <th>Invalid: No options section.</th>
   </tr>
   <tr>
<td>

    /**
     * Reindex posts.
     *
     * ## OPTIONS
     *
     * [--batch-size=<n>]
     * : Posts per batch.
     */

</td>
<td>

    /**
     * Reindex posts.
     */

</td>
   </tr>
  </table>

## Documented Flags

Every flag a command reads must be in its synopsis: WP-CLI hides undocumented flags from &quot;wp help&quot; and rejects them as unknown once a synopsis exists. WP_CLI::confirm( $msg, $assoc_args ) reads --yes. Documented flags the handler never reads are reported too, unless the assoc args are passed on to other code. Global parameters (--url, --path, ...) and a generic [--&lt;field&gt;=&lt;value&gt;] entry are accepted.

Requirement: G1 (advisory, warning; not in CLIStandardsMust).
Error codes: UndocumentedFlag, UnusedFlag.
Properties: ignoreMethods (method names that are never commands), ignoreClasses (classes that are never command classes).
  <table>
   <tr>
    <th>Valid: Read flag documented.</th>
    <th>Invalid: Read flag missing.</th>
   </tr>
   <tr>
<td>

    /**
     * ## OPTIONS
     *
     * [--verbose]
     * : Show details.
     */
    public function stats( $args, $assoc_args ) {
        $verbose = WP_CLI\Utils\get_flag_value( $assoc_args, 'verbose', false );
    }

</td>
<td>

    /**
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     */
    public function stats( $args, $assoc_args ) {
        $verbose = WP_CLI\Utils\get_flag_value( $assoc_args, 'verbose', false );
    }

</td>
   </tr>
  </table>

## Since Annotation

Command docblocks should carry a @since tag with the version that introduced the command, to support deprecations and changelogs.

Requirement: G4 (SHOULD, warning).
Error codes: MissingSince.
Properties: ignoreMethods (method names that are never commands), ignoreClasses (classes that are never command classes).
  <table>
   <tr>
    <th>Valid: Version recorded.</th>
    <th>Invalid: No version.</th>
   </tr>
   <tr>
<td>

    /**
     * Reindex posts.
     *
     * @since 2.3.0
     */

</td>
<td>

    /**
     * Reindex posts.
     */

</td>
   </tr>
  </table>

## Error Severity

Never call exit(), die() or wp_die() inside a command: they skip WP-CLI&#039;s shutdown and error reporting, and break WP_CLI::runcommand(). Use WP_CLI::error() for fatal failures and WP_CLI::halt( $code ) to set an exit code. exit at file scope, such as the ABSPATH direct-access guard, is not reported.

Requirement: D1 (MUST, error).
Error codes: DirectExit.
  <table>
   <tr>
    <th>Valid: WP-CLI exit semantics.</th>
    <th>Invalid: Direct exit.</th>
   </tr>
   <tr>
<td>

    if ( ! $ok ) {
        WP_CLI::error( 'Import failed.' );
    }

</td>
<td>

    if ( ! $ok ) {
        exit( 1 );
    }

</td>
   </tr>
  </table>

## WP_Error Handling

Check the result of WordPress functions that can return WP_Error with is_wp_error() before using it. Functions that never return WP_Error are not reported, and wp_insert_post(), wp_update_post() and wp_insert_attachment() only when their $wp_error argument is passed and not false.

Requirement: D3 (MUST in the design model; warning here because detection is heuristic).
Error codes: UncheckedWpError (result stored, never checked), UncapturedWpError (result discarded).
  <table>
   <tr>
    <th>Valid: Result checked.</th>
    <th>Invalid: Result used unchecked.</th>
   </tr>
   <tr>
<td>

    $term = wp_insert_term( 'News', 'category' );
    if ( is_wp_error( $term ) ) {
        WP_CLI::error( $term );
    }

</td>
<td>

    $term = wp_insert_term( 'News', 'category' );
    WP_CLI::success( "Created {$term['term_id']}" );

</td>
   </tr>
  </table>

## Available Fields

Listing commands that call format_items() should document their columns in an ## AVAILABLE FIELDS section, so users know what --fields accepts.

Requirement: C4 / G3 (SHOULD, warning).
Error codes: MissingAvailableFields.
Properties: ignoreMethods (method names that are never commands), ignoreClasses (classes that are never command classes).
  <table>
   <tr>
    <th>Valid: Fields documented.</th>
    <th>Invalid: No fields section.</th>
   </tr>
   <tr>
<td>

    /**
     * List jobs.
     *
     * ## AVAILABLE FIELDS
     *
     * * id
     * * status
     */

</td>
<td>

    /**
     * List jobs.
     *
     * ## EXAMPLES
     *
     *     wp acme job list
     */

</td>
   </tr>
  </table>

## Format Support

Listing commands (list, get, search, index, show, status, info, report) should print through WP_CLI\Utils\format_items() and honor --format, so scripts get table, json, csv or yaml output. Same-class helpers are followed; declare work done in another class with &quot;@cli-standards format-delegated &lt;reason&gt;&quot;.

Requirement: C3 (SHOULD, warning).
Error codes: MissingFormatSupport.
Properties: ignoreMethods (method names that are never commands), ignoreClasses (classes that are never command classes).
  <table>
   <tr>
    <th>Valid: --format through format_items().</th>
    <th>Invalid: Hand-made table.</th>
   </tr>
   <tr>
<td>

    $format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
    WP_CLI\Utils\format_items( $format, $items, [ 'id', 'name' ] );

</td>
<td>

    foreach ( $items as $item ) {
        WP_CLI::line( "{$item->id}\t{$item->name}" );
    }

</td>
   </tr>
  </table>

## Output Method

Command code must write through the WP-CLI logger (WP_CLI::log(), ::line(), ::success(), ::error()), never echo or print. The logger respects --quiet, keeps stderr and stdout apart and is captured by WP_CLI::runcommand(). Only command handlers and the same-class methods they call are checked, so web code in the same file is not reported. Fixable: phpcbf turns a standalone echo/print of one expression whose output ends with a newline (. PHP_EOL or a trailing \n) into WP_CLI::line() without that newline; other echo/print statements are only reported, because WP_CLI::line() adds a newline.

Requirement: C1 (MUST, error).
Error codes: RawOutput.
Properties: ignoreMethods (method names that are never commands), ignoreClasses (classes that are never command classes).
  <table>
   <tr>
    <th>Valid: WP-CLI logger.</th>
    <th>Invalid: Raw output.</th>
   </tr>
   <tr>
<td>

    WP_CLI::line( $report );

</td>
<td>

    echo $report;

</td>
   </tr>
  </table>

## Stdout / Stderr Separation

A command that prints structured data with format_items() should not also write status messages with WP_CLI::log(), which goes to stdout and corrupts the data for pipes (| jq, | grep). Use WP_CLI::debug() or WP_CLI::warning() (stderr) for status messages.

Requirement: C2 (SHOULD, warning).
Error codes: MixedOutput.
  <table>
   <tr>
    <th>Valid: Status on stderr, data on stdout.</th>
    <th>Invalid: Status mixed into stdout.</th>
   </tr>
   <tr>
<td>

    WP_CLI::debug( 'Fetching items...' );
    WP_CLI\Utils\format_items( $format, $items, $fields );

</td>
<td>

    WP_CLI::log( 'Fetching items...' );
    WP_CLI\Utils\format_items( $format, $items, $fields );

</td>
   </tr>
  </table>

## Confirm On Delete

Commands that delete or irreversibly change data must ask WP_CLI::confirm() first. A command is destructive when its name (the @subcommand, the registered name, or the method name) contains a whole word such as delete, remove, flush, truncate, reset, purge, destroy, drop, clear, wipe, uninstall or erase, or when it calls a content-destroying API (wp_delete_post(), $wpdb-&gt;delete(), a $wpdb-&gt;query() with DELETE/TRUNCATE/DROP, ...). Same-class helpers are followed; declare a confirmation in another class with &quot;@cli-standards confirm-delegated &lt;reason&gt;&quot;.

Requirement: E1 (MUST, error).
Error codes: MissingConfirm.
Properties: destructiveFunctions (APIs that make a command destructive; &#039;-&gt;name&#039; means a $wpdb method), ignoreMethods, ignoreClasses.
  <table>
   <tr>
    <th>Valid: Confirmation before deleting.</th>
    <th>Invalid: Deletes without asking.</th>
   </tr>
   <tr>
<td>

    public function purge( $args, $assoc_args ) {
        WP_CLI::confirm( 'Delete all logs?', $assoc_args );
        acme_delete_logs();
    }

</td>
<td>

    public function purge( $args, $assoc_args ) {
        acme_delete_logs();
    }

</td>
   </tr>
  </table>

## Confirm Passthrough

Pass the assoc args to WP_CLI::confirm() so --yes skips the prompt in CI and cron. Without them the prompt blocks every non-interactive run. An explicit guard such as &quot;if ( ! isset( $assoc_args[&#039;yes&#039;] ) )&quot; around the call is accepted. Fixable: phpcbf adds the assoc-args variable as the second argument, when the call is in a command handler or in a function with a $assoc_args / $assocArgs parameter.

Requirement: E2 (MUST, error).
Error codes: MissingAssocArgs.
  <table>
   <tr>
    <th>Valid: --yes honored.</th>
    <th>Invalid: --yes ignored.</th>
   </tr>
   <tr>
<td>

    WP_CLI::confirm( 'Reset the index?', $assoc_args );

</td>
<td>

    WP_CLI::confirm( 'Reset the index?' );

</td>
   </tr>
  </table>

## Dry-Run Support

Commands that change state (create, update, delete, migrate, sync, import, reset, install, rebuild, ...) or call destructive APIs should support --dry-run, so users can preview the effect. Same-class helpers are followed; declare handling in another class with &quot;@cli-standards dry-run-delegated &lt;reason&gt;&quot;.

Requirement: E3 (SHOULD, warning).
Error codes: MissingDryRun.
Properties: destructiveFunctions (APIs that make a command destructive; &#039;-&gt;name&#039; means a $wpdb method), ignoreMethods, ignoreClasses.
  <table>
   <tr>
    <th>Valid: Preview supported.</th>
    <th>Invalid: No preview.</th>
   </tr>
   <tr>
<td>

    if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
        WP_CLI::log( "Would migrate {$count} orders." );
        return;
    }

</td>
<td>

    acme_migrate_orders();
    WP_CLI::success( 'Migrated.' );

</td>
   </tr>
  </table>

## Batch Processing

Do not load unbounded result sets (&#039;numberposts&#039; =&gt; -1, &#039;posts_per_page&#039; =&gt; -1, &#039;nopaging&#039; =&gt; true) in CLI code. Large sites run out of memory; process items in pages instead.

Requirement: F2 (MUST in the design model; warning here because detection is heuristic).
Error codes: UnboundedQuery.
  <table>
   <tr>
    <th>Valid: Paged query.</th>
    <th>Invalid: Everything at once.</th>
   </tr>
   <tr>
<td>

    $ids = get_posts( [ 'posts_per_page' => $batch_size, 'paged' => $paged, 'fields' => 'ids' ] );

</td>
<td>

    $ids = get_posts( [ 'posts_per_page' => -1, 'fields' => 'ids' ] );

</td>
   </tr>
  </table>

## Batch Size Flag

Bulk commands (migrate, sync, import, export, regenerate, rebuild, process, batch, bulk, reindex) should let users tune batch volume with --batch-size (or --per-page) for constrained hosts. Same-class helpers are followed.

Requirement: F3 (SHOULD, warning).
Error codes: MissingBatchSizeFlag.
Properties: ignoreMethods (method names that are never commands), ignoreClasses (classes that are never command classes).
  <table>
   <tr>
    <th>Valid: Tunable batch size.</th>
    <th>Invalid: Hard-coded batch size.</th>
   </tr>
   <tr>
<td>

    $batch_size = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 100 );

</td>
<td>

    $batch_size = 100;

</td>
   </tr>
  </table>

## Progress Bar

Loops in a command that call WP-CLI for each item should show WP_CLI\Utils\make_progress_bar() in the same function. Loops over array literals and constants, and loops that only print messages (error, warning, line), are skipped unless they iterate an unbounded source such as get_posts().

Requirement: F1 (SHOULD, warning).
Error codes: MissingProgressBar.
Properties: unboundedSources (functions and classes whose results are unbounded), ignoreMethods, ignoreClasses.
  <table>
   <tr>
    <th>Valid: Progress reported.</th>
    <th>Invalid: Silent loop.</th>
   </tr>
   <tr>
<td>

    $progress = WP_CLI\Utils\make_progress_bar( 'Reindexing', count( $ids ) );
    foreach ( $ids as $id ) {
        acme_reindex( $id );
        $progress->tick();
    }
    $progress->finish();

</td>
<td>

    foreach ( $ids as $id ) {
        acme_reindex( $id );
        WP_CLI::debug( "Reindexed {$id}" );
    }

</td>
   </tr>
  </table>
