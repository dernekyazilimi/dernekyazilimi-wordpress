<?php
/**
 * Dernekyazilimi_Client.
 *
 * @package Dernekyazilimi
 */

class ClientTest extends Dernekyazilimi_Portal_TestCase {

	/**
	 * @var Dernekyazilimi_Client
	 */
	private $client;

	public function set_up() {
		parent::set_up();
		$this->client = new Dernekyazilimi_Client();
	}

	public function test_not_configured_without_settings() {
		$this->assertFalse( $this->client->configured() );
		$this->assertSame( '', $this->client->portal_url() );
		$this->assertSame( '', $this->client->portal_origin() );

		$config = $this->client->config();
		$this->assertWPError( $config );
		$this->assertSame( 'dernekyazilimi_not_configured', $config->get_error_code() );
		$this->assertEmpty( $this->requests );
	}

	public function test_portal_url_drops_trailing_slash() {
		update_option( Dernekyazilimi_Client::OPTION, array( 'portal_url' => 'https://portal.example.org/' ) );

		$this->assertSame( 'https://portal.example.org', $this->client->portal_url() );
	}

	public function test_portal_url_must_be_https() {
		update_option( Dernekyazilimi_Client::OPTION, array( 'portal_url' => 'http://portal.example.org' ) );

		$this->assertSame( '', $this->client->portal_url() );
	}

	public function test_portal_origin_keeps_port_and_drops_path() {
		update_option( Dernekyazilimi_Client::OPTION, array( 'portal_url' => 'https://portal.example.org:8443/sub' ) );

		$this->assertSame( 'https://portal.example.org:8443', $this->client->portal_origin() );
	}

	public function test_configured_needs_url_and_key() {
		update_option( Dernekyazilimi_Client::OPTION, array( 'portal_url' => self::PORTAL ) );
		$this->assertFalse( $this->client->configured() );

		$this->connect();
		$this->assertTrue( $this->client->configured() );
	}

	public function test_request_sends_key_site_and_visitor() {
		$this->connect();
		$_SERVER['REMOTE_ADDR']     = '198.51.100.7';
		$_SERVER['HTTP_USER_AGENT'] = 'TestBrowser/1.0';
		$this->answer( 'donations', 201, array( 'uuid' => 'x' ) );

		$response = $this->client->request( 'POST', 'donations', array( 'amount' => '100' ) );

		$this->assertSame( 201, $response['status'] );
		$this->assertSame( array( 'uuid' => 'x' ), $response['body'] );

		$sent = $this->last_request();
		$this->assertSame( self::PORTAL . '/api/site/donations', $sent['url'] );
		$this->assertSame( 'POST', $sent['args']['method'] );
		$this->assertSame( self::KEY, $sent['args']['headers']['X-Api-Key'] );
		$this->assertSame( home_url( '/', 'https' ), $sent['args']['headers']['X-Site-Url'] );
		$this->assertSame( '198.51.100.7', $sent['args']['headers']['X-Client-Ip'] );
		$this->assertSame( 'TestBrowser/1.0', $sent['args']['headers']['X-Client-User-Agent'] );
		$this->assertSame( 'application/json', $sent['args']['headers']['Content-Type'] );
		$this->assertSame( array( 'amount' => '100' ), $sent['body'] );
	}

	public function test_get_request_has_no_body_and_skips_invalid_ip() {
		$this->connect();
		$_SERVER['REMOTE_ADDR'] = 'not-an-ip';
		$this->answer( 'config', 200, array() );

		$this->client->request( 'GET', 'config' );

		$sent = $this->last_request();
		$this->assertNull( $sent['args']['body'] ?? null );
		$this->assertArrayNotHasKey( 'Content-Type', $sent['args']['headers'] );
		$this->assertArrayNotHasKey( 'X-Client-Ip', $sent['args']['headers'] );
	}

	public function test_request_reports_unreachable_portal() {
		$this->connect();
		$this->answer( 'config', new WP_Error( 'http_request_failed', 'timeout' ) );

		$response = $this->client->request( 'GET', 'config' );

		$this->assertWPError( $response );
		$this->assertSame( 'dernekyazilimi_unreachable', $response->get_error_code() );
	}

	public function test_request_tolerates_non_json_body() {
		$this->connect();
		$this->answer( 'config', 500, '<html>Server error</html>' );

		$response = $this->client->request( 'GET', 'config' );

		$this->assertSame( 500, $response['status'] );
		$this->assertSame( array(), $response['body'] );
	}

	public function test_config_is_cached() {
		$this->connect();
		$this->answer( 'config', 200, $this->config() );

		$first  = $this->client->config();
		$second = $this->client->config();

		$this->assertSame( $this->config(), $first );
		$this->assertSame( $first, $second );
		$this->assertCount( 1, $this->requests );

		$this->client->config( true );
		$this->assertCount( 2, $this->requests );
	}

	public function test_forget_config_clears_cache() {
		$this->connect();
		$this->answer( 'config', 200, $this->config() );
		$this->client->config();

		$this->client->forget_config();
		$this->client->config();

		$this->assertCount( 2, $this->requests );
	}

	public function test_config_error_is_not_cached() {
		$this->connect();
		$this->answer( 'config', 403, array( 'message' => 'Site not allowed' ) );

		$config = $this->client->config();

		$this->assertWPError( $config );
		$this->assertSame( 'dernekyazilimi_portal_error', $config->get_error_code() );
		$this->assertSame( 'Site not allowed', $config->get_error_message() );
		$this->assertFalse( get_transient( Dernekyazilimi_Client::CONFIG_KEY ) );
	}

	public function test_agreement_is_fetched_and_cached_per_key() {
		$this->connect();
		$text = array(
			'title'   => 'Ödeme koşulları',
			'content' => '<p>Metin</p>',
		);
		$this->answer( 'agreements/payment-terms', 200, $text );

		$this->assertSame( $text, $this->client->agreement( 'payment-terms' ) );
		$this->assertSame( $text, $this->client->agreement( 'payment-terms' ) );
		$this->assertCount( 1, $this->requests );
	}

	public function test_agreement_key_is_url_encoded() {
		$this->connect();
		$this->client->agreement( 'a/b' );

		$this->assertSame( self::PORTAL . '/api/site/agreements/a%2Fb', $this->last_request()['url'] );
	}

	public function test_missing_agreement() {
		$this->connect();
		$this->answer( 'agreements/none', 404 );

		$text = $this->client->agreement( 'none' );

		$this->assertWPError( $text );
		$this->assertSame( 'dernekyazilimi_no_agreement', $text->get_error_code() );
	}

	public function test_agreement_without_content_is_an_error() {
		$this->connect();
		$this->answer( 'agreements/payment-terms', 200, array( 'title' => 'x' ) );

		$this->assertWPError( $this->client->agreement( 'payment-terms' ) );
	}
}
