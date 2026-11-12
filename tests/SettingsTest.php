<?php
/**
 * Dernekyazilimi_Settings.
 *
 * @package Dernekyazilimi
 */

class SettingsTest extends Dernekyazilimi_Portal_TestCase {

	/**
	 * @var Dernekyazilimi_Settings
	 */
	private $settings;

	public function set_up() {
		parent::set_up();
		$this->settings = new Dernekyazilimi_Settings( new Dernekyazilimi_Client() );
		global $wp_settings_errors;
		$wp_settings_errors = array();
	}

	public function test_saves_https_address_without_trailing_slash() {
		$clean = $this->settings->sanitize(
			array(
				'portal_url' => ' https://portal.example.org/ ',
				'api_key'    => ' dy_new ',
			)
		);

		$this->assertSame(
			array(
				'portal_url' => 'https://portal.example.org',
				'api_key'    => 'dy_new',
			),
			$clean
		);
		$this->assertEmpty( get_settings_errors( 'dernekyazilimi' ) );
	}

	public function test_http_address_keeps_saved_one_and_warns() {
		$this->connect();

		$clean = $this->settings->sanitize( array( 'portal_url' => 'http://other.example.org' ) );

		$this->assertSame( self::PORTAL, $clean['portal_url'] );
		$this->assertSame( 'portal_url', get_settings_errors( 'dernekyazilimi' )[0]['code'] );
	}

	public function test_empty_address_clears_it() {
		$this->connect();

		$clean = $this->settings->sanitize( array( 'portal_url' => '' ) );

		$this->assertSame( '', $clean['portal_url'] );
		$this->assertEmpty( get_settings_errors( 'dernekyazilimi' ) );
	}

	public function test_empty_key_keeps_saved_one() {
		$this->connect();

		$clean = $this->settings->sanitize( array( 'portal_url' => self::PORTAL ) );

		$this->assertSame( self::KEY, $clean['api_key'] );
	}

	public function test_key_can_be_removed() {
		$this->connect();

		$clean = $this->settings->sanitize(
			array(
				'portal_url'     => self::PORTAL,
				'remove_api_key' => '1',
			)
		);

		$this->assertSame( '', $clean['api_key'] );
	}

	public function test_non_array_input() {
		$this->assertSame(
			array(
				'portal_url' => '',
				'api_key'    => '',
			),
			$this->settings->sanitize( 'junk' )
		);
	}

	public function test_saving_settings_forgets_cached_config() {
		$this->connect();
		$this->answer( 'config', 200, $this->config() );
		dernekyazilimi_client()->config();
		$this->assertNotFalse( get_transient( Dernekyazilimi_Client::CONFIG_KEY ) );

		update_option(
			Dernekyazilimi_Client::OPTION,
			array(
				'portal_url' => self::PORTAL,
				'api_key'    => 'dy_other',
			)
		);

		$this->assertFalse( get_transient( Dernekyazilimi_Client::CONFIG_KEY ) );
	}

	public function test_saved_key_is_not_printed() {
		$this->connect();

		ob_start();
		$this->settings->field_api_key();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( self::KEY, $html );
		$this->assertStringContainsString( 'remove_api_key', $html );
	}

	public function test_portal_field_links_to_installation_service() {
		ob_start();
		$this->settings->field_portal_url();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'https://www.kalehost.net/dernek-yazilimi', $html );
	}

	public function test_page_needs_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		ob_start();
		$this->settings->page();

		$this->assertSame( '', ob_get_clean() );
	}
}
