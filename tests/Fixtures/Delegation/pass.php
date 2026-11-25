<?php
/**
 * Test fixture: delegation to helpers and services — should PASS.
 *
 * Thin command methods that delegate to same-class helpers are followed
 * (up to two call levels). Delegation to other classes is declared with a
 * reasoned "@cli-standards <kind>-delegated" docblock tag.
 */

class Delegation_Pass_Command {

    private $wiper;

    private $renderer;

    /**
     * Purge expired tokens.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp acme purge --yes
     *
     * @since 1.0.0
     */
    public function purge( $args, $assoc_args ) {
        // ✅ confirm() and dry-run handling live in a private helper.
        $this->confirm_and_purge( $assoc_args );
        WP_CLI::success( 'Purged.' );
    }

    /**
     * Reset plugin settings.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp acme reset --yes
     *
     * @since 1.0.0
     */
    public function reset( $args, $assoc_args ) {
        // ✅ Two levels deep: reset() -> prepare_reset() -> ask().
        $this->prepare_reset( $assoc_args );
        WP_CLI::success( 'Reset.' );
    }

    /**
     * Flush every cache layer.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp acme flush --yes
     *
     * @since 1.0.0
     */
    public function flush( $args, $assoc_args ) {
        // ✅ Mutually recursive helpers are visited once (no infinite loop).
        $this->flush_layer( $assoc_args, 1 );
        WP_CLI::success( 'Flushed.' );
    }

    /**
     * Wipe tenant data.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp acme wipe --yes
     *
     * @since 1.0.0
     * @cli-standards confirm-delegated Tenant_Wiper::run() calls WP_CLI::confirm() with $assoc_args
     * @cli-standards dry-run-delegated Tenant_Wiper::run() honors --dry-run
     */
    public function wipe( $args, $assoc_args ) {
        // ✅ Declared delegation to another class, with reasons.
        $this->wiper->run( $assoc_args );
    }

    /**
     * List stored items.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     *
     * ## EXAMPLES
     *
     *     wp acme list-items --format=json
     *
     * @since 1.0.0
     */
    public function list_items( $args, $assoc_args ) {
        // ✅ format_items() is called by a private renderer.
        $this->render( [], $assoc_args );
    }

    /**
     * Migrate legacy records.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp acme migrate
     *
     * @since 1.0.0
     */
    public function migrate( $args, $assoc_args ) {
        // ✅ --batch-size and --dry-run are read by a private helper.
        $this->run_migration( $assoc_args );
        WP_CLI::success( 'Migrated.' );
    }

    /**
     * Show the weekly report.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     *
     * ## EXAMPLES
     *
     *     wp acme show-report --format=csv
     *
     * @since 1.0.0
     * @cli-standards format-delegated Report_Renderer::render() calls format_items()
     */
    public function show_report( $args, $assoc_args ) {
        // ✅ Declared delegation of --format to another class.
        $this->renderer->render( $assoc_args );
    }

    private function confirm_and_purge( array $assoc_args ): void {
        WP_CLI::confirm( 'Purge expired tokens?', $assoc_args );
        if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
            return;
        }
        acme_purge_tokens();
    }

    private function prepare_reset( array $assoc_args ): void {
        self::ask( $assoc_args );
        update_option( 'acme_settings', [] );
    }

    private static function ask( array $assoc_args ): void {
        WP_CLI::confirm( 'Reset all settings?', $assoc_args );
        WP_CLI::debug( 'dry-run: ' . (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) );
    }

    private function flush_layer( array $assoc_args, int $layer ): void {
        WP_CLI::confirm( "Flush layer {$layer}?", $assoc_args );
        $this->flush_next( $assoc_args, $layer );
    }

    private function flush_next( array $assoc_args, int $layer ): void {
        if ( $layer < 3 && ! WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
            $this->flush_layer( $assoc_args, $layer + 1 );
        }
    }

    private function render( array $items, array $assoc_args ): void {
        $format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
        WP_CLI\Utils\format_items( $format, $items, [ 'id', 'name' ] );
    }

    private function run_migration( array $assoc_args ): void {
        $batch_size = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 100 );
        $dry_run    = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
        acme_migrate( $batch_size, $dry_run );
    }
}
