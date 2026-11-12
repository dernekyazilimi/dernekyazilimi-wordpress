<?php
/**
 * Dernekyazilimi_Rest: the endpoints the forms post to.
 *
 * @package Dernekyazilimi
 */

class RestTest extends Dernekyazilimi_Portal_TestCase {

	const CODE = '0a1b2c3d-4e5f-6a7b-8c9d-0e1f2a3b4c5d';

	public function set_up() {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	/**
	 * POST to an endpoint of the plugin.
	 *
	 * @param string $route  Route under /dernekyazilimi/v1.
	 * @param array  $params Body parameters.
	 * @return WP_REST_Response
	 */
	private function post( $route, array $params = array() ) {
		$request = new WP_REST_Request( 'POST', '/dernekyazilimi/v1' . $route );
		$request->set_body_params( $params );

		return rest_get_server()->dispatch( $request );
	}

	public function test_routes_are_registered() {
		$routes = rest_get_server()->get_routes( 'dernekyazilimi/v1' );

		foreach ( array( '/documents/verify', '/donations', '/phone/send', '/phone/verify', '/accounts' ) as $route ) {
			$this->assertArrayHasKey( '/dernekyazilimi/v1' . $route, $routes );
		}
	}

	public function test_get_is_not_allowed() {
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/dernekyazilimi/v1/donations' ) );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_unconfigured_form_answers_503() {
		$response = $this->post( '/donations', array( 'amount' => '100' ) );

		$this->assertSame( 503, $response->get_status() );
		$this->assertEmpty( $this->requests );
	}

	public function test_honeypot_rejects_bots() {
		$this->connect();

		$response = $this->post( '/donations', array( 'website' => 'spam.example' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertEmpty( $this->requests );
	}

	public function test_requests_per_minute_are_limited() {
		$this->connect();
		$this->answer( 'phone-verifications', 200, array( 'status' => 'sent' ) );

		for ( $i = 0; $i < Dernekyazilimi_Rest::REQUESTS_PER_MINUTE; $i++ ) {
			$this->assertSame( 200, $this->post( '/phone/send', array( 'phone_number' => '05321234567' ) )->get_status() );
		}

		$response = $this->post( '/phone/send', array( 'phone_number' => '05321234567' ) );
		$this->assertSame( 429, $response->get_status() );
		$this->assertCount( Dernekyazilimi_Rest::REQUESTS_PER_MINUTE, $this->requests );
	}

	public function test_limit_is_per_visitor() {
		$this->connect();
		$this->answer( 'phone-verifications', 200, array( 'status' => 'sent' ) );
		$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
		for ( $i = 0; $i < Dernekyazilimi_Rest::REQUESTS_PER_MINUTE; $i++ ) {
			$this->post( '/phone/send' );
		}

		$_SERVER['REMOTE_ADDR'] = '192.0.2.2';
		$this->assertSame( 200, $this->post( '/phone/send' )->get_status() );
	}

	public function test_donation_sends_clean_values() {
		$this->connect();
		$this->answer( 'donations', 201, array( 'uuid' => 'u-1' ) );

		$this->post(
			'/donations',
			array(
				'amount'        => '250,50',
				'cause_id'      => '3',
				'name'          => '<b>Ayşe</b> Yılmaz',
				'email'         => 'ayse@example.org',
				'phone'         => '0532 123 45 67',
				'message'       => "Satır 1\nSatır 2",
				'hide_name'     => '1',
				'method'        => 'card',
				'agreement'     => '1',
				'payment_terms' => 'true',
				'turnstile'     => 'token',
			)
		);

		$this->assertSame( self::PORTAL . '/api/site/donations', $this->last_request()['url'] );
		$this->assertSame(
			array(
				'amount'        => '250.50',
				'cause_id'      => 3,
				'name'          => 'Ayşe Yılmaz',
				'email'         => 'ayse@example.org',
				'phone'         => '0532 123 45 67',
				'message'       => "Satır 1\nSatır 2",
				'hide_name'     => true,
				'method'        => 'card',
				'agreement'     => true,
				'payment_terms' => true,
				'turnstile'     => 'token',
			),
			$this->last_request()['body']
		);
	}

	public function test_donation_without_cause_or_consent_sends_null() {
		$this->connect();
		$this->answer( 'donations', 201, array() );

		$this->post( '/donations', array( 'amount' => '100' ) );

		$body = $this->last_request()['body'];
		$this->assertNull( $body['cause_id'] );
		$this->assertNull( $body['agreement'] );
		$this->assertNull( $body['payment_terms'] );
		$this->assertFalse( $body['hide_name'] );
	}

	public function test_donation_answer_keeps_only_allowed_https_fields() {
		$this->connect();
		$this->answer(
			'donations',
			201,
			array(
				'uuid'       => 'u-1',
				'method'     => 'card',
				'reference'  => 'R1',
				'frame_url'  => self::PORTAL . '/pay/u-1',
				'result_url' => 'javascript:alert(1)',
				'api_key'    => 'leak',
				'donor'      => array( 'email' => 'x@example.org' ),
			)
		);

		$response = $this->post( '/donations', array( 'amount' => '100' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'uuid'       => 'u-1',
				'method'     => 'card',
				'reference'  => 'R1',
				'frame_url'  => self::PORTAL . '/pay/u-1',
				'result_url' => '',
			),
			$response->get_data()
		);
	}

	public function test_validation_errors_are_passed_on_sanitized() {
		$this->connect();
		$this->answer(
			'donations',
			422,
			array(
				'message' => 'The amount is <b>too low</b>.',
				'errors'  => array(
					'amount'   => array( 'Minimum 10 TL.' ),
					'Bad Key!' => 'single <i>message</i>',
				),
			)
		);

		$response = $this->post( '/donations', array( 'amount' => '1' ) );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame(
			array(
				'message' => 'The amount is too low.',
				'errors'  => array(
					'amount' => array( 'Minimum 10 TL.' ),
					'badkey' => array( 'single message' ),
				),
			),
			$response->get_data()
		);
	}

	public function test_portal_rate_limit_is_passed_on() {
		$this->connect();
		$this->answer( 'donations', 429, array( 'message' => 'slow down' ) );

		$response = $this->post( '/donations' );

		$this->assertSame( 429, $response->get_status() );
		$this->assertStringNotContainsString( 'slow down', $response->get_data()['message'] );
	}

	/**
	 * @dataProvider portal_failures
	 */
	public function test_portal_failures_hide_details( $status, $body ) {
		$this->connect();
		$this->answer( 'donations', $status, $body );

		$response = $this->post( '/donations' );

		$this->assertSame( 502, $response->get_status() );
		$this->assertSame( array( 'message' ), array_keys( $response->get_data() ) );
		$this->assertStringNotContainsString( 'secret', $response->get_data()['message'] );
	}

	public function portal_failures() {
		return array(
			'wrong key'        => array( 401, array( 'message' => 'secret: invalid key' ) ),
			'site not allowed' => array( 403, array( 'message' => 'secret: site' ) ),
			'server error'     => array( 500, array( 'message' => 'secret: trace' ) ),
		);
	}

	public function test_unreachable_portal_answers_502() {
		$this->connect();
		$this->answer( 'donations', new WP_Error( 'http_request_failed', 'timeout' ) );

		$this->assertSame( 502, $this->post( '/donations' )->get_status() );
	}

	/**
	 * @dataProvider phone_numbers
	 */
	public function test_phone_is_normalized( $typed, $sent ) {
		$this->connect();
		$this->answer( 'phone-verifications', 200, array( 'status' => 'sent' ) );

		$this->post( '/phone/send', array( 'phone_number' => $typed ) );

		$this->assertSame( array( 'phone_number' => $sent ), $this->last_request()['body'] );
	}

	public function phone_numbers() {
		return array(
			'leading zero'      => array( '0532 123 45 67', '905321234567' ),
			'without zero'      => array( '(532) 123-4567', '905321234567' ),
			'with country code' => array( '+90 532 123 45 67', '905321234567' ),
			'foreign'           => array( '+49 151 2345678', '491512345678' ),
		);
	}

	public function test_phone_verify_sends_digits_of_code() {
		$this->connect();
		$this->answer( 'phone-verifications/verify', 200, array( 'status' => 'verified', 'token' => 'x' ) );

		$response = $this->post(
			'/phone/verify',
			array(
				'phone_number' => '05321234567',
				'code'         => '12 34-56',
			)
		);

		$this->assertSame(
			array(
				'phone_number' => '905321234567',
				'code'         => '123456',
			),
			$this->last_request()['body']
		);
		$this->assertSame( array( 'status' => 'verified' ), $response->get_data() );
	}

	public function test_account_for_volunteer() {
		$this->connect();
		$this->answer( 'accounts', 201, array( 'created' => true, 'continue_url' => null, 'user_id' => 9 ) );

		$response = $this->post(
			'/accounts',
			array(
				'name'         => 'Ali',
				'surname'      => 'Veli',
				'email'        => 'ali@example.org',
				'phone_number' => '05321234567',
				'consents'     => array(
					'email' => '1',
					'fax'   => '1',
				),
				'agreement'    => '1',
				'form'         => 'volunteer',
			)
		);

		$this->assertSame(
			array(
				'name'         => 'Ali',
				'surname'      => 'Veli',
				'email'        => 'ali@example.org',
				'phone_number' => '905321234567',
				'consents'     => array(
					'email'    => true,
					'sms'      => false,
					'whatsapp' => false,
				),
				'agreement'    => true,
				'continue'     => null,
			),
			$this->last_request()['body']
		);
		$this->assertSame(
			array(
				'created'      => true,
				'continue_url' => null,
			),
			$response->get_data()
		);
	}

	public function test_account_for_membership_continues_in_portal() {
		$this->connect();
		$this->answer( 'accounts', 201, array( 'created' => true, 'continue_url' => self::PORTAL . '/apply' ) );

		$response = $this->post( '/accounts', array( 'form' => 'membership' ) );

		$this->assertSame( 'membership', $this->last_request()['body']['continue'] );
		$this->assertSame( self::PORTAL . '/apply', $response->get_data()['continue_url'] );
	}

	/**
	 * @dataProvider bad_codes
	 */
	public function test_document_verify_rejects_bad_codes( $code ) {
		$this->connect();

		$response = $this->post( '/documents/verify', array( 'kod' => $code ) );

		$this->assertSame( 422, $response->get_status() );
		$this->assertEmpty( $this->requests );
	}

	public function bad_codes() {
		return array(
			'empty'        => array( '' ),
			'short'        => array( '0a1b2c3d' ),
			'not hex'      => array( 'zzzzzzzz-4e5f-6a7b-8c9d-0e1f2a3b4c5d' ),
			'trailing'     => array( self::CODE . "\n" . 'x' ),
			'injected'     => array( self::CODE . '&sayi=1' ),
		);
	}

	public function test_document_verify_with_code_only() {
		$this->connect();
		$this->answer( 'documents/verify', 200, array( 'document_no' => 'E-1' ) );

		$response = $this->post( '/documents/verify', array( 'kod' => ' ' . self::CODE . ' ' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'GET', $this->last_request()['args']['method'] );
		$this->assertSame(
			self::PORTAL . '/api/site/documents/verify?kod=' . strtoupper( self::CODE ),
			$this->last_request()['url']
		);
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
	}

	public function test_document_verify_with_number_and_turnstile() {
		$this->connect();
		$this->answer( 'documents/verify', 200, array() );

		$this->post(
			'/documents/verify',
			array(
				'kod'       => self::CODE,
				'sayi'      => 'E-2026/15 & co',
				'turnstile' => 'tok en',
			)
		);

		$this->assertSame(
			self::PORTAL . '/api/site/documents/verify?kod=' . strtoupper( self::CODE ) . '&sayi=E-2026%2F15%20%26%20co&turnstile=tok%20en',
			$this->last_request()['url']
		);
	}

	public function test_document_verify_answer_is_filtered() {
		$this->connect();
		$this->answer(
			'documents/verify',
			200,
			array(
				'document_no'   => 'E-1',
				'document_date' => '2026-11-01',
				'subject'       => 'Davet',
				'status'        => 'active',
				'preview_url'   => self::PORTAL . '/d/1/preview',
				'download_url'  => 'http://insecure.example/d.pdf',
				'original_url'  => self::PORTAL . '/d/1/original',
				'content'       => 'full text must not leak',
				'attachments'   => array(
					array(
						'name' => '<b>Ek-1</b>.pdf',
						'url'  => self::PORTAL . '/a/1',
						'size' => 10,
					),
					array( 'name' => 'Ek-2.pdf' ),
					'not an array',
				),
			)
		);

		$data = $this->post( '/documents/verify', array( 'kod' => self::CODE ) )->get_data();

		$this->assertArrayNotHasKey( 'content', $data );
		$this->assertSame( '', $data['download_url'] );
		$this->assertSame( self::PORTAL . '/d/1/preview', $data['preview_url'] );
		$this->assertSame(
			array(
				array(
					'name' => 'Ek-1.pdf',
					'url'  => self::PORTAL . '/a/1',
				),
				array(
					'name' => 'Ek-2.pdf',
					'url'  => null,
				),
			),
			array_values( $data['attachments'] )
		);
	}

	public function test_document_not_found() {
		$this->connect();
		$this->answer( 'documents/verify', 404, array( 'message' => 'missing' ) );

		$response = $this->post( '/documents/verify', array( 'kod' => self::CODE ) );

		$this->assertSame( 404, $response->get_status() );
		$this->assertNotSame( 'missing', $response->get_data()['message'] );
	}

	public function test_api_key_never_reaches_the_answer() {
		$this->connect();
		$this->answer( 'accounts', 500, array( 'message' => self::KEY ) );

		$this->assertStringNotContainsString( self::KEY, wp_json_encode( $this->post( '/accounts' )->get_data() ) );
	}
}
