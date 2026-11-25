<?php
/**
 * Test fixture: CommandRegistration — should PASS.
 *
 * A class registered under a single word is a command group: its public
 * methods become the subcommands, so the word already is the namespace.
 */

namespace Acme\Cli;

use WP_CLI;

// ✅ Class constant.
WP_CLI::add_command( 'acme', Acme_Command::class );

// ✅ Class name string.
WP_CLI::add_command( 'acmetools', 'Acme_Tools_Command' );

// ✅ Namespaced class name built from __NAMESPACE__.
WP_CLI::add_command( 'acmesync', __NAMESPACE__ . '\Sync_Command' );

// ✅ Object instance.
WP_CLI::add_command( 'acmecache', new Cache_Command() );

// ✅ Leaf commands with a namespace.
WP_CLI::add_command( 'acme ping', 'acme_ping' );
WP_CLI::add_command( 'acme stats', [ Acme_Command::class, 'stats' ] );
