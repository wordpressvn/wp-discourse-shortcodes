<?php
/**
 * Gets and returns Discourse group data.
 *
 * @package WPDiscourse\Shortcodes;
 */

namespace WPDiscourse\Shortcodes;

use WPDiscourse\Utilities\Utilities as DiscourseUtilities;

/**
 * Class DiscourseGroups
 *
 * @package WPDiscourse\Shortcodes
 */
class DiscourseGroups {
	use Formatter;

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
	 * The Discourse forum url.
	 *
	 * @access protected
	 * @var string
	 */
	protected $base_url;

	/**
	 * The Discourse API key.
	 *
	 * @access protected
	 * @var string
	 */
	protected $api_key;

	/**
	 * The Discourse api_username.
	 *
	 * @access protected
	 * @var string
	 */
	protected $api_username;

	/**
	 * In instance of the DiscourseLink class.
	 *
	 * @access protected
	 * @var DiscourseLink
	 */
	protected $discourse_link;


	/**
	 * DiscourseGroups constructor.
	 *
	 * @param DiscourseLink $discourse_link An instance of DiscourseLink.
	 */
	public function __construct( $discourse_link ) {
		$this->discourse_link = $discourse_link;
		add_action( 'init', array( $this, 'setup_options' ) );
	}

	/**
	 * Setup the plugin options.
	 */
	public function setup_options() {
		$this->options      = DiscourseUtilities::get_options();
		$this->base_url     = ! empty( $this->options['url'] ) ? $this->options['url'] : null;
		$this->api_key      = ! empty( $this->options['api-key'] ) ? $this->options['api-key'] : null;
		$this->api_username = ! empty( $this->options['publish-username'] ) ? $this->options['publish-username'] : null;
	}

	/**
	 * Gets the groups HTML.
	 *
	 * @param array $args An array of arguments, normally passed from the shorcode.
	 *
	 * @return mixed|string
	 */
	public function get_formatted_groups( $args ) {
		$args   = shortcode_atts(
			array(
				'group_list'           => '',
				'link_open_text'       => 'Join the',
				'link_close_text'      => '',
				'sso'                  => 'false',
				'tile'                 => 'false',
				'show_description'  => 'true',
				'show_images'       => 'true',
				'excerpt_length'       => 55,
				'show_header_metadata' => 'true',
				'show_join_link'        => 'true',
				'add_button_styles' => 'true',
				'id' => null,
			), $args
		);
		$groups = $this->get_discourse_groups( $args );

		if ( empty( $groups ) || is_wp_error( $groups ) ) {

			return '';
		}
		$formatted_groups = $this->format_groups( $groups, $args );

		return $formatted_groups;
	}

	/**
	 * Gets the groups data for an ID.
	 *
	 * The groups data is an array that's saved in a transient. First look for it in the
	 * array. Create and store it if it doesn't exist.
	 *
	 * @param array $args The arguments passed to the function.
	 *
	 * @return array|\WP_Error
	 */
	public function get_discourse_groups( $args ) {

		// The wpds_groups transient stores an array of groups - one for each discourse_groups shortcode id.
		$groups = get_transient( 'wpds_groups' );
		$groups_key = $args['id'] ? 'groups_' . $args['id'] : 'groups';

		if ( empty( $groups[ $groups_key ] ) || is_wp_error( $groups[ $groups_key ] ) ) {

			$raw_groups_data = $this->get_non_automatic_groups();

			if ( empty( $raw_groups_data || is_wp_error( $raw_groups_data ) ) ) {

				return new \WP_Error( 'wpds_response_error', 'The Discourse groups could not be retrieved.' );
			}

			if ( ! empty( $args['group_list'] ) ) {
				$chosen_groups = [];
				$selected      = array_map( 'trim', explode( ',', $args['group_list'] ) );

				foreach ( $raw_groups_data as $group_data ) {
					if ( ! empty( $group_data['name'] ) && in_array( $group_data['name'], $selected, true ) ) {
						$chosen_groups[] = $group_data;
					}
				}

				$groups[ $groups_key ] = $chosen_groups;
			} else {
				// No group_list was provided - save all groups.
				$groups[ $groups_key ] = $raw_groups_data;
			}

			set_transient( 'wpds_groups', $groups, DAY_IN_SECONDS );
		}

		return $groups[ $groups_key ];
	}

	/**
	 * Create the formated groups HTML.
	 *
	 * There's a lot of string concatenation in here! It's not that efficient, but it's easier to read for now.
	 * The formatted HTML is cached for 24 hours.
	 *
	 * @param  array $groups An array of Discourse group data.
	 * @param array $args The shortcode args.
	 *
	 * @return mixed
	 */
	public function format_groups( $groups, $args ) {

		if ( empty( $groups ) ) {

			return new \WP_Error( 'wpds_error', 'The groups array was empty.' );
		}

		$formatted_groups = get_transient( 'wpds_formatted_groups' );
		$groups_key = $args['id'] ? 'groups_' . $args['id'] : 'groups';

		if ( empty( $formatted_groups[ $groups_key ] ) ) {
			$link_open_text  = ! empty( $args['link_open_text'] ) ? $args['link_open_text'] . ' ' : '';
			$link_close_text = ! empty( $args['link_close_text'] ) ? ' ' . $args['link_close_text'] : '';
			$tile_class      = 'true' === $args['tile'] ? 'wpds-tile' : 'wpds-no-tile';

			$output = '<div class="wpds-groups wpds-tile-wrapper"><div class="' . esc_attr( $tile_class ) . '">';
			foreach ( $groups as $group ) {
				$group_name        = ! empty( $group['name'] ) ? $group['name'] : '';
				$group_path        = "/groups/{$group_name}";
				$full_group_name   = ! empty( $group['full_name'] ) ? $group['full_name'] : str_replace( '_', ' ', $group_name );
				$member_number     = ! empty( $group['user_count'] ) ? $group['user_count'] : null;
				$flair_url         = ! empty( $group['flair_url'] ) ? $group['flair_url'] : null;
				$join_enabled      = ! empty( $group['allow_membership_requests'] ) && 'true' === $args['show_join_link'];
				$title_text        = $full_group_name;
				$title_link_args   = array(
					'link_text' => $title_text,
					'path'      => $group_path,
					'classes'   => 'wpds-group-title-link',
					'sso'       => $args['sso'],
				);
				$join_link_text    = $link_open_text . ' ' . $full_group_name . $link_close_text;
				$join_link_args    = array(
					'link_text' => $join_link_text,
					'path'      => $group_path,
					'classes'   => 'true' === $args['add_button_styles'] ? 'wpds-join-group wpds-button' : 'wpds-join-group',
					'sso'       => $args['sso'],
				);
				$group_image       = null;
				$group_description = null;
				$description_data  = $this->parse_text_and_images( $group['bio_raw'], $args['excerpt_length'] );
				if ( ! empty( $description_data ) && ! is_wp_error( $description_data ) ) {
					$group_image       = ! empty( $description_data['images'] ) ? $description_data['images'][0] : null;
					$group_description = ! empty( $description_data['description'] ) ? $description_data['description'] : null;
				}

				$output .= '<div class="wpds-group">';
				$output .= '<div class="wpds-group-clamp">';
				$output = apply_filters( 'wpds_group_above_header', $output, $group, $args );

				if ( 'true' === $args['show_images'] && $group_image ) {
					$output .= '<div class="wpds-group-image">' . wp_kses_post( $group_image ) . '</div>';
				}

				$output .= '<header>';
				$output .= '<h4 class="wpds-groupname">' . wp_kses_post( $this->discourse_link->get_discourse_link( $title_link_args ) ) . '</h4>';

				if ( 'true' === $args['show_header_metadata'] && ( $member_number || $flair_url ) ) {
					$output .= '<div class="wpds-metadata">';
					if ( $flair_url ) {
						$output .= '<img class="wpds-group-avatar" src="' . esc_url_raw( $flair_url ) . '">';
					}
					if ( $member_number ) {
						$output .= '<span class="wpds-member-number">' . $this->member_text( esc_attr( $member_number ) ) . '</span>';
					}
					$output .= '</div>';
				}

				$output .= '</header>';

				if ( 'true' === $args['show_description'] ) {
					$output .= '<div class="wpds-group-description">' . wp_kses_post( $group_description ) . '</div>';
				}

				$output = apply_filters( 'wpds_group_above_footer', $output, $group, $args );
				$output .= '</div>'; // End of .wpds-group-clamp.

				$output .= '<footer>';

				if ( $join_enabled ) {
					$output .= '<div class="wpds-footer-link">';
					$output .= wp_kses_post( $this->discourse_link->get_discourse_link( $join_link_args ) );
					$output .= '</div>';
				}

				$output .= '</footer>'; // End of .wpds-footer-link.
				$output .= '</div>';
			}// End foreach().

			$output .= '</div></div>';

			$formatted_groups[ $groups_key ] = $output;

			set_transient( 'wpds_formatted_groups', $formatted_groups, DAY_IN_SECONDS );
		}

		return apply_filters( 'wpds_formatted_groups', $formatted_groups[ $groups_key ], $groups, $args );
	}

	/**
	 * Returns the pluralized memebers text.
	 *
	 * @param int $members The number of members in the group.
	 *
	 * @return string
	 */
	protected function member_text( $members ) {
		if ( 1 === intval( $members ) ) {
			return '1 ' . __( 'member', 'wpds' );
		} else {
			return $members . ' ' . __( 'members', 'wpds' );
		}
	}

	/**
	 * Retrieves the groups from Discourse.
	 *
	 * The wpds_discourse_groups option is deleted and then refreshed when the shortcode options tab is saved.
	 *
	 * @return array|mixed|null|object
	 */
	protected function get_non_automatic_groups() {
		$non_automatic_groups = get_option( 'wpds_discourse_groups' );

		if ( empty( $non_automatic_groups ) ) {

			if ( empty( $this->base_url ) || empty( $this->api_key ) || empty( $this->api_username ) ) {

				return new \WP_Error(
					'wpds_configuration_error', 'Unable to retrieve groups from Discourse. The WP Discourse plugin is
				not properly configured.'
				);
			}

			$groups_url = esc_url_raw( $this->base_url . '/groups.json' );

			$response = wp_remote_get(
				$groups_url,
			    array(
			    	'headers' => array(
			    		'Api-Key'      => $this->api_key,
					    'Api-Username' => $this->api_username,
				    ),
			    )
			);

			if ( ! DiscourseUtilities::validate( $response ) ) {

				return new \WP_Error( 'wpds_invalid_response', 'An invalid response was returned when retrieving the Discourse groups.' );
			}

			$body               = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['groups'] ) ) {
				$groups = $body['groups'];
				$non_automatic_groups = [];

				foreach ( $groups as $group ) {
					if ( ! empty( $group ) && empty( $group['automatic'] ) ) {
						$non_automatic_groups[] = $group;
					}
				}


				update_option( 'wpds_discourse_groups', $non_automatic_groups );

				return $non_automatic_groups;
			}

			return new \WP_Error( 'wpds_invalid_response', 'An invalid response was returned when trying to retrieve the Discourse groups.' );
		}

		return $non_automatic_groups;
	}
}
