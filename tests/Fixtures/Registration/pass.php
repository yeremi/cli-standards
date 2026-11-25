<?php
/**
 * Test fixture: registration forms — should PASS.
 *
 * WP-CLI accepts functions, methods and closures as command handlers, and
 * documentation through add_command() arguments instead of a docblock.
 */

// ✅ Function documented through add_command() arguments (no docblock).
WP_CLI::add_command(
    'acme ping',
    'acme_ping',
    [
        'shortdesc' => 'Check that the plugin responds.',
        'synopsis'  => [
            [ 'type' => 'flag', 'name' => 'loud', 'optional' => true ],
        ],
        'longdesc'  => '## EXAMPLES

    wp acme ping --loud',
    ]
);

function acme_ping( $args, $assoc_args ) {
    WP_CLI::success( 'pong' );
}

/**
 * Show plugin health.
 *
 * ## OPTIONS
 *
 * [--verbose]
 * : Show details.
 *
 * ## EXAMPLES
 *
 *     wp acme health --verbose
 *
 * @since 1.0.0
 */
WP_CLI::add_command( 'acme health', function ( $args, $assoc_args ) {
    // ✅ Closure documented by the docblock before add_command().
    WP_CLI::success( 'Healthy.' );
} );

/**
 * Print the plugin version.
 *
 * ## OPTIONS
 *
 * [--short]
 * : Number only.
 *
 * ## EXAMPLES
 *
 *     wp acme version --short
 *
 * @since 1.0.0
 */
\WP_CLI::add_command( 'acme version', static fn( $args, $assoc_args ) => WP_CLI::line( '1.0.0' ) );

/**
 * Configure the plugin.
 *
 * ## OPTIONS
 *
 * [--log-level=<level>]
 * : Log level.
 *
 * ## EXAMPLES
 *
 *     wp acme configure --log-level=debug
 *
 * @since 1.0.0
 */
WP_CLI::add_command( 'acme configure', function ( $args, $opts ) {
    // ✅ Closure assoc args (2nd parameter) are checked: kebab-case, via get_flag_value().
    $level = WP_CLI\Utils\get_flag_value( $opts, 'log-level', 'info' );
    WP_CLI::success( "Log level: {$level}" );
} );

class Acme_Registration_Command {

    public static function register() {
        WP_CLI::add_command(
            'acme stats',
            [ self::class, 'stats' ],
            [
                'shortdesc' => 'Show usage statistics.',
                'synopsis'  => [
                    [ 'type' => 'assoc', 'name' => 'days', 'optional' => true ],
                ],
                'longdesc'  => "## EXAMPLES\n\n    wp acme stats --days=7",
            ]
        );
    }

    // ✅ Method documented through the array-callable registration above.
    public static function stats( $args, $assoc_args ) {
        WP_CLI::log( 'Stats ready.' );
    }
}
