<?php
/**
 * Plugin Name:       Dernek Yazılımı
 * Plugin URI:        https://github.com/lkdtr/dernekyazilimi-wordpress
 * Description:       Document verification, donation, volunteer and membership forms for associations using the Dernek Yazılımı portal. Forms are drawn by your theme; payment and the rest of the application continue in a frame of the portal.
 * Version:           1.0.12
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Linux Kullanıcıları Derneği
 * Author URI:        https://www.lkd.org.tr
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dernekyazilimi
 * Domain Path:       /languages
 *
 * @package Dernekyazilimi
 */

defined( 'ABSPATH' ) || exit;

define( 'DERNEKYAZILIMI_VERSION', '1.0.12' );
define( 'DERNEKYAZILIMI_FILE', __FILE__ );
define( 'DERNEKYAZILIMI_DIR', plugin_dir_path( __FILE__ ) );
define( 'DERNEKYAZILIMI_URL', plugin_dir_url( __FILE__ ) );

require_once DERNEKYAZILIMI_DIR . 'includes/class-dernekyazilimi-client.php';
require_once DERNEKYAZILIMI_DIR . 'includes/class-dernekyazilimi-settings.php';
require_once DERNEKYAZILIMI_DIR . 'includes/class-dernekyazilimi-rest.php';
require_once DERNEKYAZILIMI_DIR . 'includes/class-dernekyazilimi-forms.php';

/**
 * The portal client shared by the plugin's parts.
 *
 * @return Dernekyazilimi_Client
 */
function dernekyazilimi_client() {
	static $client = null;

	if ( null === $client ) {
		$client = new Dernekyazilimi_Client();
	}

	return $client;
}

add_action(
	'plugins_loaded',
	static function () {
		( new Dernekyazilimi_Settings( dernekyazilimi_client() ) )->register();
		( new Dernekyazilimi_Rest( dernekyazilimi_client() ) )->register();
		( new Dernekyazilimi_Forms( dernekyazilimi_client() ) )->register();
	}
);

add_action(
	'init',
	static function () {
		// Until language packs from WordPress.org exist, use the bundled translation.
		load_plugin_textdomain( 'dernekyazilimi', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=dernekyazilimi' ) ) . '">' . esc_html__( 'Settings', 'dernekyazilimi' ) . '</a>' );

		return $links;
	}
);
