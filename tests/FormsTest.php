<?php
/**
 * Dernekyazilimi_Forms: shortcodes and blocks.
 *
 * @package Dernekyazilimi
 */

class FormsTest extends Dernekyazilimi_Portal_TestCase {

	public function set_up() {
		parent::set_up();
		wp_set_current_user( 0 );
		wp_dequeue_script( 'dernekyazilimi-forms' );
		wp_dequeue_script( 'dernekyazilimi-turnstile' );
	}

	/**
	 * Connect and let the portal answer with this configuration.
	 *
	 * @param array $config Configuration.
	 */
	private function portal( array $config ) {
		$this->connect();
		$this->answer( 'config', 200, $config );
	}

	private function as_admin() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function test_shortcodes_are_registered() {
		foreach ( array( 'verify', 'donate', 'volunteer', 'membership', 'agreement', 'payment_logos' ) as $name ) {
			$this->assertTrue( shortcode_exists( 'dernekyazilimi_' . $name ), $name );
		}
	}

	public function test_blocks_are_registered() {
		$blocks = array_filter(
			array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() ),
			static function ( $name ) {
				return 0 === strpos( $name, 'dernekyazilimi/' );
			}
		);

		$this->assertNotEmpty( $blocks );
	}

	/**
	 * @dataProvider forms
	 */
	public function test_visitor_sees_nothing_when_not_configured( $shortcode ) {
		$this->assertSame( '', do_shortcode( "[$shortcode]" ) );
	}

	/**
	 * @dataProvider forms
	 */
	public function test_admin_sees_reason_when_not_configured( $shortcode ) {
		$this->as_admin();

		$html = do_shortcode( "[$shortcode]" );

		$this->assertStringContainsString( 'dernekyazilimi-notice', $html );
		$this->assertStringContainsString( 'options-general.php?page=dernekyazilimi', $html );
	}

	/**
	 * @dataProvider forms
	 */
	public function test_visitor_sees_nothing_when_portal_is_down( $shortcode ) {
		$this->connect();
		$this->answer( 'config', 500, array() );

		$this->assertSame( '', do_shortcode( "[$shortcode]" ) );
	}

	/**
	 * @dataProvider forms
	 */
	public function test_admin_sees_module_off( $shortcode, $section ) {
		$config = $this->config();
		unset( $config[ $section ] );
		$this->portal( $config );
		$this->as_admin();

		$this->assertStringContainsString( 'This module is off in the portal.', do_shortcode( "[$shortcode]" ) );
	}

	/**
	 * @dataProvider forms
	 */
	public function test_closed_form_shows_notice_to_visitors( $shortcode, $section ) {
		$config                        = $this->config();
		$config[ $section ]['open'] = false;
		$this->portal( $config );

		$html = do_shortcode( "[$shortcode]" );

		$this->assertStringContainsString( 'dernekyazilimi-notice', $html );
		$this->assertStringNotContainsString( '<form', $html );
	}

	public function forms() {
		return array(
			'verify'     => array( 'dernekyazilimi_verify', 'correspondence' ),
			'donate'     => array( 'dernekyazilimi_donate', 'donation' ),
			'volunteer'  => array( 'dernekyazilimi_volunteer', 'volunteer' ),
			'membership' => array( 'dernekyazilimi_membership', 'membership' ),
		);
	}

	public function test_donation_form() {
		$this->portal( $this->config() );

		$html = do_shortcode( '[dernekyazilimi_donate cause="3" amount="250"]' );

		$this->assertStringContainsString( 'data-dy-form="donation"', $html );
		$this->assertStringContainsString( 'name="website"', $html, 'honeypot' );
		$this->assertMatchesRegularExpression( '/<option value="3"\s+selected/', $html );
		$this->assertMatchesRegularExpression( '/name="amount" min="10"[^>]*value="250"/', $html );
		$this->assertStringContainsString( 'data-dy-amount="100"', $html );
		$this->assertMatchesRegularExpression( '/name="method" value="card"\s+checked/', $html );
		$this->assertStringContainsString( 'value="transfer"', $html );
		$this->assertFalse( wp_script_is( 'dernekyazilimi-turnstile', 'enqueued' ) );
		$this->assertStringNotContainsString( self::KEY, $html );
		$this->assertTrue( wp_script_is( 'dernekyazilimi-forms', 'enqueued' ) );
	}

	public function test_donation_with_turnstile() {
		$config                                     = $this->config();
		$config['donation']['turnstile_site_key'] = '0xSITEKEY';
		$this->portal( $config );

		$html = do_shortcode( '[dernekyazilimi_donate]' );

		$this->assertStringContainsString( 'data-sitekey="0xSITEKEY"', $html );
		$this->assertTrue( wp_script_is( 'dernekyazilimi-turnstile', 'enqueued' ) );
	}

	public function test_donation_with_fixed_amounts_only() {
		$config                             = $this->config();
		$config['donation']['fixed_only'] = true;
		$this->portal( $config );

		$html = do_shortcode( '[dernekyazilimi_donate]' );

		$this->assertStringContainsString( 'type="radio" name="amount" value="250"', $html );
		$this->assertStringNotContainsString( 'type="number"', $html );
	}

	public function test_donation_intro_is_kses_filtered() {
		$config                        = $this->config();
		$config['donation']['intro'] = '<p>Destek olun</p><script>alert(1)</script>';
		$this->portal( $config );

		$html = do_shortcode( '[dernekyazilimi_donate]' );

		$this->assertStringContainsString( '<p>Destek olun</p>', $html );
		$this->assertStringNotContainsString( '<script>alert', $html );
	}

	public function test_portal_names_are_escaped() {
		$config                                   = $this->config();
		$config['donation']['causes'][0]['name'] = '<img src=x onerror=alert(1)>';
		$this->portal( $config );

		$html = do_shortcode( '[dernekyazilimi_donate]' );

		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringContainsString( '&lt;img src=x', $html );
	}

	public function test_volunteer_form_lists_consents() {
		$this->portal( $this->config() );

		$html = do_shortcode( '[dernekyazilimi_volunteer]' );

		$this->assertStringContainsString( 'data-dy-kind="volunteer"', $html );
		$this->assertStringContainsString( 'name="consents[email]"', $html );
		$this->assertStringContainsString( 'name="consents[sms]"', $html );
		$this->assertStringContainsString( 'name="phone_number"', $html );
	}

	public function test_membership_form() {
		$this->portal( $this->config() );

		$html = do_shortcode( '[dernekyazilimi_membership]' );

		$this->assertStringContainsString( 'data-dy-kind="membership"', $html );
		$this->assertStringContainsString( 'Continue to the application', $html );
	}

	public function test_verify_form() {
		$this->portal( $this->config() );

		$html = do_shortcode( '[dernekyazilimi_verify]' );

		$this->assertStringContainsString( 'name="kod"', $html );
		$this->assertStringContainsString( 'name="sayi"', $html );
	}

	public function test_agreement_text() {
		$this->connect();
		$this->answer(
			'agreements/payment-terms',
			200,
			array(
				'title'   => 'Ödeme koşulları',
				'content' => '<p>Metin</p><script>x()</script>',
			)
		);

		$html = do_shortcode( '[dernekyazilimi_agreement key="payment-terms" title="1"]' );

		$this->assertStringContainsString( '<h2>Ödeme koşulları</h2>', $html );
		$this->assertStringContainsString( '<p>Metin</p>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_agreement_without_title() {
		$this->connect();
		$this->answer( 'agreements/payment-terms', 200, array( 'title' => 'T', 'content' => '<p>x</p>' ) );

		$this->assertStringNotContainsString( '<h2>', do_shortcode( '[dernekyazilimi_agreement]' ) );
	}

	public function test_missing_agreement_is_hidden_from_visitors() {
		$this->connect();
		$this->answer( 'agreements/none', 404 );

		$this->assertSame( '', do_shortcode( '[dernekyazilimi_agreement key="none"]' ) );

		$this->as_admin();
		$this->assertStringContainsString( '(none)', do_shortcode( '[dernekyazilimi_agreement key="none"]' ) );
	}

	public function test_payment_logos() {
		$this->portal( $this->config() );

		$html = do_shortcode( '[dernekyazilimi_payment_logos]' );

		$this->assertStringContainsString( 'src="' . self::PORTAL . '/logos/band.svg"', $html );
		$this->assertStringContainsString( 'alt="iyzico"', $html );
	}

	public function test_no_payment_logos() {
		$config = $this->config();
		unset( $config['payment'] );
		$this->portal( $config );

		$this->assertSame( '', do_shortcode( '[dernekyazilimi_payment_logos]' ) );
	}

	public function test_script_gets_portal_origin_but_not_key() {
		$this->connect();
		// Register the assets again, now with the portal set.
		$GLOBALS['wp_scripts'] = null;
		foreach ( array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() ) as $name ) {
			if ( 0 === strpos( $name, 'dernekyazilimi/' ) ) {
				unregister_block_type( $name );
			}
		}
		( new Dernekyazilimi_Forms( dernekyazilimi_client() ) )->init();

		$data = wp_scripts()->get_data( 'dernekyazilimi-forms', 'data' );

		$this->assertStringContainsString( '"portalOrigin":"' . self::PORTAL . '"', $data );
		$this->assertStringNotContainsString( self::KEY, $data );
	}
}
