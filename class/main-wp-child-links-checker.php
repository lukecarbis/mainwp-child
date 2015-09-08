<?php
/**
 * Class Main_WP_Child_Links_Checker
 */
class Main_WP_Child_Links_Checker {
	public static $instance = null;

	/**
	 * @return Main_WP_Child_Links_Checker
	 */
	static function instance() {
		if ( null === Main_WP_Child_Links_Checker::$instance ) {
			Main_WP_Child_Links_Checker::$instance = new Main_WP_Child_Links_Checker();
		}
		return Main_WP_Child_Links_Checker::$instance;
	}

	// @TODO: Remove
	/**
	 * Construct
	 */
	public function __construct() {

	}

	public function action() {
		$information = array();
		if ( ! defined( 'BLC_ACTIVE' )  || ! function_exists( 'blc_init' ) ) {
			$information['error'] = 'NO_BROKENLINKSCHECKER';
			Main_WP_Helper::write( $information );
		}
		blc_init();
		// @TODO: Nonce verification
		if ( isset( $_POST['mwp_action'] ) ) { // @codingStandardsIgnoreLine
			switch ( $_POST['mwp_action'] ) {
				case 'set_showhide':
					$information = $this->set_showhide();
					break;
				case 'sync_data':
					$information = $this->sync_data();
					break;
				case 'edit_link':
					$information = $this->edit_link();
					break;
				case 'unlink':
					$information = $this->unlink();
					break;
				case 'set_dismiss':
					$information = $this->set_link_dismissed();
					break;
				case 'discard':
					$information = $this->discard();
					break;
				case 'save_settings':
					$information = $this->save_settings();
					break;
				case 'force_recheck':
					$information = $this->force_recheck();
					break;
			}
		}
		Main_WP_Helper::write( $information );
	}


	public function init() {
		if ( get_option( 'mainwp_linkschecker_ext_enabled' ) !== 'Y' ) {
			return;
		}

		if ( get_option( 'mainwp_linkschecker_hide_plugin' ) === 'hide' ) {
			add_filter( 'all_plugins', array( $this, 'hide_plugin' ) );
			add_filter( 'update_footer', array( &$this, 'update_footer' ), 15 );
		}
	}

	/**
	 * @param $comment_id
	 */
	public static function hook_trashed_comment( $comment_id ) {
		if ( get_option( 'mainwp_linkschecker_ext_enabled' ) !== 'Y' ) {
			return;
		}

		if ( ! defined( 'BLC_ACTIVE' )  || ! function_exists( 'blc_init' ) ) {
			return;
		}
		blc_init();
		$container = blcContainerHelper::get_container( array( 'comment', $comment_id ) );
		$container->delete();
		blc_cleanup_links();
	}

	/**
	 * @return array
	 */
	function save_settings() {
		$information = array();
		$information['result'] = 'NOTCHANGE';
		$new_check_threshold = intval( $_POST['check_threshold'] );

		// @TODO: Nonce verifications
		if ( update_option( 'mainwp_child_blc_max_number_of_links', intval( $_POST['max_number_of_links'] ) ) ) { // @codingStandardsIgnoreLine
			$information['result'] = 'SUCCESS';
		}

		if ( $new_check_threshold > 0 ) {
			$conf = blc_get_configuration();
			$conf->options['check_threshold'] = $new_check_threshold;
			if ( $conf->save_options() ) {
				$information['result'] = 'SUCCESS';
			}
		}

		return $information;
	}

	/**
	 * @return array
	 */
	function force_recheck() {
		$this->initiate_recheck();
		$information = array();
		$information['result'] = 'SUCCESS';
		return $information;
	}

	function initiate_recheck() {
		global $wpdb;

		//Delete all discovered instances
		$wpdb->query( "TRUNCATE {$wpdb->prefix}blc_instances" );

		//Delete all discovered links
		$wpdb->query( "TRUNCATE {$wpdb->prefix}blc_links" );

		// @TODO: Check if function exists
		//Mark all posts, custom fields and bookmarks for processing.
		blc_resynch( true );
	}


	/**
	 * @param $post_id
	 */
	public static function hook_post_deleted( $post_id ) {
		if ( get_option( 'mainwp_linkschecker_ext_enabled' ) !== 'Y' ) {
			return;
		}

		if ( ! defined( 'BLC_ACTIVE' )  || ! function_exists( 'blc_init' ) ) {
			return;
		}
		blc_init();

		//Get the container type matching the type of the deleted post
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		//Get the associated container object
		$post_container = blcContainerHelper::get_container( array( $post->post_type, intval( $post_id ) ) );

		if ( $post_container ) {
			//Delete it
			$post_container->delete();
			//Clean up any dangling links
			blc_cleanup_links();
		}
	}

	/**
	 * @param $plugins
	 *
	 * @return mixed
	 */
	public function hide_plugin( $plugins ) {
		foreach ( $plugins as $key => $value ) {
			$plugin_slug = basename( $key, '.php' );
			if ( 'broken-link-checker' === $plugin_slug ) {
				unset( $plugins[ $key ] );
			}
		}
		return $plugins;
	}

	/**
	 * @param $text
	 *
	 * @return mixed
	 */
	function update_footer( $text ) {
		?>
		<script>
			jQuery( document ).ready(function() {
				jQuery( '#menu-tools' ).find( 'a[href="tools.php?page=view-broken-links"]' ).closest( 'li' ).remove();
				jQuery( '#menu-settings' ).find( 'a[href="options-general.php?page=link-checker-settings"]' ).closest( 'li' ).remove();
			});
		</script>
        <?php
		return $text;
	}

	function set_showhide() {
		Main_WP_Helper::update_option( 'mainwp_linkschecker_ext_enabled', 'Y', 'yes' );
		// @TODO: Nonce verification
		$hide = isset( $_POST['showhide'] ) && ( 'hide' === $_POST['showhide'] ) ? 'hide' : ''; // @codingStandardsIgnoreLine
		Main_WP_Helper::update_option( 'mainwp_linkschecker_hide_plugin', $hide );
		$information['result'] = 'SUCCESS';
		return $information;
	}

	/**
	 * @param string $strategy
	 *
	 * @return array
	 */
	function sync_data( $strategy = '' ) {
		// @TODO: $strategy is unused, remove
		unset( $strategy );

		$information = array();
		$data = array();

		$blc_link_query = blcLinkQuery::getInstance();
		$data['broken'] = $blc_link_query->get_filter_links( 'broken', array( 'count_only' => true ) );
		$data['redirects'] = $blc_link_query->get_filter_links( 'redirects', array( 'count_only' => true ) );
		$data['dismissed'] = $blc_link_query->get_filter_links( 'dismissed', array( 'count_only' => true ) );
		$data['all'] = $blc_link_query->get_filter_links( 'all', array( 'count_only' => true ) );
		$data['link_data'] = self::sync_link_data();
		$information['data'] = $data;

		return $information;
	}

	/**
	 * @return array|string
	 */
	static function sync_link_data() {
		$max_results = get_option( 'mainwp_child_blc_max_number_of_links', 50 );
		$params = array( array( 'load_instances' => true ) );
		if ( ! empty( $max_results ) ) {
			$params['max_results'] = $max_results;
		}
		// @TODO: Check if function exists
		$links = blc_get_links( $params );
		$get_fields = array(
			'link_id',
			'url',
			'being_checked',
			'last_check',
			'last_check_attempt',
			'check_count',
			'http_code',
			'request_duration',
			'timeout',
			'redirect_count',
			'final_url',
			'broken',
			'first_failure',
			'last_success',
			'may_recheck',
			'false_positive',
			//'result_hash', // @TODO: Remove
			'dismissed',
			'status_text',
			'status_code',
			'log',
		);
		$return = '';
		// @TODO: SANITIZE and Nonce
		$site_id = $_POST['site_id']; // @codingStandardsIgnoreLine
		$blc_option = get_option( 'wsblc_options' );

		if ( is_string( $blc_option ) && ! empty( $blc_option ) ) {
			$blc_option = json_decode( $blc_option, true );
		}

		if ( is_array( $links ) ) {
			foreach ( $links as $link ) {
				$lnk = new stdClass();
				foreach ( $get_fields as $field ) {
					$lnk->$field = $link->$field;
				}

				if ( ! empty( $link->post_date ) ) {
					$lnk->post_date = $link->post_date;
				}

				$days_broken = 0;
				if ( $link->broken ) {
					//Add a highlight to broken links that appear to be permanently broken
					$days_broken = intval( ( time() - $link->first_failure ) / ( 3600 * 24 ) );
					if ( $days_broken >= $blc_option['failure_duration_threshold'] ) {
						$lnk->permanently_broken = 1;
						if ( $blc_option['highlight_permanent_failures'] ) {
							$lnk->permanently_broken_highlight = 1;
						}
					}
				}
				$lnk->days_broken = $days_broken;
				if ( ! empty( $link->_instances ) ) {
					$instance = reset( $link->_instances );
					$lnk->link_text = $instance->ui_get_link_text();
					$lnk->count_instance = count( $link->_instances );
					$container = $instance->get_container(); /** @var blcContainer $container */
					$lnk->container = $container;

					if ( ! empty( $container ) /* && ($container instanceof blcAnyPostContainer) */ ) {
						$lnk->container_type = $container->container_type;
						$lnk->container_id = $container->container_id;
						$lnk->source_data = Main_WP_Child_Links_Checker::instance()->ui_get_source( $container, $instance->container_field );
					}

					$can_edit_text = false;
					$can_edit_url = false;
					$editable_link_texts = $non_editable_link_texts = array();
					$instances = $link->_instances;
					foreach ( $instances as $instance ) {
						if ( $instance->is_link_text_editable() ) {
							$can_edit_text = true;
							$editable_link_texts[ $instance->link_text ] = true;
						} else {
							$non_editable_link_texts[ $instance->link_text ] = true;
						}

						if ( $instance->is_url_editable() ) {
							$can_edit_url = true;
						}
					}

					$link_texts = $can_edit_text ? $editable_link_texts : $non_editable_link_texts;
					$data_link_text = '';
					if ( count( $link_texts ) === 1 ) {
						//All instances have the same text - use it.
						$link_text = key( $link_texts );
						$data_link_text = esc_attr( $link_text );
					}
					$lnk->data_link_text = $data_link_text;
					$lnk->can_edit_url = $can_edit_url;
					$lnk->can_edit_text = $can_edit_text;
				} else {
					$lnk->link_text = '';
					$lnk->count_instance = 0;
				}
				$lnk->site_id = $site_id;

				$return[] = $lnk;
			}
		} else {
			return '';
		}

		return $return;
	}

	/**
	 * @return array
	 */
	function edit_link() {
		$information = array();
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			 $information['error'] = 'NOTALLOW';
			 return $information;
		}
		//Load the link
		// @TODO: Nonce verification
		$link = new blcLink( intval( $_POST['link_id'] ) ); // @codingStandardsIgnoreLine
		if ( ! $link->valid() ) {
			$information['error'] = 'NOTFOUNDLINK'; // Oops, I can't find the link
			return $information;
		}

		//Validate the new URL.
		// @TODO: SANITIZE (esc_url_
		$new_url = stripslashes( $_POST['new_url'] ); // @codingStandardsIgnoreLine
		$parsed = @parse_url( $new_url ); // @codingStandardsIgnoreLine
		if ( ! $parsed ) {
			$information['error'] = 'URLINVALID'; // Oops, the new URL is invalid!
			return $information;
		}

		// @TODO: Refactor to standard if statement
		$new_text = ( isset( $_POST['new_text'] ) && is_string( $_POST['new_text'] ) ) ? stripslashes( $_POST['new_text'] ) : null; // @codingStandardsIgnoreLine
		if ( $new_text === '' ) {
			$new_text = null;
		}
		if ( ! empty( $new_text ) && ! current_user_can( 'unfiltered_html' ) ) {
			$new_text = stripslashes( wp_filter_post_kses( addslashes( $new_text ) ) ); //wp_filter_post_kses expects slashed data.
		}

		$rez = $link->edit( $new_url, $new_text );
		if ( false === $rez ) {
			$information['error'] = __( 'An unexpected error occurred!' );
			return $information;
		} else {
			$new_link = $rez['new_link']; /** @var blcLink $new_link */
			$new_status = $new_link->analyse_status();
			$ui_link_text = null;
			if ( isset( $new_text ) ) {
				$instances = $new_link->get_instances();
				if ( ! empty( $instances ) ) {
					$first_instance = reset( $instances );
					$ui_link_text = $first_instance->ui_get_link_text();
				}
			}

			$response = array(
				'new_link_id' => $rez['new_link_id'],
				'cnt_okay' => $rez['cnt_okay'],
				'cnt_error' => $rez['cnt_error'],

				'status_text' => $new_status['text'],
				'status_code' => $new_status['code'],
				'http_code'   => empty( $new_link->http_code ) ? '' : $new_link->http_code,

				'url' => $new_link->url,
				'link_text' => isset( $new_text ) ? $new_text : null,
				'ui_link_text' => isset( $new_text ) ? $ui_link_text : null,

				'errors' => array(),
			);
			// @TODO: Remove
			//url, status text, status code, link text, editable link text

			/**
			 * @var WP_Error
			 */
			foreach ( $rez['errors'] as $error ) {
				array_push( $response['errors'], implode( ', ', $error->get_error_messages() ) );
			}

			return $response;
		}
	}

	function unlink() {
		$information = array();
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			 $information['error'] = 'NOTALLOW';
			 return $information;
		}

		// @TODO: Nonce verification
		if ( isset( $_POST['link_id'] ) ) { // @codingStandardsIgnoreLine
			//Load the link
			$link = new blcLink( intval( $_POST['link_id'] ) ); // @codingStandardsIgnoreLine

			if ( ! $link->valid() ) {
				$information['error'] = 'NOTFOUNDLINK'; // Oops, I can't find the link
				return $information;
			}

				//Try and unlink it
				$rez = $link->unlink();

			if ( false === $rez ) {
				$information['error'] = 'UNDEFINEDERROR'; // An unexpected error occured!
				return $information;
			} else {
				$response = array(
					'cnt_okay' => $rez['cnt_okay'],
					'cnt_error' => $rez['cnt_error'],
					'errors' => array(),
				);
				foreach ( $rez['errors'] as $error ) {  /** @var WP_Error $error */
						array_push( $response['errors'], implode( ', ', $error->get_error_messages() ) );
				}
				return $response;
			}
		} else {
			$information['error'] = __( 'Error : link_id not specified' );
			return $information;
		}
	}

	/**
	 * @return array|string
	 */
	private function set_link_dismissed() {
		$information = array();
		// @TODO: SANITIZE and Nonce
		$dismiss = $_POST['dismiss']; // @codingStandardsIgnoreLine

		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$information['error'] = 'NOTALLOW';
			return $information;
		}

		if ( isset( $_POST['link_id'] ) ) { // @codingStandardsIgnoreLine
			//Load the link
			$link = new blcLink( intval( $_POST['link_id'] ) ); // @codingStandardsIgnoreLine

			if ( ! $link->valid() ) {
				$information['error'] = 'NOTFOUNDLINK'; // Oops, I can't find the link
				return $information;
			}

			$link->dismissed = $dismiss;

			//Save the changes
			if ( $link->save() ) {
				$information = 'OK';
			} else {
				$information['error'] = 'COULDNOTMODIFY'; // Oops, couldn't modify the link
			}
			return $information;
		} else {
			$information['error'] = __( 'Error : link_id not specified' );
			return $information;
		}
	}

	/**
	 * @return array
	 */
	private function discard() {
		$information = array();

		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$information['error'] = 'NOTALLOW';
			return $information;
		}

		// @TODO Nonce verification
		if ( isset( $_POST['link_id'] ) ) { // @codingStandardsIgnoreLine
			//Load the link
			$link = new blcLink( intval( $_POST['link_id'] ) ); // @codingStandardsIgnoreLine

			if ( ! $link->valid() ) {
				$information['error'] = 'NOTFOUNDLINK'; // Oops, I can't find the link
				return $information;
			}

			//Make it appear "not broken"
			$link->broken = false;
			$link->false_positive = true;
			$link->last_check_attempt = time();
			$link->log = __( 'This link was manually marked as working by the user.' );

			//Save the changes
			if ( $link->save() ) {
				$information['status'] = 'OK';
				$information['last_check_attempt'] = $link->last_check_attempt;
			} else {
				$information['error'] = 'COULDNOTMODIFY'; // Oops, couldn't modify the link
			}
		} else {
			$information['error'] = __( 'Error : link_id not specified' );
		}
		return $information;
	}

	/**
	 * @param $container
	 * @param string $container_field
	 *
	 * @return array
	 */
	function ui_get_source( $container, $container_field = '' ) {
		if ( 'comment' === $container->container_type ) {
			return $this->ui_get_source_comment( $container, $container_field );
		} else if ( $container instanceof blcAnyPostContainer ) {
			return $this->ui_get_source_post( $container, $container_field );
		}
		return array();
	}

	/**
	 * @param $container
	 * @param string $container_field
	 *
	 * @return array
	 */
	function ui_get_source_comment( $container, $container_field = '' ) {
		//Display a comment icon.
		if ( 'comment_author_url' === $container_field ) {
			$image = 'font-awesome/font-awesome-user.png';
		} else {
			$image = 'font-awesome/font-awesome-comment-alt.png';
		}

		$comment = $container->get_wrapped_object();

		//Display a small text sample from the comment
		$text_sample = strip_tags( $comment->comment_content );
		$text_sample = blcUtility::truncate( $text_sample, 65 );

		return array(
			'image' => $image,
			'text_sample' => $text_sample,
			'comment_author' => esc_attr( $comment->comment_author ),
			'comment_id' => esc_attr( $comment->comment_ID ),
			'comment_status' => wp_get_comment_status( $comment->comment_ID ),
			'container_post_title' => get_the_title( $comment->comment_post_ID ),
			'container_post_status' => get_post_status( $comment->comment_post_ID ),
			'container_post_ID' => $comment->comment_post_ID,
		);
	}

	/**
	 * @param $container
	 * @param string $container_field
	 *
	 * @return array
	 */
	function ui_get_source_post( $container, $container_field = '' ) {
		// @TODO: Remove unused $container_field
		unset( $container_field );
		return array(
			'post_title' => get_the_title( $container->container_id ),
			'post_status' => get_post_status( $this->container_id ), // @TODO: No such thing as $this->container_id
			'container_anypost' => true,
		);
	}
}
