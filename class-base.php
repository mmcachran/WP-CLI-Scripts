<?php
/**
 * Base Migration class.
 *
 * @package WP CLI Scripts
 */

/**
 * CLI Migration Commands base class
 */
abstract class WP_CLI_Scripts_Migration_Base extends WP_CLI_Command {
	/**
	 * Caches queries for CLI commands.
	 *
	 * @var array
	 */
	protected static $queries = array();

	/**
	 * Ensure a run method exists in classes that extend this one.
	 *
	 * @return void
	 */
	abstract public function run();

	/**
	 * Performs the queries or returns cached queries.
	 *
	 * @param  array $args Arguments for the query.
	 * @return WP_Query     The query object.
	 */
	public static function get_query( $args = array() ) {
		// Default query args.
		$defaults = array(
			'post_type' 		=> array( 'post', 'page' ),
			'post_status' 		=> 'any',
			'posts_per_page'	=> -1,
		);

		// Compile query args.
		$args = wp_parse_args( $args, $defaults );

		// Get key for query to see if it exists in cache.
		$query_key = md5( json_encode( $args ) );

		// Bail early if we've already made this query.
		if ( isset( self::$queries[ $query_key ] ) ) {
			return self::$queries[ $query_key ];
		}

		// Cache the query.
		self::$queries[ $query_key ] = new WP_Query( $args );

		return self::$queries[ $query_key ];
	}

	/**
	 * Log output to the console.
	 *
	 * @param  string $string String to log.
	 * @param  string $type   Type to log it as.
	 * @return void
	 */
	protected static function log( $string, $type = 'success' ) {
		// Bail early if not a string.
		if ( ! is_string( $string ) ) {
			self::log( 'Attempting to log a non-string.', 'warning' );
			return;
		}

		switch ( $type ) {
			case 'error' :
				WP_CLI::error( $string );
				break;

			case 'warning' :
				WP_CLI::warning( $string );
				break;

			case 'log' :
				WP_CLI::log( $string );
				break;

			case 'line' :
				WP_CLI::line( $string );
				break;

			case 'debug' :
				WP_CLI::debug( $string );
				break;

			default :
				WP_CLI::success( $string );
				break;
		}
	}
}
