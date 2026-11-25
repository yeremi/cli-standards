<?php
/**
 * Test fixture: CommandRegistration — should FAIL.
 */

// ❌ A1: a single function command without a namespace.
WP_CLI::add_command( 'ping', 'acme_ping' );

// ❌ A1: a single method command without a namespace.
WP_CLI::add_command( 'stats', [ 'Acme_Command', 'stats' ] );

// ❌ A1: a class command group that takes over WP-CLI core's "user".
WP_CLI::add_command( 'user', 'Acme_User_Command' );
