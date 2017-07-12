<?php
/*
Plugin Name: FFTF Alerts
Plugin URI: https://halfelf.org/plugins/fftf-alerts
Description: Show Fight for the Future alerts on your website
Version: 1.1.1
Author: Mika Epstein (Ipstenu)
Author URI: https://halfelf.org
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: fftf-alerts

	Copyright 2017 Mika A. Epstein (email: ipstenu@halfelf.org)

	This file is part of FFTF Alerts, a plugin for WordPress.

	FFTF Alerts is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, version 3 of the License.

	FFTF Alerts is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	A copy of the Licence has been inlcuded in the plugin, but can also
	be downloaded at https://www.gnu.org/licenses/gpl-3.0.html

*/

/*
 * class FFTF_Alerts
 *
 * Main class for plugin
 *
 * @since 1.0.0
 */

class FFTF_Alerts {

	protected static $version;
	protected static $default_settings;
	protected static $settings;
	protected static $fights;
	protected static $expiration;

	/**
	 * Constructor
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_script' ) );

		// Create Defaults
		self::$version    = '1.1.0';
		self::$expiration = '604800'; // 1 week
		self::$default_settings = array (
			'version'          => self::$version,
			'blackoutcongress' => false,
			'battleforthenet'  => false,
			'catsignal'        => false,
		);
		self::$settings   = get_option( 'fftf_alerts_options', self::$default_settings );
		self::$fights     = array (
			'blackoutcongress'     => array(
				'name'  => __( 'Blackout Congress', 'fftf-alerts' ),
				'date'  => 'ongoing',
				'url'   => 'https://www.blackoutcongress.org',
				'js'    => 'https://www.blackoutcongress.org/detect.js',
				'debug' => 'fftf_redirectjs = { alwaysRedirect: true }',
			),
			'catsignal' => array(
				'name'  => __( 'Cat Signal (Internet Defense League)', 'fftf-alerts' ),
				'date'  => 'ongoing',
				'url'   => 'https://www.internetdefenseleague.org',
				'js'    => plugins_url( 'js/catsignal.js', __FILE__ ),
			),
			'battleforthenet'       => array(
				'name'  => __( 'Battle for the Net', 'fftf-alerts' ),
				'date'  => '2017-07-12',
				'url'   => 'https://www.battleforthenet.com/july12/',
				'js'    => 'https://widget.battleforthenet.com/widget.js',
				'extra' => 'var _bftn_options = { iframe_base_path: "https://widget.battleforthenet.com/iframe" }',
			),
		);

		$this->check_upgrade();
	}

	/**
	 * Admin Init
	 * @since 1.0.0
	 */
	public function admin_init() {
		$plugin = plugin_basename(__FILE__);
		add_filter( "plugin_action_links_$plugin", array( $this, 'add_settings_link' ) );
		add_filter( 'plugin_row_meta', array( $this, 'donate_link' ), 10, 2 );

	    // Register Settings
		$this->register_settings();
	}

	/**
	 * Check Upgrade
	 *
	 * If the version we're running is LESS than the version in the plugin
	 * then we need to upgrade. Check if a setting is missing and add it.
	 *
	 * @since 1.1.0
	 */
	public function check_upgrade() {
		if ( self::$settings['version'] < self::$version ) {

			$the_settings = self::$settings;

			foreach ( self::$default_settings as $setting => $value ) {
				if ( !array_key_exists( $setting , self::$settings ) ) {
					$the_settings[$setting] = self::$default_settings[$setting];
				}
			}

			$the_settings['version'] = self::$version;
			ksort( $the_settings );

			update_option( 'fftf_alerts_options', $the_settings );
		}
	}

	/**
	 * Enqueue Scripts
	 *
	 * If the setting is true and the date is ongoing, before 7 days AFTER
	 * the event passes, OR it's in debug mode, enqueue the javascript.
	 *
	 * @since 1.0.0
	 */
	public function wp_enqueue_script() {

		foreach ( self::$settings as $setting => $value ) {
			if ( is_bool( $value ) && $value == true ) {

				$fight = self::$fights[ $setting ];
				$date  = ( strtotime( $fight['date'] ) == false )? $fight['date'] : strtotime( $fight['date'] );

				if ( $date == 'ongoing' || time() < ( $date + self::$expiration ) || WP_DEBUG == true ) {
					wp_enqueue_script( $setting, $fight[ 'js' ], array( 'jquery' ), self::$version );
					if ( array_key_exists( 'extra', $fight ) ) {
						wp_add_inline_script( $setting, $fight[ 'extra' ], 'before' );
					}

					// Debug mode
					if ( WP_DEBUG == true && array_key_exists( 'debug', self::$fights[ $setting ] ) ) {
						wp_add_inline_script( $setting, self::$fights[ $setting ][ 'debug' ], 'before' );
					}
				}
			}
		}
	}

	/**
	 * Admin Menu Callback
	 *
	 * @since 1.0.0
	 */
    function admin_menu() {
		// Add settings page on Tools
		add_management_page( __( 'FFTF Alerts', 'fftf-alerts' ), __( 'FFTF Alerts', 'fftf-alerts' ), 'manage_options', 'fftf-alerts-settings', array( $this, 'fftfalert_settings' ) );
	}

	/**
	 * Register Admin Settings
	 *
	 * @since 1.0.0
	 */
    function register_settings() {
	    register_setting( 'fftf-alerts', 'fftf_alerts_options', array( $this, 'fftfalert_sanitize' ) );

		// The main section
		add_settings_section( 'fftfalert-fights', __( 'Pick Your Battles', 'fftf-alerts' ), array( $this, 'fftf_settings_callback' ), 'fftf-alerts-settings' );

		// The Field
		add_settings_field( 'fftfalert_settings_fields', __( 'Available Battles to Fight', 'fftf-alerts' ), array( $this, 'fftf_settings_fields_callback' ), 'fftf-alerts-settings', 'fftfalert-fights' );
	}

	/**
	 * Settings Callback
	 *
	 * @since 1.0.0
	 */
	function fftf_settings_callback() {
	    ?>
	    <p><?php _e( 'To activate an alert for a fight, click the checkbox and save your settings.', 'fftf-alerts' ); ?></p>
		<p><?php _e( 'In order to prevent you from making your site super slow and load every single script out there, the Cat Signal will deselect everything else for you (and prevent you from being able to select them). It\'s all or piecemeal, basically.', 'fftf-alerts' ); ?></p>
	    <?php
	}

	/**
	 * Each Settings Callback
	 *
	 * @since 1.0.0
	 */
	function fftf_settings_fields_callback() {

		foreach ( self::$settings as $setting => $value ) {
			if ( is_bool( $value ) ) {
				$fight    = self::$fights[ $setting ];
				$date     = ( strtotime( $fight['date'] ) == false )? lcfirst( $fight['date'] ) : date_i18n( get_option( 'date_format' ), strtotime( $fight['date'] ) );

				$disabled = (
					( strtotime( $date ) == true && time() > ( strtotime( $date ) + self::$expiration ) ) ||
					( array_key_exists( 'catsignal', self::$settings ) && $setting !== 'catsignal' && self::$settings['catsignal'] == true )
				)? true : false;

				?>
				<p><input type="checkbox" id="fftf_alerts_options[<?php echo $setting; ?>]" name="fftf_alerts_options[<?php echo $setting; ?>]" value="1" <?php echo checked( 1, $value ); ?> <?php disabled( $disabled, true ); ?> >
				<label for="fftf_alerts_options[<?php echo $setting; ?>]"><a href="<?php echo $fight['url']; ?>" target="_blank"><?php echo $fight['name']; ?></a> - <?php echo $date; ?></label></p>
				<?php
			}
		}
	}

	/**
	 * Options sanitization and validation
	 *
	 * @param $input the input to be sanitized
	 * @since 1.0.0
	 */
	function fftfalert_sanitize( $input ) {

		$options = self::$settings;

		foreach ( $options as $setting => $value ) {

			$fight = self::$fights[ $setting ];

			// Default false
			$output[ $setting ] = false;

			// If the user set it true, it's true
			if ( isset( $input[ $setting ] ) && $input[ $setting ] == true ) {
				$output[ $setting ] = true;
			}

			// If it's past the sell by date, it's false
			if ( ( strtotime( $fight[ 'date' ] ) == true && time() > ( strtotime( $fight[ 'date' ] ) + self::$expiration ) ) ) {
				$output[ $setting ] = false;
			}

			// If the Cat Signal is selected, we ONLY allow that to be checked
			if ( $input[ 'catsignal' ] == true && $setting !== 'catsignal' ) {
				$output[ $setting ] = false;
			}
		}

        $output[ 'version' ] = $options[ 'version' ];

		return $output;
	}

	/**
	 * donate link on manage plugin page
	 * @since 1.0.0
	 */
	function donate_link( $links, $file ) {
		if ($file == plugin_basename(__FILE__)) {
    		$donate_link = '<a href="https://paypal.me/ipstenu/5">' . __( 'Donate', 'fftf-alerts' ) . '</a>';
    		$links[] = $donate_link;
        }
        return $links;
	}

	/**
	 * Call settings page
	 *
	 * @since 1.0
	 */
	function fftfalert_settings() {
		?>
		<div class="wrap">

			<h1><?php _e( 'Fight for the Future Alerts', 'fftf-alerts' ); ?></h1>

			<?php settings_errors(); ?>

			<p><?php echo sprintf( __( '<a href="%1$s" target="_blank">Fight for the Future</a> is dedicated to protecting and expanding the Internet’s transformative power in our lives by creating civic campaigns that are engaging for millions of people.', 'fftf-alerts' ), 'https://fightforthefuture.org' ); ?></p>
			<p><?php _e( 'By default, modals will only display on their designated days. This is by design of both Fight for the Future and this plugin. The javascript for a fight will be loaded for seven days after the event, after which it will vanish in order to keep your site snappy. If a fight is ongoing, the javascript will always be loaded.', 'fftf-alerts' ); ?></p>

			<form action="options.php" method="POST" ><?php
				settings_fields( 'fftf-alerts' );
				do_settings_sections( 'fftf-alerts-settings' );
				submit_button( '', 'primary', 'update');
			?>
			</form>
		</div>

		<?php
	}

	/**
	 * Add settings link on plugin
	 *
	 * @since 1.0.0
	 */
	function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'tools.php?page=fftf-alerts-settings' ) .'">' . __( 'Settings', 'fftf-alerts' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

}

new FFTF_Alerts();