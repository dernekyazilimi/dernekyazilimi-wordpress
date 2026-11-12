<?php
/**
 * Settings page: the portal address and the API key.
 *
 * @package Dernekyazilimi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings → Dernek Yazılımı.
 */
class Dernekyazilimi_Settings {

	/**
	 * Portal client.
	 *
	 * @var Dernekyazilimi_Client
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param Dernekyazilimi_Client $client Portal client.
	 */
	public function __construct( Dernekyazilimi_Client $client ) {
		$this->client = $client;
	}

	/**
	 * Hook into WordPress.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'settings' ) );
		add_action( 'update_option_' . Dernekyazilimi_Client::OPTION, array( $this->client, 'forget_config' ) );
		add_action( 'add_option_' . Dernekyazilimi_Client::OPTION, array( $this->client, 'forget_config' ) );
	}

	/**
	 * Add the page under Settings.
	 */
	public function menu() {
		add_options_page( __( 'Dernek Yazılımı', 'dernekyazilimi' ), __( 'Dernek Yazılımı', 'dernekyazilimi' ), 'manage_options', 'dernekyazilimi', array( $this, 'page' ) );
	}

	/**
	 * Register the option and its fields.
	 */
	public function settings() {
		register_setting(
			'dernekyazilimi',
			Dernekyazilimi_Client::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section( 'dernekyazilimi_connection', __( 'Portal connection', 'dernekyazilimi' ), '__return_false', 'dernekyazilimi' );
		add_settings_field( 'dernekyazilimi_portal_url', __( 'Portal address', 'dernekyazilimi' ), array( $this, 'field_portal_url' ), 'dernekyazilimi', 'dernekyazilimi_connection', array( 'label_for' => 'dernekyazilimi_portal_url' ) );
		add_settings_field( 'dernekyazilimi_api_key', __( 'API key', 'dernekyazilimi' ), array( $this, 'field_api_key' ), 'dernekyazilimi', 'dernekyazilimi_connection', array( 'label_for' => 'dernekyazilimi_api_key' ) );
	}

	/**
	 * Clean the submitted settings; an empty key keeps the saved one.
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public function sanitize( $input ) {
		$saved = get_option( Dernekyazilimi_Client::OPTION, array() );
		$input = is_array( $input ) ? $input : array();

		$url = untrailingslashit( esc_url_raw( trim( (string) ( $input['portal_url'] ?? '' ) ), array( 'https' ) ) );
		if ( '' === $url && '' !== trim( (string) ( $input['portal_url'] ?? '' ) ) ) {
			add_settings_error( 'dernekyazilimi', 'portal_url', __( 'The portal address must start with https://.', 'dernekyazilimi' ) );
			$url = $saved['portal_url'] ?? '';
		}

		$key = sanitize_text_field( (string) ( $input['api_key'] ?? '' ) );
		if ( '' === $key ) {
			$key = empty( $input['remove_api_key'] ) ? ( $saved['api_key'] ?? '' ) : '';
		}

		return array(
			'portal_url' => $url,
			'api_key'    => $key,
		);
	}

	/**
	 * Portal address field.
	 */
	public function field_portal_url() {
		if ( defined( 'DERNEKYAZILIMI_PORTAL_URL' ) ) {
			echo '<code>' . esc_html( $this->client->portal_url() ) . '</code> <span class="description">' . esc_html__( 'Set in wp-config.php.', 'dernekyazilimi' ) . '</span>';

			return;
		}
		printf(
			'<input type="url" class="regular-text code" id="dernekyazilimi_portal_url" name="%1$s[portal_url]" value="%2$s" placeholder="https://portal.example.org">',
			esc_attr( Dernekyazilimi_Client::OPTION ),
			esc_attr( $this->client->portal_url() )
		);
		echo '<p class="description">' . esc_html__( 'Address of your association\'s Dernek Yazılımı portal.', 'dernekyazilimi' ) . '</p>';
		printf(
			'<p class="description">%1$s <a href="%2$s" target="_blank" rel="noopener">%3$s</a></p>',
			esc_html__( 'No portal yet? The software is free; if you want it set up for you, a paid installation service is available.', 'dernekyazilimi' ),
			esc_url( 'https://www.kalehost.net/dernek-yazilimi' ),
			esc_html__( 'Installation service', 'dernekyazilimi' )
		);
	}

	/**
	 * API key field; the saved key is never printed.
	 */
	public function field_api_key() {
		if ( defined( 'DERNEKYAZILIMI_API_KEY' ) ) {
			echo '<span class="description">' . esc_html__( 'Set in wp-config.php.', 'dernekyazilimi' ) . '</span>';

			return;
		}
		$has_key = '' !== $this->client->api_key();
		printf(
			'<input type="password" class="regular-text code" id="dernekyazilimi_api_key" name="%1$s[api_key]" value="" autocomplete="new-password" placeholder="%2$s">',
			esc_attr( Dernekyazilimi_Client::OPTION ),
			esc_attr( $has_key ? __( 'Saved — leave empty to keep it', 'dernekyazilimi' ) : 'dy_…' )
		);
		echo '<p class="description">' . esc_html__( 'Made in the portal: Admin panel → Settings → Organization settings → Web site connection. This site\'s address must also be listed there under the sites allowed to embed pages.', 'dernekyazilimi' ) . '</p>';
		if ( $has_key ) {
			printf(
				'<p><label><input type="checkbox" name="%1$s[remove_api_key]" value="1"> %2$s</label></p>',
				esc_attr( Dernekyazilimi_Client::OPTION ),
				esc_html__( 'Remove the saved key', 'dernekyazilimi' )
			);
		}
	}

	/**
	 * The settings page.
	 */
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'dernekyazilimi' );
				do_settings_sections( 'dernekyazilimi' );
				submit_button();
				?>
			</form>

			<h2><?php esc_html_e( 'Connection status', 'dernekyazilimi' ); ?></h2>
			<?php $this->status(); ?>

			<h2><?php esc_html_e( 'Forms', 'dernekyazilimi' ); ?></h2>
			<p><?php esc_html_e( 'Add a form to any page with its block (search for “Dernek Yazılımı” in the block inserter) or its shortcode:', 'dernekyazilimi' ); ?></p>
			<table class="widefat striped" style="max-width: 760px;">
				<tbody>
					<tr><td><code>[dernekyazilimi_verify]</code></td><td><?php esc_html_e( 'Verify an official letter using its code.', 'dernekyazilimi' ); ?></td></tr>
					<tr><td><code>[dernekyazilimi_donate]</code></td><td><?php esc_html_e( 'Donation form. Optional: cause="3" amount="250".', 'dernekyazilimi' ); ?></td></tr>
					<tr><td><code>[dernekyazilimi_volunteer]</code></td><td><?php esc_html_e( 'Volunteer registration form.', 'dernekyazilimi' ); ?></td></tr>
					<tr><td><code>[dernekyazilimi_membership]</code></td><td><?php esc_html_e( 'Membership application: opens the account here, the application continues in a frame.', 'dernekyazilimi' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Ask the portal and say what it answered.
	 */
	private function status() {
		if ( ! $this->client->configured() ) {
			echo '<p>' . esc_html__( 'Enter the portal address and the API key first.', 'dernekyazilimi' ) . '</p>';

			return;
		}

		$config = $this->client->config( true );
		if ( is_wp_error( $config ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $config->get_error_message() ) . '</p></div>';

			return;
		}

		echo '<div class="notice notice-success inline"><p>';
		/* translators: %s: name of the association. */
		echo esc_html( sprintf( __( 'Connected to the portal of %s.', 'dernekyazilimi' ), $config['organization']['name'] ?? '' ) );
		echo '</p></div><ul class="ul-disc">';
		$forms = array(
			'correspondence' => __( 'Document verification', 'dernekyazilimi' ),
			'donation'   => __( 'Donation', 'dernekyazilimi' ),
			'volunteer'  => __( 'Volunteer registration', 'dernekyazilimi' ),
			'membership' => __( 'Membership application', 'dernekyazilimi' ),
		);
		foreach ( $forms as $key => $label ) {
			if ( ! isset( $config[ $key ] ) ) {
				$state = __( 'module is off in the portal', 'dernekyazilimi' );
			} elseif ( empty( $config[ $key ]['open'] ) ) {
				$state = __( 'closed in the portal', 'dernekyazilimi' );
			} else {
				$state = __( 'open', 'dernekyazilimi' );
			}
			echo '<li>' . esc_html( $label . ': ' . $state ) . '</li>';
		}
		echo '</ul>';
	}
}
