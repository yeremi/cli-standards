<?php
/**
 * Test fixture: helper classes are not command classes — should PASS.
 *
 * None of these classes is registered with WP_CLI::add_command() in this
 * file, extends or implements a "Command" type, or has "Command"/"CLI" in
 * its name, so their public methods are not subcommands.
 */

class Acme_Sync_Utility {

    // ✅ Support code that logs through WP-CLI; "delete" in the name is irrelevant.
    public static function delete_stale_locks() {
        WP_CLI::warning( 'Removing stale sync locks.' );
        acme_delete_locks();
    }
}

class Acme_Product_Fetcher {

    // ✅ A fetcher used by a command; its $args is an API query, not CLI input.
    public function fetch_batch( array $args ): array {
        WP_CLI::debug( 'Fetching a batch.' );
        return acme_api_get( $args );
    }
}

/**
 * Table formatting for the Acme commands.
 *
 * @cli-standards not-a-command
 */
class Acme_CLI_Formatter {

    // ✅ "CLI" in the name, but the class opts out explicitly.
    public function print_table( $rows, $fields ) {
        WP_CLI\Utils\format_items( 'table', $rows, $fields );
    }
}
