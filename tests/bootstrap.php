<?php
/**
 * PHPUnit bootstrap: loads the WordPress test suite and the plugin.
 *
 * Run inside wp-env (`npm test`), which provides WP_TESTS_DIR and the test database.
 *
 * @package Dernekyazilimi
 */

$tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $tests_dir ) {
	$tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}
if ( ! file_exists( $tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test suite not found in {$tests_dir}. Run the tests with `npm test` (wp-env).\n" );
	exit( 1 );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/dernekyazilimi.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/class-portal-testcase.php';
