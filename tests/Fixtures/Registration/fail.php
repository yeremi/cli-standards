<?php
/**
 * Test fixture: registration forms — should FAIL.
 */

// ❌ G1: closure command without any documentation.
WP_CLI::add_command( 'acme pong', function ( $args, $assoc_args ) {
    WP_CLI::success( 'ping' );
} );

// ❌ G2: synopsis documents the options, but there are no examples.
WP_CLI::add_command(
    'acme echo',
    'acme_echo',
    [
        'shortdesc' => 'Echo the input.',
        'synopsis'  => [
            [ 'type' => 'positional', 'name' => 'text' ],
        ],
    ]
);

function acme_echo( $args, $assoc_args ) {
    WP_CLI::line( $args[0] );
}

/**
 * Purge the page cache.
 *
 * ## OPTIONS
 *
 * [--url=<url>]
 * : Single URL.
 *
 * ## EXAMPLES
 *
 *     wp acme cache purge
 *
 * @since 1.0.0
 */
WP_CLI::add_command( 'acme cache purge', function ( $args, $assoc_args ) {
    // ❌ E1 + ⚠ E3: the registered name "purge" makes this closure destructive.
    acme_purge_page_cache();
    WP_CLI::success( 'Purged.' );
} );

/**
 * Tune the cache.
 *
 * ## OPTIONS
 *
 * [--ttl=<seconds>]
 * : Time to live.
 *
 * ## EXAMPLES
 *
 *     wp acme tune --ttl=60
 */
WP_CLI::add_command( 'acme tune', function ( $args, $opts ) {
    // ❌ B1 + ⚠ B2 + ⚠ G4: closure assoc args are checked; no @since.
    $ttl = $opts['cacheTtl'];
    WP_CLI::success( "TTL: {$ttl}" );
} );
