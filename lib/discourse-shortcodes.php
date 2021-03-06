<?php
/**
 * Sets up the plugin.
 *
 * @package WPDiscourse\Shortcodes
 */

namespace WPDiscourse\Shortcodes;

use WPDiscourse\Utilities\Utilities as DiscourseUtilities;

/**
 * Class DiscourseShortcodes
 *
 * @package WPDiscourse\Shortcodes
 */
class DiscourseShortcodes {

	/**
	 * The key for the plugin's options array.
	 *
	 * @access protected
	 * @var string
	 */
	protected $option_key = 'wpds_options';

	/**
	 * The merged options from WP Discourse and WP Discourse Shortcodes.
	 *
	 * All options are held in a single array, use a custom plugin prefix to avoid naming collisions with wp-discourse.
	 *
	 * @access protected
	 * @var array
	 */
	protected $options;

	/**
	 * The options array added by this plugin.
	 *
	 * @access protected
	 * @var array
	 */
	protected $wpds_options = array(
		'wpds_max_topics' => 6,
		'wpds_clear_cache' => 0,
		'wpds_topic_content' => 1,
		'wpds_display_private_topics' => 0,
		'wpds_use_default_styles'     => 1,
		'wpds_topic_webhook_refresh'  => 0,
		'wpds_ajax_refresh'           => 0,
	);

	/**
	 * LatestTopics constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'initialize_plugin' ) );
		add_filter( 'wpdc_utilities_options_array', array( $this, 'add_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'plugin_scripts' ) );
	}

	/**
	 * Adds the plugin options, gets the merged wp-discourse/wp-discourse-latest-topics options, sets the discourse_url.
	 */
	public function initialize_plugin() {
		add_option( 'wpds_options', $this->wpds_options );
		add_option( 'wpds_update_flags', array() );
		$this->options = DiscourseUtilities::get_options();
	}

	/**
	 * Enqueue styles.
	 */
	public function plugin_scripts() {
		if ( ! empty( $this->options['wpds_use_default_styles'] ) ) {
			wp_register_style( 'wpds_styles', plugins_url( '/css/styles.css', __FILE__ ), array(), WPDS_VERSION );
			wp_enqueue_style( 'wpds_styles' );

			wp_register_style( 'fontello_styles', plugins_url( '../assets/fontello/css/fontello.css', __FILE__ ), array(), WPDS_VERSION );
			wp_enqueue_style( 'fontello_styles' );
		}

		if ( ! empty( $this->options['wpds_ajax_refresh'] ) ) {
			wp_register_script( 'wpds_js', plugins_url( '/js/discourse-latest.js', __FILE__ ), array( 'jquery' ), WPDS_VERSION, true );
			$data = array(
				'latestURL' => home_url( '/wp-json/wp-discourse/v1/latest-topics' ),
			);
			wp_enqueue_script( 'wpds_js' );
			wp_localize_script( 'wpds_js', 'wpds', $data );
		}
	}

	/**
	 * Hooks into 'wpdc_utilities_options_array'.
	 *
	 * This function merges the plugins options with the options array that is created in
	 * WPDiscourse\Utilities\Utilities::get_options. Doing this makes it possible to use the FormHelper function in the plugin.
	 * If you aren't using the FormHelper function, there is no need to do this.
	 *
	 * @param array $wpdc_options The unfiltered Discourse options.
	 *
	 * @return array
	 */
	public function add_options( $wpdc_options ) {
		static $merged_options = [];

		if ( empty( $merged_options ) ) {
			$added_options = get_option( $this->option_key );
			if ( is_array( $added_options ) ) {
				$merged_options = array_merge( $wpdc_options, $added_options );
			} else {
				$merged_options = $wpdc_options;
			}
		}

		return $merged_options;
	}
}
