<?php
/**
 * Admin settings and management screen module for SVGFlow.
 *
 * @package SVGFlow
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SVGFlow_Admin
 */
class SVGFlow_Admin {

	/**
	 * Settings page hook suffix.
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Register settings menu page under "Settings".
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_admin_menu() {
		$this->page_hook = add_options_page(
			esc_html__( 'WP SVG Engine Settings', 'svigent-tools' ),
			esc_html__( 'WP SVG Engine', 'svigent-tools' ),
			'manage_options',
			'svigent-tools',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin script for interactive copy buttons on the settings screen.
	 *
	 * @since  1.0.0
	 * @param  string $hook Current admin screen hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( $hook === $this->page_hook ) {
			wp_enqueue_script(
				'svg-flow-admin-js',
				SVIGENT_TOOLS_URL . 'assets/js/admin.js',
				array(),
				SVIGENT_TOOLS_VERSION,
				true
			);
		}
	}

	/**
	 * Register settings, sections, and fields using the WordPress Settings API.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'svg_flow_settings_group',
			'svg_flow_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => SVGFlow_Core::get_settings(),
			)
		);

		add_settings_section(
			'svg_flow_general_section',
			esc_html__( 'General Configuration', 'svigent-tools' ),
			'__return_false',
			'svigent-tools'
		);

		add_settings_field(
			'upload_permission',
			esc_html__( 'Upload Permissions', 'svigent-tools' ),
			array( $this, 'render_field_upload_permission' ),
			'svigent-tools',
			'svg_flow_general_section'
		);

		add_settings_field(
			'sanitization_enabled',
			esc_html__( 'XML / SVG Sanitization', 'svigent-tools' ),
			array( $this, 'render_field_sanitization' ),
			'svigent-tools',
			'svg_flow_general_section'
		);

		add_settings_field(
			'frontend_inlining',
			esc_html__( 'Frontend Inline Rendering', 'svigent-tools' ),
			array( $this, 'render_field_inlining' ),
			'svigent-tools',
			'svg_flow_general_section'
		);

		add_settings_field(
			'inline_selector',
			esc_html__( 'Inline Target Selector', 'svigent-tools' ),
			array( $this, 'render_field_selector' ),
			'svigent-tools',
			'svg_flow_general_section'
		);

		add_settings_field(
			'responsive_css',
			esc_html__( 'Responsive CSS Sizing', 'svigent-tools' ),
			array( $this, 'render_field_responsive_css' ),
			'svigent-tools',
			'svg_flow_general_section'
		);
	}

	/**
	 * Sanitize submitted settings array.
	 *
	 * @since  1.0.0
	 * @param  array $input Raw submitted settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Upload Permission: must be 'admin_only' or 'all_uploaders'.
		$allowed_permissions = array( 'admin_only', 'all_uploaders' );
		$sanitized['upload_permission'] = ( isset( $input['upload_permission'] ) && in_array( $input['upload_permission'], $allowed_permissions, true ) )
			? $input['upload_permission']
			: 'admin_only';

		// Sanitization Toggle.
		$sanitized['sanitization_enabled'] = ! empty( $input['sanitization_enabled'] );

		// Inlining Toggle.
		$sanitized['frontend_inlining'] = ! empty( $input['frontend_inlining'] );

		// Target Selector.
		$sanitized['inline_selector'] = ! empty( $input['inline_selector'] )
			? sanitize_text_field( $input['inline_selector'] )
			: 'img.style-svg, img.svg-flow';

		// Responsive CSS Toggle.
		$sanitized['responsive_css'] = ! empty( $input['responsive_css'] );

		return $sanitized;
	}

	/**
	 * Render Upload Permission radio field.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_field_upload_permission() {
		$settings = SVGFlow_Core::get_settings();
		$current  = $settings['upload_permission'];
		?>
		<fieldset class="svg-flow-radio-group">
			<label class="svg-flow-radio-label">
				<input type="radio" name="svg_flow_settings[upload_permission]" value="admin_only" <?php checked( $current, 'admin_only' ); ?> />
				<span class="svg-flow-radio-content">
					<strong><?php esc_html_e( 'Administrators Only (Recommended)', 'svigent-tools' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Only users with administrator privileges can upload SVG files.', 'svigent-tools' ); ?></span>
				</span>
			</label>
			<label class="svg-flow-radio-label">
				<input type="radio" name="svg_flow_settings[upload_permission]" value="all_uploaders" <?php checked( $current, 'all_uploaders' ); ?> />
				<span class="svg-flow-radio-content">
					<strong><?php esc_html_e( 'All Users with Upload Rights', 'svigent-tools' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Allows Authors, Editors, and Administrators to upload sanitized SVGs.', 'svigent-tools' ); ?></span>
				</span>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * Render Sanitization checkbox field.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_field_sanitization() {
		$settings = SVGFlow_Core::get_settings();
		$checked  = ! empty( $settings['sanitization_enabled'] );
		?>
		<label>
			<input type="checkbox" name="svg_flow_settings[sanitization_enabled]" value="1" <?php checked( $checked, true ); ?> />
			<?php esc_html_e( 'Enable automatic XML sanitization upon upload', 'svigent-tools' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Strips malicious scripts, inline JavaScript handlers, and XML vulnerabilities. Keep enabled for maximum security.', 'svigent-tools' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Frontend Inlining checkbox field.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_field_inlining() {
		$settings = SVGFlow_Core::get_settings();
		$checked  = ! empty( $settings['frontend_inlining'] );
		?>
		<label>
			<input type="checkbox" name="svg_flow_settings[frontend_inlining]" value="1" <?php checked( $checked, true ); ?> />
			<?php esc_html_e( 'Enable client-side inline SVG replacement', 'svigent-tools' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Replaces matching <img> elements with direct <svg> code on the frontend for dynamic CSS styling and animations.', 'svigent-tools' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Target CSS Selector text field.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_field_selector() {
		$settings = SVGFlow_Core::get_settings();
		$selector = isset( $settings['inline_selector'] ) ? $settings['inline_selector'] : '';
		?>
		<input type="text" name="svg_flow_settings[inline_selector]" value="<?php echo esc_attr( $selector ); ?>" class="regular-text code" />
		<p class="description">
			<?php esc_html_e( 'CSS selector targeting the <img> elements you want to inline (e.g., img.style-svg, img.svg-flow).', 'svigent-tools' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Responsive CSS checkbox field.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_field_responsive_css() {
		$settings = SVGFlow_Core::get_settings();
		$checked  = ! empty( $settings['responsive_css'] );
		?>
		<label>
			<input type="checkbox" name="svg_flow_settings[responsive_css]" value="1" <?php checked( $checked, true ); ?> />
			<?php esc_html_e( 'Add basic responsive CSS rules', 'svigent-tools' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Applies max-width: 100% and height: auto to prevent SVGs from overflowing their container.', 'svigent-tools' ); ?>
		</p>
		<?php
	}

	/**
	 * Render main settings screen.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'svigent-tools' ) );
		}

		$settings       = SVGFlow_Core::get_settings();
		$is_sanitizing  = ! empty( $settings['sanitization_enabled'] );
		$first_selector = 'style-svg';

		if ( ! empty( $settings['inline_selector'] ) ) {
			$parts = explode( ',', $settings['inline_selector'] );
			if ( ! empty( $parts[0] ) ) {
				$first_selector = trim( str_replace( array( 'img.', '.' ), '', $parts[0] ) );
			}
		}
		?>
		<div class="wrap svg-flow-wrap">
			<div class="svg-flow-header">
				<div class="svg-flow-header-title">
					<h1><?php esc_html_e( 'WP SVG Engine', 'svigent-tools' ); ?> <span class="svg-flow-version">v<?php echo esc_html( SVIGENT_TOOLS_VERSION ); ?></span></h1>
					<p class="svg-flow-subtitle"><?php esc_html_e( 'Safe SVG Upload & Smart Inliner Engine', 'svigent-tools' ); ?></p>
				</div>
				<div class="svg-flow-status-badge <?php echo esc_attr( $is_sanitizing ? 'status-active' : 'status-warning' ); ?>">
					<span class="status-dot"></span>
					<span class="status-text">
						<?php
						if ( $is_sanitizing ) {
							esc_html_e( 'Sanitization: Active & Secure', 'svigent-tools' );
						} else {
							esc_html_e( 'Sanitization: Disabled', 'svigent-tools' );
						}
						?>
					</span>
				</div>
			</div>

			<div class="svg-flow-layout">
				<div class="svg-flow-main-column">
					<div class="svg-flow-card">
						<form action="options.php" method="post">
							<?php
							settings_fields( 'svg_flow_settings_group' );
							do_settings_sections( 'svigent-tools' );
							submit_button();
							?>
						</form>
					</div>
				</div>

				<div class="svg-flow-sidebar">
					<div class="svg-flow-card">
						<h3><?php esc_html_e( 'Quick Inlining Guide', 'svigent-tools' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'To render an SVG inline on your website, add this CSS class to any Image element:', 'svigent-tools' ); ?>
						</p>
						<div class="svg-flow-copy-box">
							<code id="svg-flow-target-class"><?php echo esc_html( $first_selector ); ?></code>
							<button type="button" class="button button-primary svg-flow-copy-btn" data-target="svg-flow-target-class">
								<span class="dashicons dashicons-admin-page"></span><?php esc_html_e( 'Copy', 'svigent-tools' ); ?>
							</button>
						</div>
						<ul class="svg-flow-guide-list">
							<li><strong><?php esc_html_e( 'Block Editor (Gutenberg):', 'svigent-tools' ); ?></strong> <?php esc_html_e( 'Select the Image block → Advanced → Additional CSS class(es).', 'svigent-tools' ); ?></li>
							<li><strong><?php esc_html_e( 'Page Builders:', 'svigent-tools' ); ?></strong> <?php esc_html_e( 'Add the class in Elementor, Divi, or Beaver Builder image settings.', 'svigent-tools' ); ?></li>
							<li><strong><?php esc_html_e( 'CSS Styling:', 'svigent-tools' ); ?></strong> <?php esc_html_e( 'Target elements inside inline SVGs using fill: currentColor or CSS hover transitions.', 'svigent-tools' ); ?></li>
						</ul>
					</div>

					<div class="svg-flow-card">
						<h3><?php esc_html_e( 'Security Standards', 'svigent-tools' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'WP SVG Engine protects your WordPress site against Cross-Site Scripting (XSS), XML External Entity (XXE) attacks, and unauthorized upload threats using DOM-level inspection.', 'svigent-tools' ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
