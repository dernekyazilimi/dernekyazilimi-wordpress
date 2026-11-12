<?php
/**
 * Base test case: a fake portal answers every HTTP request the plugin makes.
 *
 * @package Dernekyazilimi
 */

/**
 * Records requests to the portal and answers them from a queue.
 */
abstract class Dernekyazilimi_Portal_TestCase extends WP_UnitTestCase {

	const PORTAL = 'https://portal.example.org';
	const KEY    = 'dy_test_key';

	/**
	 * Requests the plugin sent: url and the arguments of wp_remote_request().
	 *
	 * @var array[]
	 */
	protected $requests = array();

	/**
	 * Answers by path under /api/site (without the query string).
	 *
	 * @var array
	 */
	protected $answers = array();

	public function set_up() {
		parent::set_up();
		$this->requests = array();
		$this->answers  = array();
		add_filter( 'pre_http_request', array( $this, 'fake_portal' ), 10, 3 );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand( 1, 254 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_portal' ), 10 );
		delete_option( Dernekyazilimi_Client::OPTION );
		dernekyazilimi_client()->forget_config();
		unset( $_SERVER['HTTP_USER_AGENT'] );
		parent::tear_down();
	}

	/**
	 * Save the portal address and the key.
	 */
	protected function connect() {
		update_option(
			Dernekyazilimi_Client::OPTION,
			array(
				'portal_url' => self::PORTAL,
				'api_key'    => self::KEY,
			)
		);
	}

	/**
	 * Make the portal answer a path.
	 *
	 * @param string           $path   Path under /api/site, without the query string.
	 * @param int|WP_Error     $status HTTP status, or an error for an unreachable portal.
	 * @param array|string     $body   JSON body (array) or raw text.
	 */
	protected function answer( $path, $status, $body = array() ) {
		$this->answers[ $path ] = array( $status, $body );
	}

	/**
	 * The last request sent to the portal.
	 *
	 * @return array{url:string,args:array,body:mixed}
	 */
	protected function last_request() {
		$this->assertNotEmpty( $this->requests, 'No request reached the portal.' );

		return end( $this->requests );
	}

	/**
	 * pre_http_request filter.
	 *
	 * @param false|array $pre  Short-circuit value.
	 * @param array       $args Request arguments.
	 * @param string      $url  Request URL.
	 * @return array|WP_Error
	 */
	public function fake_portal( $pre, $args, $url ) {
		$this->requests[] = array(
			'url'  => $url,
			'args' => $args,
			'body' => isset( $args['body'] ) ? json_decode( $args['body'], true ) : null,
		);

		$prefix = self::PORTAL . '/api/site/';
		$path   = 0 === strpos( $url, $prefix ) ? strtok( substr( $url, strlen( $prefix ) ), '?' ) : $url;
		if ( ! isset( $this->answers[ $path ] ) ) {
			return new WP_Error( 'http_request_failed', 'No fake answer for ' . $url );
		}

		list( $status, $body ) = $this->answers[ $path ];
		if ( is_wp_error( $status ) ) {
			return $status;
		}

		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => is_array( $body ) ? wp_json_encode( $body ) : $body,
			'response' => array(
				'code'    => $status,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * A portal configuration with every form open.
	 *
	 * @return array
	 */
	protected function config() {
		return array(
			'organization'   => array( 'name' => 'Örnek Derneği' ),
			'correspondence' => array( 'open' => true ),
			'donation'       => array(
				'open'    => true,
				'amounts' => array( 100, 250 ),
				'minimum' => 10,
				'methods' => array(
					array(
						'key'   => 'card',
						'label' => 'Kredi kartı',
					),
					array(
						'key'   => 'transfer',
						'label' => 'Havale',
					),
				),
				'causes'  => array(
					array(
						'id'   => 3,
						'name' => 'Eğitim',
					),
				),
			),
			'volunteer'      => array( 'open' => true ),
			'membership'     => array( 'open' => true ),
			'registration'   => array(
				'consents' => array(
					'email' => 'E-posta',
					'sms'   => 'SMS',
				),
			),
			'payment'        => array(
				'logos' => array(
					array(
						'url'   => self::PORTAL . '/logos/band.svg',
						'label' => 'iyzico',
					),
				),
			),
		);
	}
}
