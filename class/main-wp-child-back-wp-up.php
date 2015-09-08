<?php

if ( ! defined( 'MAINWP_BACKWPUP_DEVELOPMENT' ) ) {
	define( 'MAINWP_BACKWPUP_DEVELOPMENT', false );
}

/**
 * Class Main_WP_Child_Back_WP_Up
 */
class Main_WP_Child_Back_WP_Up {
	public $is_backwpup_installed = false;
	public $is_backwpup_pro = false;
	public $plugin_translate = 'mainwp-backwpup-extension';
	public static $instance = null;
	protected $software_version = '0.1';
	public static $information = array();

	protected $exclusions = array(
		'cron'           => array(
			'cronminutes',
			'cronhours',
			'cronmday',
			'cronmon',
			'cronwday',
			'moncronminutes',
			'moncronhours',
			'moncronmday',
			'weekcronminutes',
			'weekcronhours',
			'weekcronwday',
			'daycronminutes',
			'daycronhours',
			'hourcronminutes',
			'cronbtype',
		),
		'dest-EMAIL'     => array( 'emailpass' ),
		'dest-DBDUMP'    => array( 'dbdumpspecialsetalltables' ),
		'dest-FTP'       => array( 'ftppass' ),
		'dest-S3'        => array( 's3secretkey' ),
		'dest-MSAZURE'   => array( 'msazurekey' ),
		'dest-SUGARSYNC' => array( 'sugaremail', 'sugarpass', 'sugarrefreshtoken' ),
		'dest-GDRIVE'    => array( 'gdriverefreshtoken' ),
		'dest-RSC'       => array( 'rscapikey' ),
		'dest-GLACIER'   => array( 'glaciersecretkey' ),
	);

	/**
	 * @return Main_WP_Child_Back_WP_Up
	 */
	static function instance() {
		if ( null === Main_WP_Child_Back_WP_Up::$instance ) {
			Main_WP_Child_Back_WP_Up::$instance = new Main_WP_Child_Back_WP_Up();
		}

		return Main_WP_Child_Back_WP_Up::$instance;
	}

	/**
	 * Construct
	 */
	public function __construct() {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		if ( is_plugin_active( 'backwpup-pro/backwpup.php' ) && file_exists( plugin_dir_path( __FILE__ ) . '../../backwpup-pro/backwpup.php' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . '../../backwpup-pro/backwpup.php' );
			require_once( plugin_dir_path( __FILE__ ) . '../../backwpup-pro/inc/pro/class-pro.php' );
			BackWPup::get_instance();
			$this->is_backwpup_installed = true;
			$this->is_backwpup_pro       = true;
		} else if ( is_plugin_active( 'backwpup/backwpup.php' ) && file_exists( plugin_dir_path( __FILE__ ) . '../../backwpup/backwpup.php' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . '../../backwpup/backwpup.php' );
			BackWPup::get_instance();
			$this->is_backwpup_installed = true;
		}

		if ( $this->is_backwpup_installed ) {
			add_action( 'wp_ajax_mainwp_backwpup_download_backup', array( $this, 'download_backup' ) );
		}
	}

	public function action() {
		if ( ! $this->is_backwpup_installed ) {
			Main_WP_Helper::write( array( 'error' => __( 'Please install BackWPup plugin on child website', $this->plugin_translate ) ) );

			return;
		}

		error_reporting( 0 );

		// @TODO: Refactor to move this outside of the action method
		function mainwp_backwpup_handle_fatal_error() {
			$error = error_get_last();
			$allowed_html = array(
				'main_wp' => array()
			);
			if ( isset( $error['type'] ) && E_ERROR === $error['type'] && isset( $error['message'] ) ) {
				die( wp_kses( '<mainwp>' . base64_encode( serialize( array( 'error' => 'Main_WP_Child fatal error : ' . $error['message'] . ' Line: ' . $error['line'] . ' File: ' . $error['file'] ) ) ) . '</mainwp>', $allowed_html ) );
			} else if ( ! empty (Main_WP_Child_Back_WP_Up::$information ) ) {
				die( wp_kses( '<mainwp>' . base64_encode( serialize( Main_WP_Child_Back_WP_Up::$information ) ) . '</mainwp>', $allowed_html ) );
			} else {
				die( wp_kses( '<mainwp>' . base64_encode( array( 'error' => 'Missing information array inside fatal_error' ) ) . '</mainwp>', $allowed_html ) );
			}
		}

		register_shutdown_function( 'mainwp_backwpup_handle_fatal_error' );

		// @TODO: $information isn't used before it's set
		$information = array();

		// @TODO: Nonce validation check
		if ( ! isset( $_POST['action'] ) ) { // @codingStandardsIgnoreLine
			$information = array( 'error' => __( 'Missing action.', $this->plugin_translate ) );
		} else {
			switch ( $_POST['action'] ) { // @codingStandardsIgnoreLine
				case 'backwpup_update_settings':
					$information = $this->update_settings();
					break;

				case 'backwpup_insert_or_update_jobs':
					$information = $this->insert_or_update_jobs();
					break;

				case 'backwpup_insert_or_update_jobs_global':
					$information = $this->insert_or_update_jobs_global();
					break;

				case 'backwpup_get_child_tables':
					$information = $this->get_child_tables();
					break;

				case 'backwpup_get_job_files':
					$information = $this->get_job_files();
					break;

				case 'backwpup_destination_email_check_email':
					$information = $this->destination_email_check_email();
					break;

				case 'backwpup_backup_now':
					$information = $this->backup_now();
					break;

				case 'backwpup_ajax_working':
					$information = $this->ajax_working();
					break;

				case 'backwpup_backup_abort':
					$information = $this->backup_abort();
					break;

				case 'backwpup_tables':
					$information = $this->tables();
					break;

				case 'backwpup_view_log':
					$information = $this->view_log();
					break;

				case 'backwpup_delete_log':
					$information = $this->delete_log();
					break;

				case 'backwpup_delete_job':
					$information = $this->delete_job();
					break;

				case 'backwpup_delete_backup':
					$information = $this->delete_backup();
					break;

				case 'backwpup_information':
					$information = $this->information();
					break;

				case 'backwpup_wizard_system_scan':
					$information = $this->wizard_system_scan();
					break;

				case 'backwpup_is_pro':
					$information = array( 'is_pro' => $this->is_backwpup_pro );
					break;

				case 'backwpup_show_hide':
					$information = $this->show_hide();
					break;

				default:
					$information = array( 'error' => __( 'Wrong action.', $this->plugin_translate ) );
			}
		}

		Main_WP_Child_Back_WP_Up::$information = $information;
		exit();
	}

	public function init() {
		if ( 'Y' !== get_option( 'mainwp_backwpup_ext_enabled' ) ) {
			return;
		}

		if ( get_option( 'mainwp_backwpup_hide_plugin' ) === 'hide' ) {
			add_filter( 'all_plugins', array( $this, 'all_plugins' ) );
			add_action( 'admin_menu', array( $this, 'remove_menu' ) );
		}
	}

	/**
	 * @param $plugins
	 *
	 * @return mixed
	 */
	public function all_plugins( $plugins ) {
		foreach ( $plugins as $key => $value ) {
			$plugin_slug = basename( $key, '.php' );
			if ( 'backwpup' === $plugin_slug ) {
				unset( $plugins[ $key ] );
			}
		}

		return $plugins;
	}

	public function remove_menu() {
		global $submenu;

		if ( isset( $submenu['backwpup'] ) ) {
			unset( $submenu['backwpup'] );
		}

		remove_menu_page( 'backwpup' );

		$pos = stripos( $_SERVER['REQUEST_URI'], 'admin.php?page=backwpup' );
		if ( false !== $pos ) {
			wp_redirect( get_option( 'siteurl' ) . '/wp-admin/index.php' );
			exit();
		}
	}

	/**
	 * @return array
	 */
	protected function show_hide() {
		Main_WP_Helper::update_option( 'mainwp_backwpup_ext_enabled', 'Y' );

		// @TODO: Nonce validation check
		$hide = isset( $_POST['show_hide'] ) && ( '1' === $_POST['show_hide'] ) ? 'hide' : ''; // @codingStandardsIgnoreLine

		Main_WP_Helper::update_option( 'mainwp_backwpup_hide_plugin', $hide );

		return array( 'success' => 1 );
	}

	/**
	 * @return array
	 */
	protected function information() {
		global $wpdb;
		// Copied from BackWPup_Page_Settings
		ob_start();
		echo '<table class="wp-list-table widefat fixed" cellspacing="0" style="width: 85%;margin-left:auto;;margin-right:auto;">';
		echo '<thead><tr><th width="35%">' . esc_html__( 'Setting', 'backwpup' ) . '</th><th>' . esc_html__( 'Value', 'backwpup' ) . '</th></tr></thead>';
		echo '<tfoot><tr><th>' . esc_html__( 'Setting', 'backwpup' ) . '</th><th>' . esc_html__( 'Value', 'backwpup' ) . '</th></tr></tfoot>';
		echo '<tr title="&gt;=3.2"><td>' . esc_html__( 'WordPress version', 'backwpup' ) . '</td><td>' . esc_html( BackWPup::get_plugin_data( 'wp_version' ) ) . '</td></tr>';
		if ( ! class_exists( 'BackWPup_Pro', false ) ) {
			echo '<tr title=""><td>' . esc_html__( 'BackWPup version', 'backwpup' ) . '</td><td>' . esc_html( BackWPup::get_plugin_data( 'Version' ) ) . ' <a href="' . esc_url( translate( BackWPup::get_plugin_data( 'pluginuri' ), 'backwpup' ) ) . '">' . esc_html__( 'Get pro.', 'backwpup' ) . '</a></td></tr>';
		} else {
			echo '<tr title=""><td>' . esc_html__( 'BackWPup Pro version', 'backwpup' ) . '</td><td>' . esc_html( BackWPup::get_plugin_data( 'Version' ) ) . '</td></tr>';
		}

		echo '<tr title="&gt;=5.3.3"><td>' . esc_html__( 'PHP version', 'backwpup' ) . '</td><td>' . esc_html( PHP_VERSION ) . '</td></tr>';
		echo '<tr title="&gt;=5.0.7"><td>' . esc_html__( 'MySQL version', 'backwpup' ) . '</td><td>' . esc_html( $wpdb->get_var( 'SELECT VERSION() AS version' ) ) . '</td></tr>';
		if ( function_exists( 'curl_version' ) ) {
			$curlversion = curl_version();
			echo '<tr title=""><td>' . esc_html__( 'cURL version', 'backwpup' ) . '</td><td>' . esc_html( $curlversion['version'] ) . '</td></tr>';
			echo '<tr title=""><td>' . esc_html__( 'cURL SSL version', 'backwpup' ) . '</td><td>' . esc_html( $curlversion['ssl_version'] ) . '</td></tr>';
		} else {
			echo '<tr title=""><td>' . esc_html__( 'cURL version', 'backwpup' ) . '</td><td>' . esc_html__( 'unavailable', 'backwpup' ) . '</td></tr>';
		}
		echo '<tr title=""><td>' . esc_html__( 'WP-Cron url:', 'backwpup' ) . '</td><td>' . esc_html( site_url( 'wp-cron.php' ) ) . '</td></tr>';

		echo '<tr><td>' . esc_html__( 'Server self connect:', 'backwpup' ) . '</td><td>';
		$raw_response = BackWPup_Job::get_jobrun_url( 'test' );
		$test_result  = '';
		if ( is_wp_error( $raw_response ) ) {
			$test_result .= sprintf( __( 'The HTTP response test get an error "%s"', 'backwpup' ), esc_html( $raw_response->get_error_message() ) );
		} elseif ( 200 !== (int) wp_remote_retrieve_response_code( $raw_response ) && 204 !== (int) wp_remote_retrieve_response_code( $raw_response ) ) {
			$test_result .= sprintf( __( 'The HTTP response test get a false http status (%s)', 'backwpup' ), esc_html( wp_remote_retrieve_response_code( $raw_response ) ) );
		}
		$headers = wp_remote_retrieve_headers( $raw_response );
		if ( isset( $headers['x-backwpup-ver'] ) && BackWPup::get_plugin_data( 'version' ) !== $headers['x-backwpup-ver'] ) {
			$test_result .= sprintf( __( 'The BackWPup HTTP response header returns a false value: "%s"', 'backwpup' ), esc_html( $headers['x-backwpup-ver'] ) );
		}

		if ( empty( $test_result ) ) {
			esc_html_e( 'Response Test O.K.', 'backwpup' );
		} else {
			echo esc_html( $test_result );
		}
		echo '</td></tr>';

		echo '<tr><td>' . esc_html__( 'Temp folder:', 'backwpup' ) . '</td><td>';
		if ( ! is_dir( BackWPup::get_plugin_data( 'TEMP' ) ) ) {
			echo sprintf( esc_html__( 'Temp folder %s doesn\'t exist.', 'backwpup' ), esc_html( BackWPup::get_plugin_data( 'TEMP' ) ) );
		} elseif ( ! is_writable( BackWPup::get_plugin_data( 'TEMP' ) ) ) {
			echo sprintf( esc_html__( 'Temporary folder %s is not writable.', 'backwpup' ), esc_html( BackWPup::get_plugin_data( 'TEMP' ) ) );
		} else {
			echo esc_html( BackWPup::get_plugin_data( 'TEMP' ) );
		}
		echo '</td></tr>';

		echo '<tr><td>' . esc_html__( 'Log folder:', 'backwpup' ) . '</td><td>';
		if ( ! is_dir( get_site_option( 'backwpup_cfg_logfolder' ) ) ) {
			echo sprintf( esc_html__( 'Logs folder %s not exist.', 'backwpup' ), esc_html( get_site_option( 'backwpup_cfg_logfolder' ) ) );
		} elseif ( ! is_writable( get_site_option( 'backwpup_cfg_logfolder' ) ) ) {
			echo sprintf( esc_html__( 'Log folder %s is not writable.', 'backwpup' ), esc_html( get_site_option( 'backwpup_cfg_logfolder' ) ) );
		} else {
			echo esc_html( get_site_option( 'backwpup_cfg_logfolder' ) );
		}
		echo '</td></tr>';
		echo '<tr title=""><td>' . esc_html__( 'Server', 'backwpup' ) . '</td><td>' . esc_html( $_SERVER['SERVER_SOFTWARE'] ) . '</td></tr>';
		echo '<tr title=""><td>' . esc_html__( 'Operating System', 'backwpup' ) . '</td><td>' . esc_html( PHP_OS ) . '</td></tr>';
		echo '<tr title=""><td>' . esc_html__( 'PHP SAPI', 'backwpup' ) . '</td><td>' . esc_html( PHP_SAPI ) . '</td></tr>';
		echo '<tr title=""><td>' . esc_html__( 'Current PHP user', 'backwpup' ) . '</td><td>' . esc_html( get_current_user() ) . '</td></tr>';
		$text = (bool) ini_get( 'safe_mode' ) ? __( 'On', 'backwpup' ) : __( 'Off', 'backwpup' );
		echo '<tr title=""><td>' . esc_html__( 'Safe Mode', 'backwpup' ) . '</td><td>' . esc_html( $text ) . '</td></tr>';
		echo '<tr title="&gt;=30"><td>' . esc_html__( 'Maximum execution time', 'backwpup' ) . '</td><td>' . esc_html( ini_get( 'max_execution_time' ) ) . ' ' . esc_html__( 'seconds', 'backwpup' ) . '</td></tr>';
		if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
			echo '<tr title="ALTERNATE_WP_CRON"><td>' . esc_html__( 'Alternative WP Cron', 'backwpup' ) . '</td><td>' . esc_html__( 'On', 'backwpup' ) . '</td></tr>';
		} else {
			echo '<tr title="ALTERNATE_WP_CRON"><td>' . esc_html__( 'Alternative WP Cron', 'backwpup' ) . '</td><td>' . esc_html__( 'Off', 'backwpup' ) . '</td></tr>';
		}
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			echo '<tr title="DISABLE_WP_CRON"><td>' . esc_html__( 'Disabled WP Cron', 'backwpup' ) . '</td><td>' . esc_html__( 'On', 'backwpup' ) . '</td></tr>';
		} else {
			echo '<tr title="DISABLE_WP_CRON"><td>' . esc_html__( 'Disabled WP Cron', 'backwpup' ) . '</td><td>' . esc_html__( 'Off', 'backwpup' ) . '</td></tr>';
		}
		if ( defined( 'FS_CHMOD_DIR' ) ) {
			echo '<tr title="FS_CHMOD_DIR"><td>' . esc_html__( 'CHMOD Dir', 'backwpup' ) . '</td><td>' . esc_html( FS_CHMOD_DIR ) . '</td></tr>';
		} else {
			echo '<tr title="FS_CHMOD_DIR"><td>' . esc_html__( 'CHMOD Dir', 'backwpup' ) . '</td><td>0755</td></tr>';
		}

		$now = localtime( time(), true );
		/* @codingStandardsIgnoreStart */
		$memory_usage = @memory_get_usage( true );
		/* @codingStandardsIgnoreEnd */
		echo '<tr title=""><td>' . esc_html__( 'Server Time', 'backwpup' ) . '</td><td>' . esc_html( $now['tm_hour'] ) . ':' . esc_html( $now['tm_min'] ) . '</td></tr>';
		echo '<tr title=""><td>' . esc_html__( 'Blog Time', 'backwpup' ) . '</td><td>' . esc_html( date_i18n( 'H:i' ) ) . '</td></tr>';
		echo '<tr title=""><td>' . esc_html__( 'Blog Timezone', 'backwpup' ) . '</td><td>' . esc_html( get_option( 'timezone_string' ) ) . '</td></tr>';
		echo '<tr title=""><td>' . esc_html__( 'Blog Time offset', 'backwpup' ) . '</td><td>' . sprintf( esc_html__( '%s hours', 'backwpup' ), esc_html( get_option( 'gmt_offset' ) ) ) . '</td></tr>';
		echo '<tr title="WPLANG"><td>' . esc_html__( 'Blog language', 'backwpup' ) . '</td><td>' . esc_html( get_bloginfo( 'language' ) ) . '</td></tr>';
		echo '<tr title="utf8"><td>' . esc_html__( 'MySQL Client encoding', 'backwpup' ) . '</td><td>';
		echo defined( 'DB_CHARSET' ) ? esc_html( DB_CHARSET ) : '';
		echo '</td></tr>';
		echo '<tr title="URF-8"><td>' . esc_html__( 'Blog charset', 'backwpup' ) . '</td><td>' . esc_html( get_bloginfo( 'charset' ) ) . '</td></tr>';
		echo '<tr title="&gt;=128M"><td>' . esc_html__( 'PHP Memory limit', 'backwpup' ) . '</td><td>' . esc_html( ini_get( 'memory_limit' ) ) . '</td></tr>';
		echo '<tr title="WP_MEMORY_LIMIT"><td>' . esc_html__( 'WP memory limit', 'backwpup' ) . '</td><td>' . esc_html( WP_MEMORY_LIMIT ) . '</td></tr>';
		echo '<tr title="WP_MAX_MEMORY_LIMIT"><td>' . esc_html__( 'WP maximum memory limit', 'backwpup' ) . '</td><td>' . esc_html( WP_MAX_MEMORY_LIMIT ) . '</td></tr>';
		echo '<tr title=""><td>' . esc_html__( 'Memory in use', 'backwpup' ) . '</td><td>' . esc_html( size_format( $memory_usage, 2 ) ) . '</td></tr>';
		//disabled PHP functions
		$disabled = ini_get( 'disable_functions' );
		if ( ! empty( $disabled ) ) {
			$disabledarry = explode( ',', $disabled );
			echo '<tr title=""><td>' . esc_html__( 'Disabled PHP Functions:', 'backwpup' ) . '</td><td>';
			echo esc_html( implode( ', ', $disabledarry ) );
			echo '</td></tr>';
		}
		//Loaded PHP Extensions
		echo '<tr title=""><td>' . esc_html__( 'Loaded PHP Extensions:', 'backwpup' ) . '</td><td>';
		$extensions = get_loaded_extensions();
		sort( $extensions );
		echo esc_html( implode( ', ', $extensions ) );
		echo '</td></tr>';
		echo '</table>';

		$output = ob_get_contents();

		ob_end_clean();

		return array( 'success' => 1, 'response' => $output );
	}

	/**
	 * @return array
	 */
	protected function delete_log() {
		// @TODO: Nonce validation check
		if ( ! isset( $_POST['settings']['logfile'] ) || ! is_array( $_POST['settings']['logfile'] ) ) { // @codingStandardsIgnoreLine
			return array( 'error' => __( 'Missing logfile.', $this->plugin_translate ) );
		}

		$dir = get_site_option( 'backwpup_cfg_logfolder' );
		// @TODO: SANITIZE
		foreach ( $_POST['settings']['logfile'] as $logfile ) { // @codingStandardsIgnoreLine
			$logfile = basename( $logfile );

			if ( ! is_writeable( $dir ) ) {
				return array( 'error' => __( 'Directory not writable:', $this->plugin_translate ) . $dir );
			}
			if ( ! is_file( $dir . $logfile ) ) {
				return array( 'error' => __( 'Not file:', $this->plugin_translate ) . $dir . $logfile );
			}

			unlink( $dir . $logfile );

		}

		return array( 'success' => 1 );
	}

	/**
	 * @return array
	 */
	protected function delete_job() {
		// @TODO: Nonce validation check
		if ( ! isset( $_POST['job_id'] ) ) { // @codingStandardsIgnoreLine
			return array( 'error' => __( 'Missing job_id.', $this->plugin_translate ) );
		}

		$job_id = (int) $_POST['job_id'];

		wp_clear_scheduled_hook( 'backwpup_cron', array( 'id' => $job_id ) );
		if ( ! BackWPup_Option::delete_job( $job_id ) ) {
			return array( 'error' => __( 'Cannot delete job', $this->plugin_translate ) );
		}

		return array( 'success' => 1 );
	}

	/**
	 * @return array
	 */
	protected function delete_backup() {
		// @TODO: Nonce validation check
		if ( ! isset( $_POST['settings']['backupfile'] ) ) { // @codingStandardsIgnoreLine
			return array( 'error' => __( 'Missing backupfile.', $this->plugin_translate ) );
		}

		if ( ! isset( $_POST['settings']['dest'] ) ) { // @codingStandardsIgnoreLine
			return array( 'error' => __( 'Missing dest.', $this->plugin_translate ) );
		}

		// @TODO: SANITIZE
		$backupfile = $_POST['settings']['backupfile']; // @codingStandardsIgnoreLine
		$dest = $_POST['settings']['dest']; // @codingStandardsIgnoreLine

		list( $dest_id, $dest_name ) = explode( '_', $dest );

		$dest_class = BackWPup::get_destination( $dest_name );

		if ( is_null( $dest_class ) ) {
			return array( 'error' => __( 'Invalid dest class.', $this->plugin_translate ) );
		}

		$files = $dest_class->file_get_list( $dest );

		foreach ( $files as $file ) {
			if ( is_array( $file ) && $file['file'] === $backupfile ) {
				$dest_class->file_delete( $dest, $backupfile );
				return array( 'success' => 1, 'response' => 'DELETED' );
			}
		}

		return array( 'success' => 1, 'response' => 'Not found' );
	}

	/**
	 * @return array
	 */
	protected function view_log() {
		// @TODO: Nonce validation check
		if ( ! isset( $_POST['settings']['logfile'] ) ) { // @codingStandardsIgnoreLine
			return array( 'error' => __( 'Missing logfile.', $this->plugin_translate ) );
		}

		// @TODO: SANITIZE
		$log_file = get_site_option( 'backwpup_cfg_logfolder' ) . basename( $_POST['settings']['logfile'] ); // @codingStandardsIgnoreLine

		if ( ! is_readable( $log_file ) && ! is_readable( $log_file . '.gz' ) && ! is_readable( $log_file . '.bz2' ) ) {
			$output = __( 'Log file doesn\'t exists', $this->plugin_translate );
		} else {
			if ( ! file_exists( $log_file ) && file_exists( $log_file . '.gz' ) ) {
				$log_file = $log_file . '.gz';
			}

			if ( ! file_exists( $log_file ) && file_exists( $log_file . '.bz2' ) ) {
				$log_file = $log_file . '.bz2';
			}

			if ( '.gz' === substr( $log_file, -3 ) ) {
				$output = file_get_contents( 'compress.zlib://' .$log_file, false );
			} else {
				$output = file_get_contents( $log_file, false );
			}
		}

		return array( 'success' => 1, 'response' => $output );
	}

	/**
	 * @return array
	 */
	protected function tables() {
		// @TODO: Nonce validation check
		if ( ! isset( $_POST['settings']['type'] ) ) { // @codingStandardsIgnoreLine
			return array( 'error' => __( 'Missing type.', $this->plugin_translate ) );
		}

		if ( ! isset( $_POST['settings']['website_id'] ) ) { // @codingStandardsIgnoreLine
			return array( 'error' => __( 'Missing website id.', $this->plugin_translate ) );
		}

		// @TODO: SANITIZE
		$type       = $_POST['settings']['type']; // @codingStandardsIgnoreLine
		$website_id = $_POST['settings']['website_id']; // @codingStandardsIgnoreLine

		$this->wp_list_table_dependency();

		$array = array();

		switch ( $type ) {
			case 'logs':
				if ( ! is_dir( get_site_option( 'backwpup_cfg_logfolder' ) ) ) {
					return array( 'success' => 1, 'response' => $array );
				}
				update_user_option( get_current_user_id(), 'backwpuplogs_per_page', 99999999 );
				$output = new BackWPup_Page_Logs();
				$output->prepare_items();
				break;

			case 'backups':
				update_user_option( get_current_user_id(), 'backwpupbackups_per_page', 99999999 );
				$output = new BackWPup_Page_Backups();
				$output->items = array();

				$jobids = BackWPup_Option::get_job_ids();

				if ( ! empty( $jobids ) ) {
					foreach ( $jobids as $jobid ) {
						if ( 'sync' === BackWPup_Option::get( $jobid, 'backuptype' ) ) {
							continue;
						}

						$dests = BackWPup_Option::get( $jobid, 'destinations' );
						foreach ( $dests as $dest ) {
							$dest_class = BackWPup::get_destination( $dest );
							$items = $dest_class->file_get_list( $jobid . '_' . $dest );
							if ( ! empty( $items ) ) {
								foreach ( $items as $item ) {
									$temp_single_item = $item;
									$temp_single_item['dest'] = $jobid. '_' . $dest;
									$output->items[] = $temp_single_item;
								}
							}
						}
					}
				}

				break;

			case 'jobs':
				$output = new BackWPup_Page_Jobs();
				$output->prepare_items();
				break;
		}

		if ( isset( $output ) && is_array( $output->items ) ) {
			if ( 'jobs' === $type ) {
				foreach ( $output->items as $key => $val ) {
					$temp_array                 = array();
					$temp_array['id']           = $val;
					$temp_array['name']         = BackWPup_Option::get( $val, 'name' );
					$temp_array['type']         = BackWPup_Option::get( $val, 'type' );
					$temp_array['destinations'] = BackWPup_Option::get( $val, 'destinations' );

					if ( $this->is_backwpup_pro ) {
						$temp_array['export'] = str_replace( '&amp;', '&', wp_nonce_url( network_admin_url( 'admin.php' ) . '?page=backwpupjobs&action=export&jobs[]=' . $val, 'bulk-jobs' ) );
					}

					if ( 'wpcron' === BackWPup_Option::get( $val, 'activetype' ) ) {
						if ( $nextrun = wp_next_scheduled( 'backwpup_cron', array( 'id' => $val ) ) + ( get_option( 'gmt_offset' ) * 3600 )  ) {
							$temp_array['nextrun'] = sprintf( __( '%1$s at %2$s by WP-Cron', 'backwpup' ) , date_i18n( get_option( 'date_format' ), $nextrun, true ) , date_i18n( get_option( 'time_format' ), $nextrun, true ) );
						} else { 							$temp_array['nextrun'] = __( 'Not scheduled!', 'backwpup' ); }
					} else {
						$temp_array['nextrun'] = __( 'Inactive', 'backwpup' );
					}

					if ( BackWPup_Option::get( $val, 'lastrun' ) ) {
						$lastrun = BackWPup_Option::get( $val, 'lastrun' );
						$temp_array['lastrun'] = sprintf( __( '%1$s at %2$s', 'backwpup' ), date_i18n( get_option( 'date_format' ), $lastrun, true ), date_i18n( get_option( 'time_format' ), $lastrun, true ) );
						if ( BackWPup_Option::get( $val, 'lastruntime' ) ) {
							$temp_array['lastrun'] .= ' ' . sprintf( __( 'Runtime: %d seconds', 'backwpup' ), BackWPup_Option::get( $val, 'lastruntime' ) );
						}
					} else {
						$temp_array['lastrun'] = __( 'not yet', 'backwpup' );
					}

					$temp_array['website_id'] = $website_id;
					$array[]                  = $temp_array;
				}
			} else if ( 'backups' === $type ) {
				$without_dupes = array();
				foreach ( $output->items as $key ) {
					$temp_array                = $key;
					$temp_array['downloadurl'] = str_replace( array( '&amp;', network_admin_url( 'admin.php' ).'?page=backwpupbackups&action=' ), array( '&', admin_url( 'admin-ajax.php' ).'?action=mainwp_backwpup_download_backup&type=' ), $temp_array['downloadurl'].'&_wpnonce='.$this->create_nonce_without_session( 'mainwp_download_backup' ) );
					$temp_array['website_id']  = $website_id;

					if ( ! isset($without_dupes[ $temp_array['file'] ]) ) {
						$array[]                   = $temp_array;
						$without_dupes[ $temp_array['file'] ] = 1;
					}
				}
			} else {
				foreach ( $output->items as $key => $val ) {
					$array[] = $val;
				}
			}
		}

		return array( 'success' => 1, 'response' => $array );
	}

	public function download_backup() {
		if ( ! isset( $_GET['type'] ) || empty( $_GET['type'] ) || ! isset( $_GET['_wpnonce'] ) || empty( $_GET['_wpnonce'] ) ) {
			die( '-1' );
		}

		if ( ! current_user_can( 'backwpup_backups_download' ) ) {
			die( '-2' );
		}

		if ( ! $this->verify_nonce_without_session( $_GET['_wpnonce'], 'mainwp_download_backup' ) ) {
			die( '-3' );
		}

		$dest = strtoupper( str_replace( 'download', '', $_GET['type'] ) );
		if ( ! empty( $dest ) && strstr( $_GET['type'], 'download' ) ) {
			$dest_class = BackWPup::get_destination( $dest );
			if ( is_null( $dest_class ) ) {
				die('-4');
			}

			$dest_class->file_download( (int) $_GET['jobid'], $_GET['file'] );
		} else {
			die('-5');
		}

		die();
	}

	/**
	 * @param int $action
	 *
	 * @return string
	 */
	protected function create_nonce_without_session( $action = -1 ) {
		$user = wp_get_current_user();
		$uid  = (int) $user->ID;
		if ( ! $uid ) {
			$uid = apply_filters( 'nonce_user_logged_out', $uid, $action );
		}

		$i = wp_nonce_tick();

		return substr( wp_hash( $i . '|' . $action . '|' . $uid, 'nonce' ), -12, 10 );
	}

	/**
	 * @param $nonce
	 * @param int $action
	 *
	 * @return bool|int
	 */
	protected function verify_nonce_without_session( $nonce, $action = -1 ) {
		$nonce = (string) $nonce;
		$user = wp_get_current_user();
		$uid  = (int) $user->ID;
		if ( ! $uid ) {
			$uid = apply_filters( 'nonce_user_logged_out', $uid, $action );
		}

		if ( empty( $nonce ) ) {
			return false;
		}

		$i = wp_nonce_tick();

		$expected = substr( wp_hash( $i . '|' . $action . '|' . $uid, 'nonce' ), -12, 10 );
		if ( hash_equals( $expected, $nonce ) ) {
			return 1;
		}

		$expected = substr( wp_hash( ( $i - 1 ) . '|' . $action . '|' . $uid, 'nonce' ), -12, 10 );
		if ( hash_equals( $expected, $nonce ) ) {
			return 2;
		}

		return false;
	}

	/**
	 * @return array
	 */
	protected function ajax_working() {
		// @TODO: Nonce validation check
		if ( ! isset( $_POST['settings'] ) || ! is_array( $_POST['settings'] ) || ! isset( $_POST['settings']['logfile'] ) || ! isset( $_POST['settings']['logpos'] ) ) { // @codingStandardsIgnoreLine
			return array( 'error' => __( 'Missing logfile or logpos.', $this->plugin_translate ) );
		}

		// @TODO: SANITIZE
		$_GET['logfile']      = $_POST['settings']['logfile']; // @codingStandardsIgnoreLine
		$_GET['logpos']       = $_POST['settings']['logpos']; // @codingStandardsIgnoreLine
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'backwpupworking_ajax_nonce' );

		$this->wp_list_table_dependency();

		// @TODO: Move this out of the ajax_working function
		/**
		 * @param $message
		 *
		 * @return string
		 */
		function mainwp_backwpup_wp_die_ajax_handler( $message ) {
			return 'mainwp_backwpup_wp_die_ajax_handler';
		}

		// We do this in order to not die when using wp_die
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		add_filter( 'wp_die_ajax_handler', 'mainwp_backwpup_wp_die_ajax_handler' );
		remove_filter( 'wp_die_ajax_handler', '_ajax_wp_die_handler' );

		ob_start();
		BackWPup_Page_Jobs::ajax_working();

		$output = ob_get_contents();

		ob_end_clean();

		return array( 'success' => 1, 'response' => $output );
	}

	/**
	 * @return array
	 */
	protected function backup_now() {
		// @TODO: Nonce validation check
		if ( ! isset( $_POST['settings']['job_id'] ) ) { // @codingStandardsIgnoreLine
			return array( 'error' => __( 'Missing job id', $this->plugin_translate ) );
		}

		// Simulate http://wp/wp-admin/admin.php?jobid=1&page=backwpupjobs&action=runnow
		// @TODO: SANITIZE
		$_GET['jobid'] = $_POST['settings']['job_id']; // @codingStandardsIgnoreLine

		$_REQUEST['action']   = 'runnow';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'backwpup_job_run-runnowlink' );

		update_site_option( 'backwpup_messages', array() );

		$this->wp_list_table_dependency();

		ob_start();
		BackWPup_Page_Jobs::load();
		ob_end_clean();

		$output = $this->check_backwpup_messages();

		if ( isset( $output['error'] ) ) {
			return array( 'error' => 'BackWPup_Page_Jobs::load fail: ' . $output['error'] );
		} else {
			$job_object = BackWPup_Job::get_working_data();
			if ( is_object( $job_object ) ) {
				return array(
					'success'  => 1,
					'response' => $output['message'],
					'logfile'  => basename( $job_object->logfile ),
				);
			} else {
				return array( 'success' => 1, 'response' => $output['message'] );
			}
		}
	}

	/**
	 * @return array
	 */
	protected function backup_abort() {
		// @TODO: SANITIZE & Nonce
		$_REQUEST['action']   = 'abort';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'abort-job' );

		update_site_option( 'backwpup_messages', array() );

		$this->wp_list_table_dependency();

		ob_start();
		BackWPup_Page_Jobs::load();
		ob_end_clean();

		$output = $this->check_backwpup_messages();

		if ( isset( $output['error'] ) ) {
			return array( 'error' => 'Cannot abort: ' . $output['error'] );
		} else {
			return array( 'success' => 1, 'response' => $output['message'] );
		}
	}

	protected function wp_list_table_dependency() {
		if ( ! function_exists( 'convert_to_screen' ) ) {
			// We need this because BackWPup_Page_Jobs extends WP_List_Table which uses convert_to_screen
			/**
			 * @param $hook_name
			 *
			 * @return Main_WP_Fake_WP_Screen
			 */
			function convert_to_screen( $hook_name ) {
				return new Main_WP_Fake_WP_Screen();
			}
		}

		if ( ! function_exists( 'add_screen_option' ) ) {
			function add_screen_option( $option, $args = array() ) {

			}
		}

		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
		}
	}

	/**
	 * @return array
	 */
	protected function wizard_system_scan() {
		if ( class_exists( 'BackWPup_Pro_Wizard_SystemTest' ) ) {
			ob_start();

			// @TODO: Check if class exists (they might be using the free version)
			$system_test = new BackWPup_Pro_Wizard_SystemTest();
			$system_test->execute( null );

			$output = ob_get_contents();

			ob_end_clean();

			return array( 'success' => 1, 'response' => $output );
		} else {
			return array( 'error' => 'Missing BackWPup_Pro_Wizard_SystemTest' );
		}
	}

	/**
	 * @return array
	 */
	protected function destination_email_check_email() {
		// TODO: SANITIZE and Nonce
		$settings = $_POST['settings']; // @codingStandardsIgnoreLine

		$message = '';

		$emailmethod   = ( isset( $settings['emailmethod'] ) ? $settings['emailmethod'] : '' );
		$emailsendmail = ( isset( $settings['emailsendmail'] ) ? $settings['emailsendmail'] : '' );
		$emailhost     = ( isset( $settings['emailhost'] ) ? $settings['emailhost'] : '' );
		$emailhostport = ( isset( $settings['emailhostport'] ) ? $settings['emailhostport'] : '' );
		$emailsecure   = ( isset( $settings['emailsecure'] ) ? $settings['emailsecure'] : '' );
		$emailuser     = ( isset( $settings['emailuser'] ) ? $settings['emailuser'] : '' );
		$emailpass     = ( isset( $settings['emailpass'] ) ? $settings['emailpass'] : '' );

		if ( ! isset( $settings['emailaddress'] ) || strlen( $settings['emailaddress'] ) < 2 ) {
			$message = __( 'Missing email address.', 'backwpup' );
		} else {

			// From BackWPup_Destination_Email::edit_ajax
			if ( $emailmethod ) {
				//do so if i'm the wp_mail to get the settings
				global $phpmailer;
				// (Re)create it, if it's gone missing
				if ( ! is_object( $phpmailer ) || ! $phpmailer instanceof PHPMailer ) {
					require_once ABSPATH . WPINC . '/class-phpmailer.php';
					require_once ABSPATH . WPINC . '/class-smtp.php';
					// @TODO: We should never ovverride a WordPress Global, find another way
					$phpmailer = new PHPMailer( true ); // @codingStandardsIgnoreLine
				}
				//only if PHPMailer really used
				if ( is_object( $phpmailer ) ) {
					do_action_ref_array( 'phpmailer_init', array( &$phpmailer ) );
					//get settings from PHPMailer
					$emailmethod   = $phpmailer->Mailer;
					$emailsendmail = $phpmailer->Sendmail;
					$emailhost     = $phpmailer->Host;
					$emailhostport = $phpmailer->Port;
					$emailsecure   = $phpmailer->SMTPSecure;
					$emailuser     = $phpmailer->Username;
					$emailpass     = $phpmailer->Password;
				}
			}

			//Generate mail with Swift Mailer
			if ( ! class_exists( 'Swift', false ) ) {
				require BackWPup::get_plugin_data( 'plugindir' ) . '/vendor/SwiftMailer/swift_required.php';
			}

			if ( function_exists( 'mb_internal_encoding' ) && ( (int) ini_get( 'mbstring.func_overload' ) ) & 2 ) {
				$mbEncoding = mb_internal_encoding();
				mb_internal_encoding( 'ASCII' );
			}

			try {
				// Create the Transport
				if ( 'smtp' === $emailmethod ) {
					$transport = Swift_SmtpTransport::newInstance( $emailhost, $emailhostport );
					$transport->setUsername( $emailuser );
					$transport->setPassword( $emailpass );
					if ( 'ssl' === $emailsecure ) {
						$transport->setEncryption( 'ssl' );
					}
					if ( 'tls' === $emailsecure ) {
						$transport->setEncryption( 'tls' );
					}
				} elseif ( 'sendmail' === $emailmethod ) {
					$transport = Swift_SendmailTransport::newInstance( $emailsendmail );
				} else {
					$transport = Swift_MailTransport::newInstance();
				}
				// Create the Mailer using your created Transport
				$emailer = Swift_Mailer::newInstance( $transport );

				// Create a message
				$message = Swift_Message::newInstance( __( 'BackWPup archive sending TEST Message', 'backwpup' ) );
				$message->setFrom( array( ( isset( $settings['emailsndemail'] ) ? $settings['emailsndemail'] : 'from@example.com' ) => isset( $settings['emailsndemailname'] ) ? $settings['emailsndemailname'] : '' ) );
				$message->setTo( array( $settings['emailaddress'] ) );
				$message->setBody( __( 'If this message reaches your inbox, sending backup archives via email should work for you.', 'backwpup' ) );
				// Send the message
				$result = $emailer->send( $message );
			} catch ( Exception $e ) {
				$message = 'Swift Mailer: ' . $e->getMessage();
			}

			if ( isset( $mbEncoding ) ) {
				mb_internal_encoding( $mbEncoding );
			}

			if ( ! isset( $result ) || ! $result ) {
				$message = __( 'Error while sending email!', 'backwpup' );
			} else {
				$message = __( 'Email sent.', 'backwpup' );
			}
		}

		return array( 'success' => 1, 'message' => $message );
	}

	/**
	 * @return array
	 */
	protected function get_job_files() {
		// From BackWPup_JobType_File::get_exclude_dirs
		/**
		 * @param $folder
		 *
		 * @return array
		 */
		function mainwp_backwpup_get_exclude_dirs( $folder ) {
			$folder            = trailingslashit( str_replace( '\\', '/', realpath( $folder ) ) );
			$exclude_dir_array = array();

			// @TODO: Refactor so the conditionals aren't quite so long
			if ( false !== strpos( trailingslashit( str_replace( '\\', '/', realpath( ABSPATH ) ) ), $folder ) && trailingslashit( str_replace( '\\', '/', realpath( ABSPATH ) ) ) !== $folder ) {
				$exclude_dir_array[] = trailingslashit( str_replace( '\\', '/', realpath( ABSPATH ) ) );
			}
			if ( false !== strpos( trailingslashit( str_replace( '\\', '/', realpath( WP_CONTENT_DIR ) ) ), $folder ) && trailingslashit( str_replace( '\\', '/', realpath( WP_CONTENT_DIR ) ) ) !== $folder ) {
				$exclude_dir_array[] = trailingslashit( str_replace( '\\', '/', realpath( WP_CONTENT_DIR ) ) );
			}
			if ( false !== strpos( trailingslashit( str_replace( '\\', '/', realpath( WP_PLUGIN_DIR ) ) ), $folder ) && trailingslashit( str_replace( '\\', '/', realpath( WP_PLUGIN_DIR ) ) ) !== $folder ) {
				$exclude_dir_array[] = trailingslashit( str_replace( '\\', '/', realpath( WP_PLUGIN_DIR ) ) );
			}
			if ( false !== strpos( trailingslashit( str_replace( '\\', '/', realpath( get_theme_root() ) ) ), $folder ) && trailingslashit( str_replace( '\\', '/', realpath( get_theme_root() ) ) ) !== $folder ) {
				$exclude_dir_array[] = trailingslashit( str_replace( '\\', '/', realpath( get_theme_root() ) ) );
			}
			if ( false !== strpos( trailingslashit( str_replace( '\\', '/', realpath( BackWPup_File::get_upload_dir() ) ) ), $folder ) && trailingslashit( str_replace( '\\', '/', realpath( BackWPup_File::get_upload_dir() ) ) ) !== $folder ) {
				$exclude_dir_array[] = trailingslashit( str_replace( '\\', '/', realpath( BackWPup_File::get_upload_dir() ) ) );
			}

			return array_unique( $exclude_dir_array );
		}

		$return = array();

		$folders = array(
			'abs'     => ABSPATH,
			'content' => WP_CONTENT_DIR,
			'plugin'  => WP_PLUGIN_DIR,
			'theme'   => get_theme_root(),
			'upload'  => BackWPup_File::get_upload_dir(),
		);

		foreach ( $folders as $key => $folder ) {
			$return_temp      = array();
			$main_folder_name = realpath( $folder );

			if ( $main_folder_name ) {
				$main_folder_name = untrailingslashit( str_replace( '\\', '/', $main_folder_name ) );
				$main_folder_size = '(' . size_format( BackWPup_File::get_folder_size( $main_folder_name, false ), 2 ) . ')';

				if ( $dir = @opendir( $main_folder_name ) ) { // @codingStandardsIgnoreLine
					while ( ( $file = readdir( $dir ) ) !== false ) {
						if ( ! in_array( $file, array(
								'.',
								'..',
							) ) && is_dir( $main_folder_name . '/' . $file ) && ! in_array( trailingslashit( $main_folder_name . '/' . $file ), mainwp_backwpup_get_exclude_dirs( $main_folder_name ) )
						) {
							$folder_size = ' (' . size_format( BackWPup_File::get_folder_size( $main_folder_name . '/' . $file ), 2 ) . ')';

							$return_temp[] = array(
								'size' => $folder_size,
								'name' => $file,
							);

						}
					}

					@closedir( $dir ); // @codingStandardsIgnoreLine
				}

				$return[ $key ] = array( 'size' => $main_folder_size, 'name' => $folder, 'folders' => $return_temp );
			}
		}

		return array( 'success' => 1, 'folders' => $return );
	}

	/**
	 * @return array
	 */
	protected function get_child_tables() {
		global $wpdb;

		$return = array();

		// TODO: SANITIZE and Nonce
		$settings = $_POST['settings']; // @codingStandardsIgnoreLine

		if ( ! empty( $settings['dbhost'] ) && ! empty( $settings['dbuser'] ) ) {
			$mysqli = @new mysqli( $settings['dbhost'], $settings['dbuser'], ( isset( $settings['dbpassword'] ) ? $settings['dbpassword'] : '' ) ); // @codingStandardsIgnoreLine

			if ( $mysqli->connect_error ) {
				$return['message'] = $mysqli->connect_error;
			} else {
				if ( ! empty( $settings['dbname'] ) ) {
					if ( $res = $mysqli->query( 'SHOW FULL TABLES FROM `' . $mysqli->real_escape_string( $settings['dbname'] ) . '`' ) ) {
						$tables_temp = array();
						while ( $table = $res->fetch_array( MYSQLI_NUM ) ) {
							$tables_temp[] = $table[0];
						}

						$res->close();
						$return['tables'] = $tables_temp;
					}
				}

				if ( empty( $settings['dbname'] ) || ! empty( $settings['first'] ) ) {
					if ( $res = $mysqli->query( 'SHOW DATABASES' ) ) {
						$databases_temp = array();
						while ( $db = $res->fetch_array() ) {
							$databases_temp[] = $db['Database'];
						}

						$res->close();
						$return['databases'] = $databases_temp;
					}
				}
			}
			$mysqli->close();
		} else {
			$tables_temp = array();

			$tables = $wpdb->get_results( 'SHOW FULL TABLES FROM `' . DB_NAME . '`', ARRAY_N );
			foreach ( $tables as $table ) {
				$tables_temp[] = $table[0];
			}

			$return['tables'] = $tables_temp;
		}

		return array( 'success' => 1, 'return' => $return );
	}

	/**
	 * @return array
	 */
	protected function insert_or_update_jobs_global() {
		// @TODO SANITIZE and Nonce
		$settings = $_POST['settings']; // @codingStandardsIgnoreLine

		if ( ! is_array( $settings ) ) {
			return array( 'error' => __( 'Missing array settings', $this->plugin_translate ) );
		}

		if ( ! isset( $settings['job_id'] ) ) {
			return array( 'error' => __( 'Missing job id', $this->plugin_translate ) );
		}

		if ( $settings['job_id'] > 0 ) {
			$new_job_id = intval( $settings['job_id'] );
		} else {
			$new_job_id = null;
		}

		$changes_array = array();
		$message_array = array();

		foreach ( $settings['value'] as $key => $val ) {
			$temp_array          = array();
			$temp_array['tab']   = $key;
			$temp_array['value'] = $val;
			if ( ! is_null( $new_job_id ) ) {
				$temp_array['job_id'] = $new_job_id;
			} else {
				$temp_array['job_id'] = $settings['job_id'];
			}

			$_POST['settings'] = $temp_array;
			$return            = $this->insert_or_update_jobs();

			if ( is_null( $new_job_id ) ) {
				if ( ! isset( $return['job_id'] ) ) {
					return array( 'error' => __( 'Missing new job_id', $this->plugin_translate ) );
				}

				$new_job_id = $return['job_id'];
			}

			// We want to exit gracefully
			if ( isset( $return['error_message'] ) ) {
				$message_array[ $return['error_message'] ] = 1;
			}

			if ( isset( $return['changes'] ) ) {
				$changes_array = array_merge( $changes_array, $return['changes'] );
			}

			if ( isset( $return['message'] ) ) {
				// Some kind of array_unique
				foreach ( $return['message'] as $message ) {
					if ( ! isset( $message_array[ $message ] ) ) {
						$message_array[ $message ] = 1;
					}
				}
			}
		}

		return array(
			'success' => 1,
			'job_id'  => $new_job_id,
			'changes' => $changes_array,
			'message' => array_keys( $message_array ),
		);
	}

	/**
	 * @return array
	 */
	protected function insert_or_update_jobs() {
		// @TODO: SANITIZE and Nonce
		$settings = $_POST['settings']; // @codingStandardsIgnoreLine

		if ( ! is_array( $settings ) || ! isset( $settings['value'] ) ) {
			return array( 'error' => __( 'Missing array settings', $this->plugin_translate ) );
		}

		if ( ! isset( $settings['tab'] ) ) {
			return array( 'error' => __( 'Missing tab', $this->plugin_translate ) );
		}

		if ( ! isset( $settings['job_id'] ) ) {
			return array( 'error' => __( 'Missing job id', $this->plugin_translate ) );
		}

		if ( ! class_exists( 'BackWPup' ) ) {
			return array( 'error' => __( 'Install BackWPup on child website', $this->plugin_translate ) );
		}

		if ( $settings['job_id'] > 0 ) {
			$job_id = intval( $settings['job_id'] );
		} else {
			//generate jobid if not exists
			$newjobid = BackWPup_Option::get_job_ids();
			sort( $newjobid );
			$job_id = end( $newjobid ) + 1;
		}

		update_site_option( 'backwpup_messages', array() );

		foreach ( $settings['value'] as $key => $val ) {
			$_POST[ $key ] = $val;
		}

		BackWPup_Page_Editjob::save_post_form( $settings['tab'], $job_id );

		$return = $this->check_backwpup_messages();

		if ( isset( $return['error'] ) ) {
			return array(
				'success'       => 1,
				'error_message' => __( 'Cannot save jobs: ' . $return['error'], $this->plugin_translate ),
			);
		}

		if ( isset( $settings['value']['sugarrefreshtoken'] ) ) {
			BackWPup_Option::update( $job_id, 'sugarrefreshtoken', $settings['value']['sugarrefreshtoken'] );
		}

		if ( isset( $settings['value']['gdriverefreshtoken'] ) ) {
			BackWPup_Option::update( $job_id, 'gdriverefreshtoken', $settings['value']['gdriverefreshtoken'] );
		}

		if ( isset( $settings['value']['dbdumpspecialsetalltables'] ) && $settings['value']['dbdumpspecialsetalltables'] ) {
			BackWPup_Option::update( $job_id, 'dbdumpexclude', array() );
		}

		if ( isset( $settings['value']['dropboxtoken']) && isset($settings['value']['dropboxroot']) ) {
			BackWPup_Option::update( $job_id, 'dropboxtoken', $settings['value']['dropboxtoken'] );
			BackWPup_Option::update( $job_id, 'dropboxroot', $settings['value']['dropboxroot'] );
		}

		$changes_array = array();

		foreach ( $settings['value'] as $key => $val ) {
			$temp_value = BackWPup_Option::get( $job_id, $key );
			if ( is_string( $temp_value ) ) {
				if ( isset( $this->exclusions[ $settings['tab'] ] ) ) {
					if ( ! in_array( $key, $this->exclusions[ $settings['tab'] ] ) && 0 !== strcmp( $temp_value, $val ) ) {
						$changes_array[ $key ] = $temp_value;
					}
				} else if ( 0 !== strcmp( $temp_value, $val ) ) {
					$changes_array[ $key ] = $temp_value;
				}
			}
		}

		return array(
			'success' => 1,
			'job_id'  => $job_id,
			'changes' => $changes_array,
			'message' => $return['message'],
		);
	}


	/**
	 * @return array
	 */
	protected function update_settings() {
		// @TODO: SANITIZE and Nonce
		$settings = $_POST['settings']; // @codingStandardsIgnoreLine

		if ( ! is_array( $settings ) || ! isset( $settings['value'] ) ) {
			return array( 'error' => __( 'Missing array settings', $this->plugin_translate ) );
		}

		if ( ! class_exists( 'BackWPup' ) ) {
			return array( 'error' => __( 'Install BackWPup on child website', $this->plugin_translate ) );
		}

		if ( isset($settings['value']['is_premium']) && 1 === $settings['value']['is_premium'] && false === $this->is_backwpup_pro ) {
			return array( 'error' => __( 'You try to use pro version settings in non pro plugin version. Please install pro version on child and try again.', $this->plugin_translate ) );
		}

		foreach ( $settings['value'] as $key => $val ) {
			$_POST[ $key ] = $val;
		}

		update_site_option( 'backwpup_messages', array() );

		$backwpup = new BackWPup_Page_Settings();
		$backwpup->save_post_form();

		if ( class_exists( 'BackWPup_Pro' ) ) {
			$pro_settings = BackWPup_Pro_Settings_APIKeys::get_instance();
			$pro_settings->save_form();
		}

		$return = $this->check_backwpup_messages();

		if ( isset( $return['error'] ) ) {
			return array( 'error' => __( 'Cannot save settings: ' . $return['error'], $this->plugin_translate ) );
		}

		$exclusions = array(
			'is_premium',
			'dropboxappsecret',
			'dropboxsandboxappsecret',
			'sugarsyncsecret',
			'googleclientsecret',
			'override',
			'httpauthpassword',
		);

		$changes_array = array();

		foreach ( $settings['value'] as $key => $val ) {

			$temp_value = get_site_option( 'backwpup_cfg_' . $key, '' );
			if ( ! in_array( $key, $exclusions ) && 0 !== strcmp( $temp_value, $val ) ) {
				$changes_array[ $key ] = $temp_value;
			}
		}

		return array( 'success' => 1, 'changes' => $changes_array, 'message' => $return['message'] );
	}

	/**
	 * @return array
	 */
	protected function check_backwpup_messages() {
		$message = get_site_option( 'backwpup_messages', array() );
		update_site_option( 'backwpup_messages', array() );

		if ( isset( $message['error'] ) ) {
			return array( 'error' => implode( ', ', $message['error'] ) );
		} else if ( isset( $message['updated'] ) ) {
			return array( 'message' => $message['updated'] );
		} else {
			return array( 'error' => 'Generic error' );
		}

	}
}

if ( ! class_exists( 'Main_WP_Fake_WP_Screen' ) ) {
	/**
	 * Class Main_WP_Fake_WP_Screen
	 */
	class Main_WP_Fake_WP_Screen {
		public $action;
		public $base;
		public $id;
	}
}
