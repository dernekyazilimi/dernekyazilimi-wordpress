<?php
/**
 * The forms: shortcodes, blocks and their markup.
 *
 * @package Dernekyazilimi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Donation, volunteer and membership forms; agreement texts and payment logos.
 */
class Dernekyazilimi_Forms {

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
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Register assets, shortcodes and blocks.
	 */
	public function init() {
		wp_register_style( 'dernekyazilimi-forms', DERNEKYAZILIMI_URL . 'assets/css/forms.css', array(), DERNEKYAZILIMI_VERSION );
		wp_register_script(
			'dernekyazilimi-forms',
			DERNEKYAZILIMI_URL . 'assets/js/forms.js',
			array(),
			DERNEKYAZILIMI_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script(
			'dernekyazilimi-forms',
			'dernekyazilimiForms',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'dernekyazilimi/v1/' ) ),
				'portalOrigin' => $this->client->portal_origin(),
				'i18n'         => array(
					'sending'      => __( 'Sending…', 'dernekyazilimi' ),
					'failed'       => __( 'The form could not be sent. Please check your connection and try again.', 'dernekyazilimi' ),
					'required'     => __( 'Please fill in this field.', 'dernekyazilimi' ),
					'codeSent'     => __( 'We sent a code by SMS. Enter it below.', 'dernekyazilimi' ),
					'codeNeeded'   => __( 'Enter the 6-digit code.', 'dernekyazilimi' ),
					'verified'     => __( 'Your phone number is verified.', 'dernekyazilimi' ),
					'verifyFirst'  => __( 'Please verify your phone number first.', 'dernekyazilimi' ),
					'sendAgain'    => __( 'Send again', 'dernekyazilimi' ),
					'existingNote' => __( 'This email already has a portal account. Sign in below to continue.', 'dernekyazilimi' ),
					'closePayment' => __( 'Close the payment window? If you have not finished paying, your donation will not be completed.', 'dernekyazilimi' ),
					'humanCheck'   => __( 'Waiting for the security check…', 'dernekyazilimi' ),
				),
			)
		);

		// Cloudflare Turnstile, drawn by forms.js once the script has loaded.
		wp_register_script( 'dernekyazilimi-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=dernekyazilimiTurnstile', array( 'dernekyazilimi-forms' ), null, array( 'in_footer' => true, 'strategy' => 'defer' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Cloudflare asks that the script is not versioned or cached.

		add_shortcode( 'dernekyazilimi_verify', array( $this, 'verify' ) );
		add_shortcode( 'dernekyazilimi_donate', array( $this, 'donate' ) );
		add_shortcode( 'dernekyazilimi_volunteer', array( $this, 'volunteer' ) );
		add_shortcode( 'dernekyazilimi_membership', array( $this, 'membership' ) );
		add_shortcode( 'dernekyazilimi_agreement', array( $this, 'agreement' ) );
		add_shortcode( 'dernekyazilimi_payment_logos', array( $this, 'payment_logos' ) );

		$this->blocks();
	}

	/**
	 * Blocks that draw the same forms.
	 */
	private function blocks() {
		wp_register_script( 'dernekyazilimi-blocks', DERNEKYAZILIMI_URL . 'assets/js/blocks.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ), DERNEKYAZILIMI_VERSION, true );
		wp_localize_script(
			'dernekyazilimi-blocks',
			'dernekyazilimiBlocks',
			array(
				'donate'     => array(
					'title'       => __( 'Donation form', 'dernekyazilimi' ),
					'description' => __( 'Donation form of your association\'s portal.', 'dernekyazilimi' ),
				),
				'volunteer'  => array(
					'title'       => __( 'Volunteer form', 'dernekyazilimi' ),
					'description' => __( 'Volunteer registration form of your association\'s portal.', 'dernekyazilimi' ),
				),
				'membership' => array(
					'title'       => __( 'Membership application', 'dernekyazilimi' ),
					'description' => __( 'Membership application of your association\'s portal.', 'dernekyazilimi' ),
				),
				'verify' => array(
					'title' => __( 'Document verification', 'dernekyazilimi' ),
					'description' => __( 'Verify an official letter using its code.', 'dernekyazilimi' ),
				),
				'labels'     => array(
					'settings' => __( 'Form settings', 'dernekyazilimi' ),
					'cause'    => __( 'Donation cause number (optional)', 'dernekyazilimi' ),
					'amount'   => __( 'Amount filled in at first (optional)', 'dernekyazilimi' ),
				),
			)
		);

		$blocks = array(
			'donate'     => array(
				'cause'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'amount' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'verify' => array(),
			'volunteer'  => array(),
			'membership' => array(),
		);
		foreach ( $blocks as $name => $attributes ) {
			register_block_type(
				'dernekyazilimi/' . $name,
				array(
					'api_version'           => 3,
					'attributes'            => $attributes,
					'editor_script_handles' => array( 'dernekyazilimi-blocks' ),
					'style_handles'         => array( 'dernekyazilimi-forms' ),
					'render_callback'       => array( $this, $name ),
					'supports'              => array(
						'html'  => false,
						'align' => array( 'wide', 'full' ),
					),
				)
			);
		}
	}

	/** [dernekyazilimi_verify] — query-string codes are read in JS to work with page caches. */
	public function verify( $atts = array() ) {
		$config = $this->section( 'correspondence', __( 'Document verification is currently unavailable.', 'dernekyazilimi' ) );
		if ( is_string( $config ) ) {
			return $config;
		}
		wp_enqueue_style( 'dernekyazilimi-forms' );
		wp_enqueue_script( 'dernekyazilimi-forms' );
		$id = wp_unique_id( 'dy-verify-' );
		ob_start();
		?>
		<div class="dernekyazilimi dy-verify" data-dy-verify>
			<form class="dy-form">
				<?php $this->honeypot(); ?>
				<div class="dy-field">
					<label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Document verification code', 'dernekyazilimi' ); ?></label>
					<input id="<?php echo esc_attr( $id ); ?>" name="kod" maxlength="36" autocomplete="off" spellcheck="false" placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX" required>
					<p class="dy-hint"><?php esc_html_e( 'Enter the code printed at the bottom of the letter.', 'dernekyazilimi' ); ?></p>
				</div>
				<div class="dy-field">
					<label for="<?php echo esc_attr( $id ); ?>-number"><?php esc_html_e( 'Document number', 'dernekyazilimi' ); ?></label>
					<input id="<?php echo esc_attr( $id ); ?>-number" name="sayi" maxlength="120" autocomplete="off" spellcheck="false" required>
					<p class="dy-hint"><?php esc_html_e( 'Enter the number written on the letter next to "Sayı".', 'dernekyazilimi' ); ?></p>
				</div>
				<?php if ( ! empty( $config['correspondence']['turnstile_site_key'] ) ) : ?>
					<?php wp_enqueue_script( 'dernekyazilimi-turnstile' ); ?>
					<div class="dy-field">
						<div class="dy-turnstile" data-sitekey="<?php echo esc_attr( $config['correspondence']['turnstile_site_key'] ); ?>"></div>
					</div>
				<?php endif; ?>
				<p class="dy-actions"><button type="submit" class="wp-element-button"><?php esc_html_e( 'Verify document', 'dernekyazilimi' ); ?></button></p>
			</form>
			<noscript><?php esc_html_e( 'Enable JavaScript to verify a document.', 'dernekyazilimi' ); ?></noscript>
			<p class="dy-message" role="status" aria-live="polite" hidden></p>
			<div class="dy-document" data-dy-document hidden>
				<div class="dy-document-head">
					<?php
					$this->shield( 'dy-shield-valid', 'M9 12l2 2l4 -4' );
					$this->shield( 'dy-shield-cancelled', 'M10 10l4 4m0 -4l-4 4' );
					?>
					<h2 tabindex="-1" data-dy-headline data-active="<?php echo esc_attr__( 'Document verified', 'dernekyazilimi' ); ?>" data-cancelled="<?php echo esc_attr__( 'Cancelled document', 'dernekyazilimi' ); ?>"></h2>
					<p data-dy-status data-active="<?php echo esc_attr__( 'This document is registered and has not been cancelled.', 'dernekyazilimi' ); ?>" data-cancelled="<?php echo esc_attr__( 'This document has been cancelled.', 'dernekyazilimi' ); ?>"></p>
				</div>
				<dl class="dy-document-facts">
					<dt><?php esc_html_e( 'Document number', 'dernekyazilimi' ); ?></dt><dd data-dy-value="document_no"></dd>
					<dt><?php esc_html_e( 'Document date', 'dernekyazilimi' ); ?></dt><dd data-dy-value="document_date"></dd>
					<dt><?php esc_html_e( 'Subject', 'dernekyazilimi' ); ?></dt><dd data-dy-value="subject"></dd>
				</dl>
				<div class="dy-document-files" data-dy-files data-preview-label="<?php echo esc_attr__( 'Open PDF', 'dernekyazilimi' ); ?>" data-download-label="<?php echo esc_attr__( 'Download PDF', 'dernekyazilimi' ); ?>" data-original-label="<?php echo esc_attr__( 'Open original PDF', 'dernekyazilimi' ); ?>"></div>
				<p class="dy-hint"><?php esc_html_e( 'The combined PDF is a viewing copy. Use the original files to verify electronic signatures.', 'dernekyazilimi' ); ?></p>
				<div data-dy-attachments data-physical-label="<?php echo esc_attr__( 'physical attachment', 'dernekyazilimi' ); ?>" hidden>
					<h3><?php esc_html_e( 'Attachments', 'dernekyazilimi' ); ?></h3>
					<ul></ul>
				</div>
				<iframe class="dy-document-preview" data-dy-preview title="<?php echo esc_attr__( 'Letter and PDF attachments', 'dernekyazilimi' ); ?>" hidden></iframe>
				<p class="dy-actions"><button type="button" class="wp-element-button is-style-outline" data-dy-again><?php esc_html_e( 'Verify another document', 'dernekyazilimi' ); ?></button></p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * The shield of a verification result, as on the portal's pages.
	 *
	 * @param string $class CSS class.
	 * @param string $mark  SVG path of the mark inside the shield.
	 */
	private function shield( $class, $mark ) {
		printf(
			'<span class="dy-shield %s"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3"/><path d="%s"/></svg></span>',
			esc_attr( $class ),
			esc_attr( $mark )
		);
	}

	/**
	 * [dernekyazilimi_donate cause="3" amount="250"]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function donate( $atts ) {
		$atts   = shortcode_atts(
			array(
				'cause'  => '',
				'amount' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'dernekyazilimi_donate'
		);
		$config = $this->section( 'donation', __( 'Online donations are not accepted at the moment.', 'dernekyazilimi' ) );
		if ( is_string( $config ) ) {
			return $config;
		}

		$donation = $config['donation'];
		$id       = wp_unique_id( 'dy-' );
		$cause    = absint( $atts['cause'] );
		$amount   = is_numeric( $atts['amount'] ) ? (float) $atts['amount'] : '';
		$methods  = (array) ( $donation['methods'] ?? array() );

		ob_start();
		$this->open( 'donation', 'donation' );
		if ( ! empty( $donation['intro'] ) ) {
			echo '<div class="dy-intro">' . wp_kses_post( $donation['intro'] ) . '</div>';
		}
		?>
		<form class="dy-form" novalidate>
			<?php $this->honeypot(); ?>
			<div class="dy-field" data-dy-field="amount">
				<label for="<?php echo esc_attr( $id ); ?>-amount"><?php esc_html_e( 'Amount (TL)', 'dernekyazilimi' ); ?> <span class="dy-required" aria-hidden="true">*</span></label>
				<?php if ( ! empty( $donation['fixed_only'] ) && ! empty( $donation['amounts'] ) ) : ?>
					<div class="dy-amounts">
						<?php foreach ( (array) $donation['amounts'] as $fixed ) : ?>
							<label class="dy-amount-choice"><input type="radio" name="amount" value="<?php echo esc_attr( (int) $fixed ); ?>" <?php checked( (float) $amount, (float) $fixed ); ?> required><span class="dy-amount wp-element-button is-style-outline"><?php echo esc_html( number_format_i18n( (int) $fixed ) ); ?> TL</span></label>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
				<?php if ( ! empty( $donation['amounts'] ) ) : ?>
					<div class="dy-amounts">
						<?php foreach ( (array) $donation['amounts'] as $suggested ) : ?>
							<button type="button" class="dy-amount wp-element-button is-style-outline" data-dy-amount="<?php echo esc_attr( (int) $suggested ); ?>"><?php echo esc_html( number_format_i18n( (int) $suggested ) ); ?> TL</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<input type="number" inputmode="decimal" id="<?php echo esc_attr( $id ); ?>-amount" name="amount" min="<?php echo esc_attr( (int) ( $donation['minimum'] ?? 1 ) ); ?>" step="0.01" value="<?php echo esc_attr( $amount ); ?>" required>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $donation['causes'] ) ) : ?>
				<div class="dy-field" data-dy-field="cause_id">
					<label for="<?php echo esc_attr( $id ); ?>-cause"><?php esc_html_e( 'Donation cause', 'dernekyazilimi' ); ?></label>
					<select id="<?php echo esc_attr( $id ); ?>-cause" name="cause_id">
						<option value=""><?php esc_html_e( 'General donation', 'dernekyazilimi' ); ?></option>
						<?php foreach ( (array) $donation['causes'] as $item ) : ?>
							<option value="<?php echo esc_attr( (int) $item['id'] ); ?>" <?php selected( $cause, (int) $item['id'] ); ?>><?php echo esc_html( $item['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<div class="dy-row">
				<?php
				$this->input( $id, 'name', __( 'Full name', 'dernekyazilimi' ), 'text', true, 'name' );
				$this->input( $id, 'email', __( 'Email', 'dernekyazilimi' ), 'email', true, 'email' );
				?>
			</div>
			<div class="dy-row">
				<?php $this->input( $id, 'phone', __( 'Phone', 'dernekyazilimi' ), 'tel', false, 'tel' ); ?>
			</div>

			<div class="dy-field" data-dy-field="message">
				<label for="<?php echo esc_attr( $id ); ?>-message"><?php esc_html_e( 'Your message (optional)', 'dernekyazilimi' ); ?></label>
				<textarea id="<?php echo esc_attr( $id ); ?>-message" name="message" rows="2" maxlength="1000"></textarea>
			</div>

			<label class="dy-check"><input type="checkbox" name="hide_name" value="1"> <span><?php esc_html_e( 'I do not want my name to be mentioned as a donor', 'dernekyazilimi' ); ?></span></label>

			<fieldset class="dy-field" data-dy-field="method">
				<legend><?php esc_html_e( 'Payment method', 'dernekyazilimi' ); ?> <span class="dy-required" aria-hidden="true">*</span></legend>
				<?php foreach ( $methods as $index => $method ) : ?>
					<label class="dy-check"><input type="radio" name="method" value="<?php echo esc_attr( $method['key'] ); ?>" <?php checked( 0, $index ); ?> required> <span><?php echo esc_html( $method['label'] ); ?></span></label>
				<?php endforeach; ?>
				<p class="dy-hint"><?php esc_html_e( 'Your card details are entered on the secure page of the payment provider, not on this site.', 'dernekyazilimi' ); ?></p>
				<?php $this->logos( $config ); ?>
			</fieldset>

			<?php
			$this->agreements(
				array(
					'agreement'     => $config['privacy'] ?? null,
					'payment_terms' => $config['payment']['terms'] ?? null,
				)
			);
			?>

			<?php if ( ! empty( $donation['turnstile_site_key'] ) ) : ?>
				<?php wp_enqueue_script( 'dernekyazilimi-turnstile' ); ?>
				<div class="dy-field" data-dy-field="turnstile">
					<div class="dy-turnstile" data-sitekey="<?php echo esc_attr( $donation['turnstile_site_key'] ); ?>"></div>
				</div>
			<?php endif; ?>

			<p class="dy-message" role="alert" hidden></p>
			<p class="dy-actions"><button type="submit" class="dy-submit wp-element-button"><?php esc_html_e( 'Donate', 'dernekyazilimi' ); ?></button></p>
		</form>
		<?php
		$this->frame( __( 'Donation', 'dernekyazilimi' ), true );
		$this->close();

		return ob_get_clean();
	}

	/**
	 * [dernekyazilimi_volunteer]
	 *
	 * @return string
	 */
	public function volunteer() {
		$config = $this->section( 'volunteer', __( 'Volunteer registration is closed at the moment.', 'dernekyazilimi' ) );
		if ( is_string( $config ) ) {
			return $config;
		}

		return $this->account( $config, 'volunteer', $config['registration']['label'] ?? __( 'Register', 'dernekyazilimi' ) );
	}

	/**
	 * [dernekyazilimi_membership]
	 *
	 * @return string
	 */
	public function membership() {
		$config = $this->section( 'membership', __( 'Membership applications are closed at the moment.', 'dernekyazilimi' ) );
		if ( is_string( $config ) ) {
			return $config;
		}

		return $this->account( $config, 'membership', __( 'Continue to the application', 'dernekyazilimi' ) );
	}

	/**
	 * The registration form: who the person is, the verified phone, the
	 * communication permissions.
	 *
	 * @param array  $config Portal configuration.
	 * @param string $kind   "volunteer" or "membership".
	 * @param string $submit Label of the submit button.
	 * @return string
	 */
	private function account( array $config, $kind, $submit ) {
		$id = wp_unique_id( 'dy-' );

		ob_start();
		$this->open( 'account', $kind );
		?>
		<form class="dy-form" novalidate>
			<?php $this->honeypot(); ?>
			<?php if ( 'membership' === $kind ) : ?>
				<p class="dy-hint"><?php esc_html_e( 'First we open your portal account; the application form follows right after. If you already have an account, enter its email and you will be asked to sign in.', 'dernekyazilimi' ); ?></p>
			<?php endif; ?>
			<div class="dy-row">
				<?php
				$this->input( $id, 'name', __( 'First name', 'dernekyazilimi' ), 'text', true, 'given-name' );
				$this->input( $id, 'surname', __( 'Last name', 'dernekyazilimi' ), 'text', true, 'family-name' );
				?>
			</div>
			<div class="dy-row">
				<?php $this->input( $id, 'email', __( 'Email', 'dernekyazilimi' ), 'email', true, 'email' ); ?>
				<div class="dy-field" data-dy-field="phone_number">
					<label for="<?php echo esc_attr( $id ); ?>-phone_number"><?php esc_html_e( 'Mobile phone', 'dernekyazilimi' ); ?> <span class="dy-required" aria-hidden="true">*</span></label>
					<div class="dy-inline">
						<input type="tel" id="<?php echo esc_attr( $id ); ?>-phone_number" name="phone_number" autocomplete="tel" placeholder="05xx xxx xx xx" required>
						<button type="button" class="dy-send-code wp-element-button is-style-outline"><?php esc_html_e( 'Send code', 'dernekyazilimi' ); ?></button>
					</div>
					<p class="dy-hint dy-phone-state"><?php esc_html_e( 'We verify your number with a code sent by SMS.', 'dernekyazilimi' ); ?></p>
				</div>
			</div>
			<div class="dy-field dy-code" data-dy-field="code" hidden>
				<label for="<?php echo esc_attr( $id ); ?>-code"><?php esc_html_e( 'Verification code', 'dernekyazilimi' ); ?></label>
				<div class="dy-inline">
					<input type="text" id="<?php echo esc_attr( $id ); ?>-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}">
					<button type="button" class="dy-verify-code wp-element-button is-style-outline"><?php esc_html_e( 'Verify', 'dernekyazilimi' ); ?></button>
				</div>
			</div>

			<?php if ( ! empty( $config['registration']['consents'] ) ) : ?>
				<fieldset class="dy-field">
					<legend><?php esc_html_e( 'Communication permissions', 'dernekyazilimi' ); ?></legend>
					<?php foreach ( (array) $config['registration']['consents'] as $channel => $label ) : ?>
						<label class="dy-check"><input type="checkbox" name="consents[<?php echo esc_attr( $channel ); ?>]" value="1"> <span><?php echo esc_html( $label ); ?></span></label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>

			<?php $this->agreements( array( 'agreement' => $config['privacy'] ?? null ) ); ?>

			<p class="dy-message" role="alert" hidden></p>
			<p class="dy-actions"><button type="submit" class="dy-submit wp-element-button"><?php echo esc_html( $submit ); ?></button></p>
		</form>
		<div class="dy-done" hidden>
			<p><strong><?php esc_html_e( 'Your registration is complete. Thank you!', 'dernekyazilimi' ); ?></strong></p>
			<p><?php esc_html_e( 'We sent you an email with a link to set your password for the portal.', 'dernekyazilimi' ); ?></p>
		</div>
		<?php
		$this->frame( __( 'Membership application', 'dernekyazilimi' ) );
		$this->close();

		return ob_get_clean();
	}

	/**
	 * The portal configuration when the form can be shown, otherwise the
	 * notice to show instead.
	 *
	 * @param string $key    Section of the configuration.
	 * @param string $closed Notice for visitors when the form is closed.
	 * @return array|string
	 */
	private function section( $key, $closed ) {
		$config = $this->client->config();

		if ( is_wp_error( $config ) || ! isset( $config[ $key ] ) ) {
			// Visitors see nothing broken; people who can fix it see why.
			if ( ! current_user_can( 'manage_options' ) ) {
				return '';
			}
			$reason = is_wp_error( $config ) ? $config->get_error_message() : __( 'This module is off in the portal.', 'dernekyazilimi' );

			return '<p class="dernekyazilimi-notice"><strong>Dernek Yazılımı:</strong> ' . esc_html( $reason ) . ' <a href="' . esc_url( admin_url( 'options-general.php?page=dernekyazilimi' ) ) . '">' . esc_html__( 'Settings', 'dernekyazilimi' ) . '</a></p>';
		}

		if ( empty( $config[ $key ]['open'] ) ) {
			return '<p class="dernekyazilimi-notice">' . esc_html( $closed ) . '</p>';
		}

		return $config;
	}

	/**
	 * Start of a form's wrapper; loads the assets.
	 *
	 * @param string $form "donation" or "account".
	 * @param string $kind "donation", "volunteer" or "membership".
	 */
	private function open( $form, $kind ) {
		wp_enqueue_style( 'dernekyazilimi-forms' );
		wp_enqueue_script( 'dernekyazilimi-forms' );

		printf( '<div class="dernekyazilimi" data-dy-form="%1$s" data-dy-kind="%2$s">', esc_attr( $form ), esc_attr( $kind ) );
	}

	/**
	 * End of a form's wrapper.
	 */
	private function close() {
		echo '</div>';
	}

	/**
	 * A text input with its label.
	 *
	 * @param string $id           Prefix of the element id.
	 * @param string $name         Field name.
	 * @param string $label        Label.
	 * @param string $type         Input type.
	 * @param bool   $required     Whether it must be filled in.
	 * @param string $autocomplete Autocomplete token.
	 */
	private function input( $id, $name, $label, $type, $required, $autocomplete ) {
		echo '<div class="dy-field" data-dy-field="' . esc_attr( $name ) . '">';
		echo '<label for="' . esc_attr( $id . '-' . $name ) . '">' . esc_html( $label );
		if ( $required ) {
			echo ' <span class="dy-required" aria-hidden="true">*</span>';
		}
		echo '</label>';
		echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id . '-' . $name ) . '" name="' . esc_attr( $name ) . '" autocomplete="' . esc_attr( $autocomplete ) . '"' . ( $required ? ' required' : '' ) . '>';
		echo '</div>';
	}

	/**
	 * [dernekyazilimi_agreement key="payment-terms"]
	 *
	 * Text of an agreement published in the portal, so that it is kept in
	 * one place and still has a page on this site.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function agreement( $atts ) {
		$atts = shortcode_atts(
			array(
				'key'   => 'payment-terms',
				'title' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'dernekyazilimi_agreement'
		);
		$key  = sanitize_title( $atts['key'] );
		$text = $this->client->agreement( $key );

		if ( is_wp_error( $text ) ) {
			// Visitors see nothing broken; people who can fix it see why.
			if ( ! current_user_can( 'manage_options' ) ) {
				return '';
			}

			return '<p class="dernekyazilimi-notice"><strong>Dernek Yazılımı:</strong> ' . esc_html( $text->get_error_message() ) . ' (' . esc_html( $key ) . ')</p>';
		}

		$html = '<div class="dernekyazilimi dy-agreement">';
		if ( rest_sanitize_boolean( $atts['title'] ) && ! empty( $text['title'] ) ) {
			$html .= '<h2>' . esc_html( $text['title'] ) . '</h2>';
		}

		return $html . wp_kses_post( $text['content'] ) . '</div>';
	}

	/**
	 * [dernekyazilimi_payment_logos]
	 *
	 * Logos of the portal's card payment providers, e.g. for the footer.
	 *
	 * @return string
	 */
	public function payment_logos() {
		$config = $this->client->config();
		if ( is_wp_error( $config ) || empty( $config['payment']['logos'] ) ) {
			return '';
		}
		wp_enqueue_style( 'dernekyazilimi-forms' );

		ob_start();
		echo '<div class="dernekyazilimi">';
		$this->logos( $config );
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Logos the portal's card payment providers ask to be shown.
	 *
	 * @param array $config Portal configuration.
	 */
	private function logos( array $config ) {
		$logos = (array) ( $config['payment']['logos'] ?? array() );
		if ( ! $logos ) {
			return;
		}
		echo '<p class="dy-logos">';
		foreach ( $logos as $logo ) {
			if ( empty( $logo['url'] ) ) {
				continue;
			}
			// The image lives in the portal: the provider's own logo band, not a file of this plugin.
			echo '<img src="' . esc_url( $logo['url'] ) . '" alt="' . esc_attr( $logo['label'] ?? '' ) . '" loading="lazy" decoding="async">';
		}
		echo '</p>';
	}

	/**
	 * Checkboxes of the agreements in force in the portal, with the dialog
	 * their texts open in.
	 *
	 * @param array $docs Field name => agreement of the configuration (or null).
	 */
	private function agreements( array $docs ) {
		$docs = array_filter(
			$docs,
			function ( $doc ) {
				return ! empty( $doc['url'] );
			}
		);
		if ( ! $docs ) {
			return;
		}

		foreach ( $docs as $name => $doc ) {
			$title = $doc['title'] ?? __( 'agreement', 'dernekyazilimi' );
			$link  = '<a class="dy-doc-link" href="' . esc_url( $doc['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $title ) . '</a>';
			echo '<div class="dy-field" data-dy-field="' . esc_attr( $name ) . '"><label class="dy-check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" required> <span>';
			/* translators: %s: link to the agreement, e.g. the privacy policy. */
			echo wp_kses_post( sprintf( __( 'I have read and accept: %s', 'dernekyazilimi' ), $link ) );
			echo ' <span class="dy-required" aria-hidden="true">*</span></span></label></div>';
		}

		// The text opens in a dialog over the page; without script the link opens a new tab.
		$label = wp_unique_id( 'dy-doc-' );
		$first = reset( $docs );
		?>
		<dialog class="dy-modal dy-doc-modal" aria-labelledby="<?php echo esc_attr( $label ); ?>">
			<div class="dy-modal-head">
				<strong id="<?php echo esc_attr( $label ); ?>"></strong>
				<button type="button" class="dy-modal-close" aria-label="<?php esc_attr_e( 'Close', 'dernekyazilimi' ); ?>">&times;</button>
			</div>
			<div class="dy-modal-body">
				<div class="dy-frame-view">
					<iframe title="<?php esc_attr_e( 'Agreement text', 'dernekyazilimi' ); ?>" referrerpolicy="strict-origin-when-cross-origin"></iframe>
					<?php $this->loading(); ?>
				</div>
				<p class="dy-hint dy-fallback"><?php esc_html_e( 'Page not showing?', 'dernekyazilimi' ); ?> <a href="<?php echo esc_url( $first['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open it in a new tab', 'dernekyazilimi' ); ?></a></p>
			</div>
		</dialog>
		<?php
	}

	/**
	 * A field people do not see; bots that fill it are turned away.
	 */
	private function honeypot() {
		echo '<div class="dy-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
	}

	/**
	 * Shown over a frame until its page has loaded; a slow payment page
	 * otherwise looks like an empty, broken window.
	 */
	private function loading() {
		?>
		<div class="dy-loading" role="status" hidden><span class="dy-spinner" aria-hidden="true"></span> <?php esc_html_e( 'Loading…', 'dernekyazilimi' ); ?></div>
		<?php
	}

	/**
	 * Where the portal's page is shown after the form. With a modal, the card
	 * payment opens in a dialog over the page and the result returns here.
	 *
	 * @param string $title Title of the frame for screen readers.
	 * @param bool   $modal Whether to add the payment dialog.
	 */
	private function frame( $title, $modal = false ) {
		?>
		<div class="dy-frame" hidden>
			<p class="dy-note" hidden></p>
			<div class="dy-frame-view">
				<iframe title="<?php echo esc_attr( $title ); ?>" allow="payment" referrerpolicy="strict-origin-when-cross-origin"></iframe>
				<?php $this->loading(); ?>
			</div>
			<p class="dy-hint dy-fallback"><?php esc_html_e( 'Page not showing?', 'dernekyazilimi' ); ?> <a href="#" target="_blank" rel="noopener"><?php esc_html_e( 'Open it in a new tab', 'dernekyazilimi' ); ?></a></p>
		</div>
		<?php if ( $modal ) : ?>
			<?php $label = wp_unique_id( 'dy-modal-' ); ?>
			<dialog class="dy-modal" aria-labelledby="<?php echo esc_attr( $label ); ?>">
				<div class="dy-modal-head">
					<strong id="<?php echo esc_attr( $label ); ?>"><?php esc_html_e( 'Secure payment', 'dernekyazilimi' ); ?></strong>
					<button type="button" class="dy-modal-close" aria-label="<?php esc_attr_e( 'Close', 'dernekyazilimi' ); ?>">&times;</button>
				</div>
				<div class="dy-modal-body"></div>
			</dialog>
			<?php
		endif;
	}
}
