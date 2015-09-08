<?php
/**
 * Class Main_WP_Child_Server_Information
 */
class Main_WP_Child_Server_Information {
	public static function init() {
		add_action( 'wp_ajax_mainwp-child_dismiss_warnings', array( 'Main_WP_Child_Server_Information', 'dismiss_warnings' ) );
	}

	public static function dismiss_warnings() {
		// @TODO: Nonce verification
		if ( isset( $_POST['what'] ) ) { // @codingStandardsIgnoreLine
			$dismissWarnings = get_option( 'mainwp_child_dismiss_warnings' );
			if ( ! is_array( $dismissWarnings ) ) {
				$dismissWarnings = array();
			}

			if ( 'conflict' === $_POST['what'] ) { // @codingStandardsIgnoreLine
				$dismissWarnings['conflicts'] = self::get_conflicts();
			} elseif ( 'warning' === $_POST['what'] ) { // @codingStandardsIgnoreLine
				$dismissWarnings['warnings'] = self::get_warnings();
			}

			Main_WP_Helper::update_option( 'mainwp_child_dismiss_warnings', $dismissWarnings );
		}
	}

	public static function show_warnings() {
		if ( stristr( $_SERVER['REQUEST_URI'], 'Main_WP_Child_Server_Information' ) ) {
			return;
		}

		$conflicts = self::get_conflicts();
		$warnings = self::get_warnings();

		$dismissWarnings = get_option( 'mainwp_child_dismiss_warnings' );
		if ( ! is_array( $dismissWarnings ) ) {
			$dismissWarnings = array();
		}

		if ( isset( $dismissWarnings['warnings'] ) && $dismissWarnings['warnings'] >= $warnings ) {
			$warnings = 0;
		}
		if ( isset( $dismissWarnings['conflicts'] ) && Main_WP_Helper::containsAll( $dismissWarnings['conflicts'], $conflicts ) ) {
			$conflicts = array();
		}

		if ( 0 === $warnings && 0 === count( $conflicts ) ) {
			return;
		}

		if ( $warnings > 0 ) {
			$dismissWarnings['warnings'] = 0;
		}

		if ( count( $conflicts ) > 0 ) {
			$dismissWarnings['conflicts'] = array();
		}
		Main_WP_Helper::update_option( 'mainwp_child_dismiss_warnings', $dismissWarnings );

		$itheme_ext_activated = ( get_option( 'mainwp_ithemes_ext_activated' ) === 'Y' ) ? true : false;
		if ( $itheme_ext_activated ) {
			foreach ( $conflicts as $key => $cf ) {
				if ( 'iThemes Security' === $cf ) {
					unset( $conflicts[ $key ] );
				}
			}
			if ( 0 === $warnings && 0 === count( $conflicts ) ) {
				return;
			}
		}

		?>
		<script language="javascript">
			dismiss_warnings = function( pElement, pAction ) {
				var table = jQuery( pElement.parents('table')[0] );
				pElement.parents( 'tr' )[0].remove();
				if ( table.find( 'tr' ).length == 0 ) {
					jQuery( '#mainwp-child_server_warnings' ).hide();
				}

				var data = {
					action:'mainwp-child_dismiss_warnings',
					what: pAction
				};

				jQuery.ajax({
					type:"POST",
					url: ajaxurl,
					data: data,
					success: function (resp ) { },
					error: function() { },
					dataType: 'json'
				});

				return false;
			};
			jQuery( document ).on( 'click', '#mainwp-child-connect-warning-dismiss', function() {
				return dismiss_warnings( jQuery( this ), 'warning' );
			});
			jQuery( document ).on( 'click', '#mainwp-child-all-pages-warning-dismiss', function() {
				return dismiss_warnings( jQuery( this ), 'conflict' );
			});
		</script>
		<style type="text/css">
			.mainwp-child_info-box-red-warning {
				background-color: rgba(187, 114, 57, 0.2) !important;
				border-bottom: 4px solid #bb7239 !important;
				border-top: 1px solid #bb7239 !important;
				border-left: 1px solid #bb7239 !important;
				border-right: 1px solid #bb7239 !important;
				-webkit-border-radius: 3px;
				   -moz-border-radius: 3px;
				        border-radius: 3px;
				margin: 1em 0 !important;

				background-image: url('<?php echo esc_url( plugins_url( 'images/mainwp-icon-orange.png', dirname( __FILE__ ) ) ); ?>' ) !important;
				background-position: 1.5em 50% !important;
				background-repeat: no-repeat !important;
				background-size: 30px !important;
			}
			.mainwp-child_info-box-red-warning table {
				background-color: rgba(187, 114, 57, 0) !important;
				border: 0;
				padding-left: 4.5em;
				background-position: 1.5em 50% !important;
				background-repeat: no-repeat !important;
				background-size: 30px !important;
			}
		</style>

		<div class="updated mainwp-child_info-box-red-warning" id="mainwp-child_server_warnings">
			<table id="mainwp-table" class="wp-list-table widefat" cellspacing="0">
				<tbody id="the-sites-list" class="list:sites">
					<?php
					$warning = '';

					if ( $warnings > 0 ) {
						$warning .= '<tr><td colspan="2">This site may not connect to your dashboard or may have other issues. Check your <a href="admin.php?page=Main_WP_Child_Server_Information">MainWP Server Information page</a> to review and <a href="http://docs.mainwp.com/child-site-issues/">check here for more information on possible fixes</a></td><td style="text-align: right;"><a href="#" id="mainwp-child-connect-warning-dismiss">Dismiss</a></td></tr>';
					}

					if ( count( $conflicts ) > 0 ) {
						$warning .= '<tr><td colspan="2">';
						if ( 1 === count( $conflicts ) ) {
							$warning .= '"' . $conflicts[0] . '" is';
						} else {
							$warning .= '"' . join( '", "', $conflicts ) . '" are';
						}
						$warning .= ' installed on this site. This is known to have a potential conflict with MainWP functions. <a href="http://docs.mainwp.com/known-plugin-conflicts/">Please click this link for possible solutions</a></td><td style="text-align: right;"><a href="#" id="mainwp-child-all-pages-warning-dismiss">Dismiss</a></td></tr>';
					}

					echo wp_kses_post( $warning );
					?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function render_page() {
		?>
		<div class="wrap">
	        <h2><?php esc_html_e( 'Plugin Conflicts' ); ?></h2>
	        <br/>
			<?php Main_WP_Child_Server_Information::render_conflicts(); ?>
			<h2><?php esc_html_e( 'Server Information' ); ?></h2>
			<?php Main_WP_Child_Server_Information::render(); ?>
			<h2><?php esc_html_e( 'Cron Schedules' ); ?></h2>
			<?php Main_WP_Child_Server_Information::render_cron(); ?>
			<h2><?php esc_html_e( 'Error Log' ); ?></h2>
			<?php Main_WP_Child_Server_Information::render_error_log_page(); ?>
		</div>
		<?php
	}

	/**
	 * @return int
	 */
	public static function get_warnings() {
		$i = 0;

		if ( ! self::check( '>=', '3.4', 'get_wordpress_version' ) ) {
			$i++;
		}
		if ( ! self::check( '>=', '5.2.4', 'get_php_version' ) ) {
			$i++;
		}
		if ( ! self::check( '>=', '5.0', 'get_mysql_version' ) ) {
			$i++;
		}
		if ( ! self::check( '>=', '30', 'get_max_execution_time', '=', '0' ) ) {
			$i++;
		}
		if ( ! self::check( '>=', '2M', 'get_upload_max_filesize' ) ) {
			$i++;
		}
		if ( ! self::check( '>=', '2M', 'get_post_max_size' ) ) {
			$i++;
		}
		if ( ! self::check( '>=', '10000', 'get_output_buffer_size' ) ) {
			$i++;
		}

		if ( ! self::check_directory_main_wp_directory( false ) ) {
			$i++;
		}

		return $i;
	}

	/**
	 * @return array
	 */
	public static function get_conflicts() {
		global $mainWPChild;

		$pluginConflicts = array(
			'Better WP Security',
			'iThemes Security',
			'Secure WordPress',
			'Wordpress Firewall',
			'Bad Behavior',
			'SpyderSpanker',
		);

		$conflicts = array();

		if ( count( $pluginConflicts ) > 0 ) {
			$plugins = $mainWPChild->get_all_plugins_int( false );
			foreach ( $plugins as $plugin ) {
				foreach ( $pluginConflicts as $pluginConflict ) {
					if ( 1 === $plugin['active'] && ( ( $plugin['name'] === $pluginConflict ) || ( $plugin['slug'] === $pluginConflict ) ) ) {
						$conflicts[] = $plugin['name'];
					}
				}
			}
		}

		return $conflicts;
	}

	public static function render_conflicts() {
		$conflicts = self::get_conflicts();
		$branding_title = 'MainWP';
		if ( Main_WP_Child_Branding::is_branding() ) {
			$branding_title = Main_WP_Child_Branding::get_branding();
		}

		if ( count( $conflicts ) > 0 ) {
			$information['pluginConflicts'] = $conflicts;
			?>
			<style type="text/css">
				.mainwp-child_info-box-warning {
					background-color: rgba(187, 114, 57, 0.2) !important;
					border-bottom: 4px solid #bb7239 !important;
					border-top: 1px solid #bb7239 !important;
					border-left: 1px solid #bb7239 !important;
					border-right: 1px solid #bb7239 !important;
					-webkit-border-radius: 3px;
					    -moz-border-radius: 3px;
					         border-radius: 3px;
					<?php if ( ! Main_WP_Child_Branding::is_branding() ) : ?>
					padding-left: 4.5em;
					background-image: url('<?php echo esc_url( plugins_url( 'images/mainwp-icon-orange.png', dirname( __FILE__ ) ) ); ?>') !important;
					<?php endif; ?>
					background-position: 1.5em 50% !important;
					background-repeat: no-repeat !important;
					background-size: 30px !important;
				}
			</style>
			<table id="mainwp-table" class="wp-list-table widefat mainwp-child_info-box-warning" cellspacing="0">
				<tbody id="the-sites-list" class="list:sites">
					<tr>
						<td colspan="2">
							<?php // @TODO: Use _n() ?>
							<strong><?php echo esc_html( count( $conflicts ) ); ?> plugin conflict<?php echo esc_html( count( $conflicts ) > 1 ? 's' : '' ); ?> found</strong>
						</td>
						<td style="text-align: right;"></td>
					</tr>
					<?php foreach ( $conflicts as $conflict ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $conflict ); ?></strong> is installed on this site. This plugin is known to have a potential conflict with <?php echo esc_html( $branding_title ); ?> functions. <a href="http://docs.mainwp.com/known-plugin-conflicts/">Please click this link for possible solutions</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		} else {
			?>
			<style type="text/css">
				.mainwp-child_info-box {
					background-color: rgba(127, 177, 0, 0.2) !important;
					border-bottom: 4px solid #7fb100 !important;
					border-top: 1px solid #7fb100 !important;
					border-left: 1px solid #7fb100 !important;
					border-right: 1px solid #7fb100 !important;
					-webkit-border-radius: 3px;
					   -moz-border-radius: 3px;
					        border-radius: 3px;
					<?php if ( ! Main_WP_Child_Branding::is_branding() ) : ?>
					padding-left: 4.5em;
					background-image: url('<?php echo esc_url( plugins_url( 'images/mainwp-icon.png', dirname( __FILE__ ) ) ); ?>') !important;
					<?php endif; ?>
					background-position: 1.5em 50% !important;
					background-repeat: no-repeat !important;
					background-size: 30px !important;
				}
			</style>
			<table id="mainwp-table" class="wp-list-table widefat mainwp-child_info-box" cellspacing="0">
				<tbody id="the-sites-list" class="list:sites">
					<tr>
						<td>No conflicts found.</td>
						<td style="text-align: right;"><a href="#" id="mainwp-child-info-dismiss">Dismiss</a></td>
					</tr>
				</tbody>
			</table>
			<?php
		}
		?>
		<br />
		<?php
	}

	/**
	 * @return string
	 */
	protected static function get_file_system_method() {
		$fs = get_filesystem_method();
		return $fs;
	}

	/**
	 * @return string
	 */
	protected static function get_file_system_method_check() {
		$fsmethod = self::get_file_system_method();
		if ( 'direct' === $fsmethod ) {
			return '<span class="mainwp-pass"><i class="fa fa-check-circle"></i> Pass</span>';
		} else {
			return '<span class="mainwp-warning"><i class="fa fa-exclamation-circle"></i> Warning</span>';
		}
	}

	public static function render() {
		$branding_title = 'MainWP Child';
		if ( Main_WP_Child_Branding::is_branding() ) {
			$branding_title = Main_WP_Child_Branding::get_branding();
		}
		?>
		<br />
		<table id="mainwp-table" class="wp-list-table widefat" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" class="manage-column column-posts mwp-not-generate-row" style="width: 1px;"><?php esc_html_e( '','mainwp-child' ); ?></th>
					<th scope="col" class="manage-column column-posts" style=""><span><?php esc_html_e( 'Server Configuration','mainwp-child' ); ?></span></th>
					<th scope="col" class="manage-column column-posts" style=""><?php esc_html_e( 'Required Value','mainwp' ); ?></th>
					<th scope="col" class="manage-column column-posts" style=""><?php esc_html_e( 'Value','mainwp' ); ?></th>
					<th scope="col" class="manage-column column-posts" style=""><?php esc_html_e( 'Status','mainwp' ); ?></th>
				</tr>
			</thead>
			<tbody id="the-sites-list" class="list:sites">
				<tr>
					<td style="background: #333; color: #fff;" colspan="5"><?php echo esc_html( strtoupper( $branding_title ) ); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php echo wp_kses_post( $branding_title ); ?> Version</td>
					<td><?php echo wp_kses_post( self::get_main_wp_version() ); ?></td>
					<td><?php echo wp_kses_post( self::get_current_version() ); ?></td>
					<td><?php echo wp_kses_post( self::get_main_wp_version_check() ); ?></td>
				</tr>

				<?php self::check_directory_main_wp_directory(); ?>

				<tr>
					<td style="background: #333; color: #fff;" colspan="5"><?php esc_html_e( 'WORDPRESS','mainwp-child' ); ?></td>
				</tr>

				<?php self::render_row( 'WordPress Version', '>=', '3.4', 'get_wordpress_version' ); ?>

				<tr>
					<td style="background: #333; color: #fff;" colspan="5"><?php esc_html_e( 'PHP SETTINGS','mainwp-child' ); ?></td>
				</tr>

				<?php
				self::render_row( 'PHP Version', '>=', '5.3', 'get_php_version' );
				self::render_row( 'PHP Max Execution Time', '>=', '30', 'get_max_execution_time', 'seconds', '=', '0' );
				self::render_row( 'PHP Upload Max Filesize', '>=', '2M', 'get_upload_max_filesize', '(2MB+ best for upload of big plugins)', null, null, true );
				self::render_row( 'PHP Post Max Size', '>=', '2M', 'get_post_max_size', '(2MB+ best for upload of big plugins)', null, null, true );
				self::render_row( 'PHP Memory Limit', '>=', '128M', 'get_php_memory_limit', '(256M+ best for big backups)' , null, null, true );
				self::render_row( 'SSL Extension Enabled', '=', true, 'get_ssl_support' );
				?>

				<tr>
					<td style="background: #333; color: #fff;" colspan="5"><?php esc_html_e( 'MISC','mainwp-child' ); ?></td>
				</tr>

				<?php self::render_row( 'PCRE Backtracking Limit', '>=', '10000', 'get_output_buffer_size' ); ?>

				<tr>
					<td></td>
					<td><?php wp_kses_post( 'FileSystem Method','mainwp' ); ?></td>
					<td><?php echo wp_kses_post( '= ' . __( 'direct','mainwp' ) ); ?></td>
					<td><?php echo wp_kses_post( self::get_file_system_method() ); ?></td>
					<td><?php echo wp_kses_post( self::get_file_system_method_check() ); ?></td>
				</tr>
				<tr>
					<td style="background: #333; color: #fff;" colspan="5"><?php esc_html_e( 'MySQL SETTINGS','mainwp-child' ); ?></td>
				</tr>

				<?php self::render_row( 'MySQL Version', '>=', '5.0', 'get_mysql_version' ); ?>

				<tr>
					<td style="background: #333; color: #fff;" colspan="5"><?php esc_html_e( 'BACKUP ARCHIVE INFORMATION','mainwp-child' ); ?></td>
				</tr>

				<?php
				self::render_row( 'ZipArchive enabled in PHP', '=', true, 'get_zip_archive_enabled' );
				self::render_row( 'Tar GZip supported', '=', true, 'get_gzip_enabled' );
				self::render_row( 'Tar BZip2 supported', '=', true, 'get_bzip_enabled' );
				?>

				<tr>
					<td style="background: #333; color: #fff;" colspan="5"><?php esc_html_e( 'SERVER INFORMATION','mainwp' ); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'WordPress Root Directory','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_wp_root(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Server Name','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_server_name(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Server Sofware','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_server_software(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Operating System','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_os(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Architecture','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_architecture(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Server IP','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_server_ip(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Server Protocol','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_server_protocol(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'HTTP Host','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_http_host(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Server Admin','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_server_admin(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Server Port','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_server_port(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Getaway Interface','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_server_getaway_interface(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Memory Usage','mainwp' ); ?></td>
					<td colspan="3"><?php self::memory_usage(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'HTTPS','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_https(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'User Agent','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_user_agent(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Complete URL','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_complete_url(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Request Method','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_server_request_method(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Request Time','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_server_request_time(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Query String','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_server_query_string(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Accept Content','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_server_http_accept(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Accept-Charset Content','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_server_accept_charset(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Currently Executing Script Pathname','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_script_filename(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Server Signature','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_server_signature(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Currently Executing Script','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_currently_executing_script(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Path Translated','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_server_path_translated(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Current Script Path','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_script_name(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Current Page URI','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_current_page_uri(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Remote Address','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_remote_address(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Remote Host','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_remote_host(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'Remote Port','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_remote_post(); ?></td>
				</tr>
				<tr>
					<td style="background: #333; color: #fff;" colspan="5"><?php esc_html_e( 'PHP INFORMATION','mainwp' ); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'PHP Safe Mode Disabled','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_php_safe_mode(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'PHP Allow URL fopen','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_php_allow_url_fopen(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'PHP Exif Support','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_php_exif(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'PHP IPTC Support','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_php_iptc(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'PHP XML Support','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_php_xml(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'PHP Disabled Functions','mainwp' ); ?></td>
					<td colspan="3"><?php self::main_wp_required_functions(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'PHP Loaded Extensions','mainwp' ); ?></td>
					<td colspan="3" style="width: 73% !important;"><?php self::get_loaded__php_extensions(); ?></td>
				</tr>
				<tr>
					<td style="background: #333; color: #fff;" colspan="5"><?php esc_html_e( 'MySQL INFORMATION','mainwp' ); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'MySQL Mode','mainwp' ); ?></td>
					<td colspan="3"><?php self::get_sql_mode(); ?></td>
				</tr>
				<tr>
					<td></td>
					<td><?php esc_html_e( 'MySQL Client Encoding','mainwp' ); ?></td>
					<td colspan="3"><?php echo esc_html( defined( 'DB_CHARSET' ) ? DB_CHARSET : '' ); ?></td>
				</tr>
			</tbody>
		</table>
		<br />
		<?php
	}

	public static function main_wp_required_functions() {
		//error_reporting(E_ALL);
		$disabled_functions = ini_get( 'disable_functions' );
		if ( '' !== $disabled_functions ) {
			$arr = explode( ',', $disabled_functions );
			sort( $arr );
			$total = count( $arr );
			for ( $i = 0; $i < $total; $i++ ) {
				echo esc_html( $arr[ $i ] . ', ' );
			}
		} else {
			  esc_html_e( 'No functions disabled','mainwp' );
		}
	}

	protected static function get_loaded__php_extensions() {
		$extensions = get_loaded_extensions();
		sort( $extensions );
		echo esc_html( implode( ', ', $extensions ) );
	}

	/**
	 * @return mixed|void
	 */
	protected static function get_current_version() {
		$currentVersion = get_option( 'mainwp_child_plugin_version' );
		return $currentVersion;
	}

	/**
	 * @return bool
	 */
	protected static function get_main_wp_version() {
		include_once( ABSPATH . '/wp-admin/includes/plugin-install.php' );
		$api = plugins_api( 'plugin_information', array( 'slug' => 'mainwp-child', 'fields' => array( 'sections' => false ), 'timeout' => 60 ) );
		if ( is_object( $api ) && isset( $api->version ) ) {
			return $api->version;
		}
		return false;
	}

	/**
	 * @return string
	 */
	protected static function get_main_wp_version_check() {
		$current = get_option( 'mainwp_child_plugin_version' );
		$latest  = self::get_main_wp_version();
		if ( $current === $latest ) {
			return '<span class="mainwp-pass"><i class="fa fa-check-circle"></i> Pass</span>';
		} else {
			return '<span class="mainwp-warning"><i class="fa fa-exclamation-circle"></i> Warning</span>';
		}
	}

	public static function render_cron() {
		$cron_array = _get_cron_array();
		$schedules = wp_get_schedules();
		?>
		<table id="mainwp-table" class="wp-list-table widefat" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" class="manage-column column-posts" style=""><span><?php esc_html_e( 'Next due','mainwp' ); ?></span></th>
					<th scope="col" class="manage-column column-posts" style=""><span><?php esc_html_e( 'Schedule','mainwp' ); ?></span></th>
					<th scope="col" class="manage-column column-posts" style=""><span><?php esc_html_e( 'Hook','mainwp' ); ?></span></th>
				</tr>
			</thead>
			<tbody id="the-sites-list" class="list:sites">
			<?php
			foreach ( $cron_array as $time => $cron ) {
				foreach ( $cron as $hook => $cron_info ) {
					foreach ( $cron_info as $key => $schedule ) {
						?>
	                    <tr>
		                    <td><?php echo wp_kses_post( Main_WP_Helper::formatTimestamp( Main_WP_Helper::getTimestamp( $time ) ) ); ?></td>
		                    <td><?php echo wp_kses_post( ( isset( $schedule['schedule'] ) && isset( $schedules[ $schedule['schedule'] ] ) && isset( $schedules[ $schedule['schedule'] ]['display'] ) ) ? $schedules[ $schedule['schedule'] ]['display'] : '' );?> </td>
		                    <td><?php echo wp_kses_post( $hook ); ?></td>
	                    </tr>
	                    <?php
					}
				}
			}
			?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * @param bool|true $write
	 *
	 * @return bool
	 */
	protected static function check_directory_main_wp_directory( $write = true ) {
		$branding_title = 'MainWP';

		if ( Main_WP_Child_Branding::is_branding() ) {
			$branding_title = Main_WP_Child_Branding::get_branding();
		}
		$branding_title .= ' upload directory';

		try {
			$dirs = Main_WP_Helper::getMainWPDir( null, false );
			$path = $dirs[0];
		} catch (Exception $e) {
			return self::render_directory_row( $branding_title, '', 'Writable', $e->getMessage(), false );
		}

		if ( ! is_dir( dirname( $path ) ) ) {
			if ( $write ) {
				return self::render_directory_row( $branding_title, $path, 'Writable', 'Directory not found', false );
			} else {
				return false;
			}
		}

		$hasWPFileSystem = Main_WP_Helper::getWPFilesystem();
		global $wp_filesystem;

		if ( $hasWPFileSystem && ! empty( $wp_filesystem ) ) {
			if ( ! $wp_filesystem->is_writable( $path ) ) {
				if ( $write ) {
					return self::render_directory_row( $branding_title, $path, 'Writable', 'Directory not writable', false );
				} else {
					return false;
				}
			}
		} else {
			if ( ! is_writable( $path ) ) {
				if ( $write ) {
					return self::render_directory_row( $branding_title, $path, 'Writable', 'Directory not writable', false );
				} else {
					return false;
				}
			}
		}

		if ( $write ) {
			return self::render_directory_row( $branding_title, $path, 'Writable', 'Writable', true );
		} else {
			return true;
		}
	}

	/**
	 * @param $p_name
	 * @param $p_directory
	 * @param $p_check
	 * @param $p_result
	 * @param $p_passed
	 *
	 * @return bool
	 */
	protected static function render_directory_row( $p_name, $p_directory, $p_check, $p_result, $p_passed ) {
		?>
		<tr>
			<td></td>
			<td><?php echo wp_kses_post( $p_name ); ?><br/><?php echo esc_html( Main_WP_Child_Branding::is_branding() ? '' : $p_directory ); ?></td>
			<td><?php echo wp_kses_post( $p_check ); ?></td>
			<td><?php echo wp_kses_post( $p_result ); ?></td>
			<td><?php echo wp_kses_post( $p_passed ? '<span class="mainwp-pass"><i class="fa fa-check-circle"></i> Pass</span>' : '<span class="mainwp-warning"><i class="fa fa-exclamation-circle"></i> Warning</span>' ); ?></td>
		</tr>
		<?php
		return true;
	}

	/**
	 * @param $p_config
	 * @param $p_compare
	 * @param $p_version
	 * @param $p_getter
	 * @param string $p_extra_text
	 * @param null $p_extra_compare
	 * @param null $p_extra_version
	 * @param bool|false $compare_filesize
	 */
	protected static function render_row( $p_config, $p_compare, $p_version, $p_getter, $p_extra_text = '', $p_extra_compare = null, $p_extra_version = null, $compare_filesize = false ) {
		$currentVersion = call_user_func( array( 'Main_WP_Child_Server_Information', $p_getter ) );
		?>
		<tr>
			<td></td>
			<td><?php echo wp_kses_post( $p_config ); ?></td>
			<td><?php echo wp_kses_post( $p_compare ); ?>  <?php echo esc_html( true === $p_version ? 'true' : $p_version . ' ' . $p_extra_text ); ?></td>
			<td><?php echo wp_kses_post( true === $currentVersion ? 'true' : $currentVersion ); ?></td>
			<?php if ( $compare_filesize ) : ?>
			<td><?php echo wp_kses_post( self::filesize_compare( $currentVersion, $p_version, $p_compare ) ? '<span class="mainwp-pass"><i class="fa fa-check-circle"></i> Pass</span>' : '<span class="mainwp-warning"><i class="fa fa-exclamation-circle"></i> Warning</span>' ); ?></td>
			<?php else : ?>
			<td><?php echo wp_kses_post( self::check( $p_compare, $p_version, $p_getter, $p_extra_compare, $p_extra_version ) ? '<span class="mainwp-pass"><i class="fa fa-check-circle"></i> Pass</span>' : '<span class="mainwp-warning"><i class="fa fa-exclamation-circle"></i> Warning</span>' ); ?></td>
			<?php endif; ?>
		</tr>
		<?php
	}

	/**
	 * @param $value_1
	 * @param $value_2
	 * @param null $operator
	 *
	 * @return mixed
	 */
	protected static function filesize_compare( $value_1, $value_2, $operator = null ) {
		if ( strpos( $value_1, 'G' ) !== false ) {
			$value_1 = preg_replace( '/[A-Za-z]/', '', $value_1 );
			$value_1 = intval( $value_1 ) * 1024; // Megabyte number
		} else {
			$value_1 = preg_replace( '/[A-Za-z]/', '', $value_1 ); // Megabyte number
		}

		if ( strpos( $value_2, 'G' ) !== false ) {
			$value_2 = preg_replace( '/[A-Za-z]/', '', $value_2 );
			$value_2 = intval( $value_2 ) * 1024; // Megabyte number
		} else {
			$value_2 = preg_replace( '/[A-Za-z]/', '', $value_2 ); // Megabyte number
		}

		return version_compare( $value_1, $value_2, $operator );
	}

	/**
	 * @param $p_compare
	 * @param $p_version
	 * @param $p_getter
	 * @param null $p_extra_compare
	 * @param null $p_extra_version
	 *
	 * @return bool
	 */
	protected static function check( $p_compare, $p_version, $p_getter, $p_extra_compare = null, $p_extra_version = null ) {
		$currentVersion = call_user_func( array( 'Main_WP_Child_Server_Information', $p_getter ) );
		return version_compare( $currentVersion, $p_version, $p_compare ) || ( ( null !== $p_extra_compare ) && version_compare( $currentVersion, $p_extra_version, $p_extra_compare ));
	}

	/**
	 * @return bool
	 */
	protected static function get_zip_archive_enabled() {
		return class_exists( 'ZipArchive' );
	}

	/**
	 * @return bool
	 */
	protected static function get_gzip_enabled() {
		return function_exists( 'gzopen' );
	}

	/**
	 * @return bool
	 */
	protected static function get_bzip_enabled() {
		return function_exists( 'bzopen' );
	}

	/**
	 * @return string
	 */
	protected static function get_wordpress_version() {
		global $wp_version;
		return $wp_version;
	}

	/**
	 * @return bool
	 */
	protected static function get_ssl_support() {
		return extension_loaded( 'openssl' );
	}

	/**
	 * @return string
	 */
	protected static function get_php_version() {
		return phpversion();
	}

	/**
	 * @return string
	 */
	protected static function get_max_execution_time() {
		return ini_get( 'max_execution_time' );
	}

	/**
	 * @return string
	 */
	protected static function get_upload_max_filesize() {
		return ini_get( 'upload_max_filesize' );
	}

	/**
	 * @return string
	 */
	protected static function get_post_max_size() {
		return ini_get( 'post_max_size' );
	}

	/**
	 * @return null|string
	 */
	protected static function get_mysql_version() {
		global $wpdb;
		return $wpdb->get_var( 'SHOW VARIABLES LIKE "version"', 1 );
	}

	/**
	 * @return string
	 */
	protected static function get_php_memory_limit() {
		return ini_get( 'memory_limit' );
	}

	protected static function get_os() {
		echo esc_html( PHP_OS );
	}

	protected static function get_architecture() {
		echo wp_kses_post( ( PHP_INT_SIZE * 8 ) . '&nbsp;bit' );
	}

	protected static function memory_usage() {
		if ( function_exists( 'memory_get_usage' ) ) { $memory_usage = round( memory_get_usage() / 1024 / 1024, 2 ) . __( ' MB' );
		} else {
			$memory_usage = __( 'N/A' );
		}
		echo esc_html( $memory_usage );
	}

	/**
	 * @return string
	 */
	protected static function get_output_buffer_size() {
		return ini_get( 'pcre.backtrack_limit' );
	}

	protected static function get_php_safe_mode() {
		if ( ini_get( 'safe_mode' ) ) {
			$safe_mode = __( 'ON' );
		} else {
			$safe_mode = __( 'OFF' );
		}
		echo esc_html( $safe_mode );
	}

	protected static function get_sql_mode() {
		global $wpdb;
		$mysqlinfo = $wpdb->get_results( "SHOW VARIABLES LIKE 'sql_mode'" );
		if ( is_array( $mysqlinfo ) ) {
			$sql_mode = $mysqlinfo[0]->Value;
		}
		if ( empty( $sql_mode ) ) {
			$sql_mode = __( 'NOT SET' );
		}
		echo esc_html( $sql_mode );
	}

	protected static function get_php_allow_url_fopen() {
		if ( ini_get( 'allow_url_fopen' ) ) {
			$allow_url_fopen = __( 'ON' );
		} else {
			$allow_url_fopen = __( 'OFF' );
		}
		echo esc_html( $allow_url_fopen );
	}

	protected static function get_php_exif() {
		if ( is_callable( 'exif_read_data' ) ) {
			$exif = __( 'YES' ) . ' ( V' . substr( phpversion( 'exif' ), 0, 4 ) . ')';
		} else {
			$exif = __( 'NO' );
		}
		echo esc_html( $exif );
	}

	protected static function get_php_iptc() {
		if ( is_callable( 'iptcparse' ) ) {
			$iptc = __( 'YES' );
		} else {
			$iptc = __( 'NO' );
		}
		echo esc_html( $iptc );
	}

	protected static function get_php_xml() {
		if ( is_callable( 'xml_parser_create' ) ) {
			$xml = __( 'YES' );
		} else {
			$xml = __( 'NO' );
		}
		echo esc_html( $xml );
	}

	protected static function get_currently_executing_script() {
		echo esc_html( $_SERVER['PHP_SELF'] );
	}

	protected static function get_server_getaway_interface() {
		echo esc_html( $_SERVER['GATEWAY_INTERFACE'] );
	}

	protected static function get_server_ip() {
		echo esc_html( $_SERVER['SERVER_ADDR'] );
	}

	protected static function get_server_name() {
		echo esc_html( $_SERVER['SERVER_NAME'] );
	}

	protected static function get_server_software() {
		echo esc_html( $_SERVER['SERVER_SOFTWARE'] );
	}

	protected static function get_server_protocol() {
		echo esc_html( $_SERVER['SERVER_PROTOCOL'] );
	}

	protected static function get_server_request_method() {
		echo esc_html( $_SERVER['REQUEST_METHOD'] );
	}

	protected static function get_server_request_time() {
		echo esc_html( $_SERVER['REQUEST_TIME'] );
	}

	protected static function get_server_query_string() {
		echo esc_html( $_SERVER['QUERY_STRING'] );
	}

	protected static function get_server_http_accept() {
		echo esc_html( $_SERVER['HTTP_ACCEPT'] );
	}

	protected static function get_server_accept_charset() {
		if ( ! isset( $_SERVER['HTTP_ACCEPT_CHARSET'] ) || ( '' === $_SERVER['HTTP_ACCEPT_CHARSET'] ) ) {
			echo esc_html( __( 'N/A','mainwp' ) );
		} else {
			echo esc_html( $_SERVER['HTTP_ACCEPT_CHARSET'] );
		}
	}

	protected static function get_http_host() {
		echo esc_html( $_SERVER['HTTP_HOST'] );
	}

	protected static function get_complete_url() {
		echo esc_html( $_SERVER['HTTP_REFERER'] );
	}

	protected static function get_user_agent() {
		echo esc_html( $_SERVER['HTTP_USER_AGENT'] );
	}

	protected static function get_https() {
		if ( isset( $_SERVER['HTTPS'] ) && '' !== $_SERVER['HTTPS'] ) {
			echo esc_html( __( 'ON','mainwp' ) . ' - ' . $_SERVER['HTTPS'] );
		} else {
			echo esc_html( __( 'OFF','mainwp' ) );
		}
	}

	protected static function get_remote_address() {
		echo esc_html( $_SERVER['REMOTE_ADDR'] );
	}

	protected static function get_remote_host() {
		if ( ! isset( $_SERVER['REMOTE_HOST'] ) || ( '' === $_SERVER['REMOTE_HOST'] ) ) {
			echo esc_html( __( 'N/A','mainwp' ) );
		} else {
			echo esc_html( $_SERVER['REMOTE_HOST'] );
		}
	}

	protected static function get_remote_post() {
		echo esc_html( $_SERVER['REMOTE_PORT'] );
	}

	protected static function get_script_filename() {
		echo esc_html( $_SERVER['SCRIPT_FILENAME'] );
	}

	protected static function get_server_admin() {
		echo esc_html( $_SERVER['SERVER_ADMIN'] );
	}

	protected static function get_server_port() {
		echo esc_html( $_SERVER['SERVER_PORT'] );
	}

	protected static function get_server_signature() {
		echo esc_html( $_SERVER['SERVER_SIGNATURE'] );
	}

	protected static function get_server_path_translated() {
		if ( ! isset( $_SERVER['PATH_TRANSLATED'] ) || ( '' === $_SERVER['PATH_TRANSLATED'] ) ) {
			echo esc_html( __( 'N/A','mainwp' ) );
		} else {
			echo esc_html( $_SERVER['PATH_TRANSLATED'] );
		}
	}

	protected static function get_script_name() {
		echo esc_html( $_SERVER['SCRIPT_NAME'] );
	}

	protected static function get_current_page_uri() {
		echo esc_html( $_SERVER['REQUEST_URI'] );
	}
	protected static function get_wp_root() {
		echo esc_html( ABSPATH );
	}

	/**
	 * @param $bytes
	 *
	 * @return string
	 */
	function format_size_units( $bytes ) {
		if ( $bytes >= 1073741824 ) {
			$bytes = number_format( $bytes / 1073741824, 2 ) . ' GB';
		} elseif ( $bytes >= 1048576 ) {
			$bytes = number_format( $bytes / 1048576, 2 ) . ' MB';
		} elseif ( $bytes >= 1024 ) {
			$bytes = number_format( $bytes / 1024, 2 ) . ' KB';
		} elseif ( $bytes > 1 ) {
			$bytes = $bytes . ' bytes';
		} elseif ( 1 === $bytes ) {
			$bytes = $bytes . ' byte';
		} else {
			$bytes = '0 bytes';
		}

		return $bytes;
	}

	// @TODO: Remove this random comment
	 /*
	*Plugin Name: Error Log Dashboard Widget
	*Plugin URI: http://wordpress.org/extend/plugins/error-log-dashboard-widget/
	*Description: Robust zero-configuration and low-memory way to keep an eye on error log.
	*Author: Andrey "Rarst" Savchenko
	*Author URI: http://www.rarst.net/
	*Version: 1.0.2
	*License: GPLv2 or later

	*Includes last_lines() function by phant0m, licensed under cc-wiki and GPLv2+
	*/

	public static function render_error_log_page() {
		?>
		<table id="mainwp-table" class="wp-list-table widefat" cellspacing="0">
			<thead title="Click to Toggle" style="cursor: pointer;">
				<tr>
					<th scope="col" class="manage-column column-posts" style="width: 10%"><span><?php esc_html_e( 'Time','mainwp' ); ?></span></th>
					<th scope="col" class="manage-column column-posts" style=""><span><?php esc_html_e( 'Error','mainwp' ); ?></span></th>
				</tr>
			</thead>
			<tbody class="list:sites" id="mainwp-error-log-table">
				<?php self::render_error_log(); ?>
			</tbody>
		</table>
    <?php
	}

	public static function render_error_log() {
		$log_errors = ini_get( 'log_errors' );
		if ( ! $log_errors ) {
			echo wp_kses_post( '<tr><td colspan="2">' . __( 'Error logging disabled.', 'mainwp' ) . '</td></tr>' );
		}

		$error_log = ini_get( 'error_log' );
		$logs      = apply_filters( 'error_log_mainwp_logs', array( $error_log ) );
		$count     = apply_filters( 'error_log_mainwp_lines', 10 );
		$lines     = array();

		foreach ( $logs as $log ) {
			if ( is_readable( $log ) ) {
				$lines = array_merge( $lines, self::last_lines( $log, $count ) );
			}
		}

		$lines = array_map( 'trim', $lines );
		$lines = array_filter( $lines );

		if ( empty( $lines ) ) {
			echo wp_kses_post( '<tr><td colspan="2">' . __( 'MainWP is unable to find your error logs, please contact your host for server error logs.', 'mainwp' ) . '</td></tr>' );
			return;
		}

		foreach ( $lines as $key => $line ) {
			if ( false !== strpos( $line, ']' ) ) {
				list( $time, $error ) = explode( ']', $line, 2 );
			} else {
				list( $time, $error ) = array( '', $line );
			}

			$time  = trim( $time, '[]' );
			$error = trim( $error );

			$lines[ $key ] = compact( 'time', 'error' );
		}

		if ( count( $error_log ) > 1 ) {
			uasort( $lines, array( __CLASS__, 'time_compare' ) );
			$lines = array_slice( $lines, 0, $count );
		}

		foreach ( $lines as $line ) {
			$error = esc_html( $line['error'] );
			$time  = esc_html( $line['time'] );

			if ( ! empty( $error ) ) {
				echo wp_kses_post( "<tr><td>{$time}</td><td>{$error}</td></tr>" );
			}
		}
	}

	/**
	 * @param $a
	 * @param $b
	 *
	 * @return int
	 */
	static function time_compare( $a, $b ) {
		if ( $a === $b ) {
			return 0;
		}

		return ( strtotime( $a['time'] ) > strtotime( $b['time'] ) ) ? - 1 : 1;
	}

	/**
	 * @param $path
	 * @param $line_count
	 * @param int $block_size
	 *
	 * @return array
	 */
	static function last_lines( $path, $line_count, $block_size = 512 ) {
		$lines = array();

		// we will always have a fragment of a non-complete line
		// keep this in here till we have our next entire line.
		$leftover = '';

		$fh = fopen( $path, 'r' );
		// go to the end of the file
		fseek( $fh, 0, SEEK_END );

		$total_lines = count( $lines );

		do {
			// need to know whether we can actually go back
			// $block_size bytes
			$can_read = $block_size;

			if ( ftell( $fh ) <= $block_size ) {
				$can_read = ftell( $fh );
			}

			if ( empty( $can_read ) ) {
				break;
			}

			// go back as many bytes as we can
			// read them to $data and then move the file pointer
			// back to where we were.
			fseek( $fh, - $can_read, SEEK_CUR );
			$data  = fread( $fh, $can_read );
			$data .= $leftover;
			fseek( $fh, - $can_read, SEEK_CUR );

			// split lines by \n. Then reverse them,
			// now the last line is most likely not a complete
			// line which is why we do not directly add it, but
			// append it to the data read the next time.
			$split_data = array_reverse( explode( "\n", $data ) );
			$new_lines  = array_slice( $split_data, 0, - 1 );
			$lines      = array_merge( $lines, $new_lines );
			$leftover   = $split_data[ count( $split_data ) - 1 ];
		} while ( $total_lines < $line_count && ftell( $fh ) !== 0 );

		if ( 0 === ftell( $fh ) ) {
			$lines[] = $leftover;
		}

		fclose( $fh );
		// Usually, we will read too many lines, correct that here.
		return array_slice( $lines, 0, $line_count );
	}

	// @TODO: Change this to a private method, it could be abused to view the wp-config file
	public static function render_wp_config() {
		?>
		<style>
			#mainwp-code-display code {
				background: none !important;
			}
		</style>
		<div class="postbox" id="mainwp-code-display">
			<h3 class="hndle" style="padding: 8px 12px; font-size: 14px;"><span>WP-Config.php</span></h3>
			<div style="padding: 1em;">
				<?php
				/* @codingStandardsIgnoreStart */
			   @show_source( ABSPATH . 'wp-config.php' );
				/* @codingStandardsIgnoreEnd */
				?>
			</div>
		</div>
		<?php
	}

	// @TODO: Change this to a private method, it could be abused to view the wp-config file
	public static function render_htaccess() {
		?>
		<div class="postbox" id="mainwp-code-display">
			<h3 class="hndle" style="padding: 8px 12px; font-size: 14px;"><span>.htaccess</span></h3>
			<div style="padding: 1em;">
				<?php
				/* @codingStandardsIgnoreStart */
				@show_source( ABSPATH . '.htaccess' );
				/* @codingStandardsIgnoreEnd */
				?>
			</div>
		</div>
		<?php
	}
}

