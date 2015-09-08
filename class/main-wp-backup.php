<?php
/**
 * Class Main_WP_Backup
 */
class Main_WP_Backup {
	protected static $instance = null;
	protected $exclude_zip;
	protected $zip_archive_file_count;
	protected $zip_archive_size_count;
	protected $zip_archive_file_name;
	protected $file_descriptors;
	protected $load_files_before_zip;

	protected $timeout;
	protected $last_run;

	/**
	 * @var ZipArchive|PclZip
	 */
	protected $zip;

	/**
	 * @var Tar_Archiver
	 */
	protected $archiver = null;

	// @TODO: Remove
	protected function __construct() {

	}

	/**
	 * @return Main_WP_Backup|null
	 */
	public static function get() {
		if ( null === self::$instance ) {
			self::$instance = new Main_WP_Backup();
		}
		return self::$instance;
	}

	// @TODO: Refactor with one single $args argument as an array, instead of 12 arguments
	/**
	 * Create full backup
	 *
	 * @param $excludes
	 * @param string $file_prefix
	 * @param bool|false $add_config
	 * @param bool|false $include_core_files
	 * @param int $file_descriptors
	 * @param bool|false $file_suffix
	 * @param bool|false $exclude_zip
	 * @param bool|false $exclude_non_wp
	 * @param bool|true $load_files_before_zip
	 * @param string $ext
	 * @param bool|false $pid
	 * @param bool|false $append
	 *
	 * @return array|bool
	 */
	public function create_full_backup( $excludes, $file_prefix = '', $add_config = false, $include_core_files = false, $file_descriptors = 0, $file_suffix = false, $exclude_zip = false, $exclude_non_wp = false, $load_files_before_zip = true, $ext = 'zip', $pid = false, $append = false ) {
		$this->file_descriptors = $file_descriptors;
		$this->load_files_before_zip = $load_files_before_zip;

		$dirs = Main_WP_Helper::getMainWPDir( 'backup' );
		$backup_dir = $dirs[0];
		if ( ! defined( 'PCLZIP_TEMPORARY_DIR' ) ) {
			define( 'PCLZIP_TEMPORARY_DIR', $backup_dir );
		}

		if ( false !== $pid ) {
			$pid = trailingslashit( $backup_dir ) . 'backup-' . $pid . '.pid';
		}

		// Verify if another backup is running, if so, return an error
		$files = glob( $backup_dir . '*.pid' );
		foreach ( $files as $file ) {
			if ( basename( $file ) === basename( $pid ) ) { continue; }

			if ( ( time() - filemtime( $file ) ) < 160 ) {
				Main_WP_Helper::error( 'Another backup process is running, try again later' );
			}
		}

		$timestamp = time();
		if ( '' !== $file_prefix ) {
			$file_prefix .= '-';
		}

		if ( 'zip' === $ext ) {
			$this->archiver = null;
			$ext = '.zip';
		} else {
			$this->archiver = new Tar_Archiver( $this, $ext, $pid );
			$ext = $this->archiver->getExtension();
		}

		// Throw new Exception('Test 1 2 : ' . print_r($append,1));
		if ( ( false !== $file_suffix ) && ! empty( $file_suffix ) ) {
			$file = $file_suffix . ( true === $append ? '' : $ext ); // Append already contains extension!
		} else {
			$file = 'backup-' . $file_prefix . $timestamp . $ext;
		}
		$file_path = $backup_dir . $file;
		$file_url = $file;

		// @TODO: Remove this
		//        if (!$append)
		//        {
		//            if ($dh = opendir($backup_dir))
		//            {
		//                while (($file = readdir($dh)) !== false)
		//                {
		//                    if ($file != '.' && $file != '..' && preg_match('/(.*).(zip|tar|tar.gz|tar.bz2|pid|done)$/', $file))
		//                    {
		//                        @unlink($backup_dir . $file);
		//                    }
		//                }
		//                closedir($dh);
		//            }
		//        }

		if ( ! $add_config ) {
			if ( ! in_array( str_replace( ABSPATH, '', WP_CONTENT_DIR ), $excludes ) && ! in_array( 'wp-admin', $excludes ) && ! in_array( WPINC, $excludes ) ) {
				$add_config = true;
				$include_core_files = true;
			}
		}

		// @TODO Use MINUTES_IN_SECONDS constant ( 20 * MINUTES IN SECONDS )
		// @TODO: Check this. 20 * 60 * 60 to me is 20 hours, not minutes
		$this->timeout = 20 * 60 * 60; /* 20 minutes */
		$mem = '512M';

		/* @codingStandardsIgnoreStart */
		@ini_set( 'memory_limit', $mem );
		@set_time_limit( $this->timeout );
		@ini_set( 'max_execution_time', $this->timeout );
		/* @codingStandardsIgnoreEnd */

		if ( null !== $this->archiver ) {
			$success = $this->archiver->createFullBackup( $file_path, $excludes, $add_config, $include_core_files, $exclude_zip, $exclude_non_wp, $append );
		} else if ( $this->check_zip_support() ) {
			$success = $this->create_zip_full_backup( $file_path, $excludes, $add_config, $include_core_files, $exclude_zip, $exclude_non_wp );
		} else if ( $this->check_zip_console() ) {
			$success = $this->create_zip_console_full_backup( $file_path, $excludes, $add_config, $include_core_files, $exclude_zip, $exclude_non_wp );
		} else {
			$success = $this->create_zip_pcl_full_backup_2( $file_path, $excludes, $add_config, $include_core_files, $exclude_zip, $exclude_non_wp );
		}

		// @TODO: Refactor as an if statement, to make this easier to read.
		return ( $success ) ? array(
			'timestamp' => $timestamp,
			'file' => $file_url,
			'filesize' => filesize( $file_path ),
		) : false;
	}

	/**
	 * @param $file
	 * @param $archive
	 *
	 * @return bool
	 */
	public function zip_file( $file, $archive ) {
		// @TODO Use MINUTES_IN_SECONDS constant ( 20 * MINUTES IN SECONDS )
		// @TODO: Check this. 20 * 60 * 60 to me is 20 hours, not minutes
		$this->timeout = 20 * 60 * 60; /*20 minutes*/
		$mem = '512M';

		/* @codingStandardsIgnoreStart */
		@ini_set( 'memory_limit', $mem );
		@set_time_limit( $this->timeout );
		@ini_set( 'max_execution_time', $this->timeout );
		/* @codingStandardsIgnoreEnd */

		if ( null !== $this->archiver ) {
			$success = $this->archiver->zipFile( $file, $archive );
		} else if ( $this->check_zip_support() ) {
			$success = $this->_zip_file( $file, $archive );
		} else if ( $this->check_zip_console() ) {
			$success = $this->_zip_file_console( $file, $archive );
		} else {
			$success = $this->_zip_file_pcl( $file, $archive );
		}

		return $success;
	}

	/**
	 * @param $file
	 * @param $archive
	 *
	 * @return bool
	 */
	function _zip_file( $file, $archive ) {
		$this->zip = new ZipArchive();
		$this->zip_archive_file_count = 0;
		$this->zip_archive_size_count = 0;

		$zip_res = $this->zip->open( $archive, ZipArchive::CREATE );
		if ( $zip_res ) {
			$this->add_file_to_zip( $file, basename( $file ) );

			return $this->zip->close();
		}

		return false;
	}

	// @TODO: Complete or remove
	/**
	 * @param $file
	 * @param $archive
	 *
	 * @return bool
	 */
	function _zip_file_console( $file, $archive ) {
		return false;
	}

	/**
	 * @param $file
	 * @param $archive
	 *
	 * @return bool
	 */
	public function _zip_file_pcl( $file, $archive ) {
		//Zip this backup folder..
		require_once ( ABSPATH . 'wp-admin/includes/class-pclzip.php');
		$this->zip = new PclZip( $archive );

		$error = false;
		// @TODO: Refactor to remove the $rslt var, it's unneeded
		if ( 0 === ( $rslt = $this->zip->add( $file, PCLZIP_OPT_REMOVE_PATH, dirname( $file ) ) ) ) {
			$error = true;
		}

		return ! $error;
	}

	/**
	 * Check for default PHP zip support
	 *
	 * @return bool
	 */
	public function check_zip_support() {
		return class_exists( 'ZipArchive' );
	}

	/**
	 * Check if we could run zip on console
	 *
	 * @return bool
	 */
	public function check_zip_console() {
		// @TODO: Complete
		return false;
		//        return function_exists('system');
	}

	/**
	 * Create full backup using default PHP zip library
	 *
	 * @param $file_path
	 * @param $excludes
	 * @param $add_config
	 * @param $include_core_files
	 * @param $exclude_zip
	 * @param $exclude_non_wp
	 *
	 * @return bool
	 */
	public function create_zip_full_backup( $file_path, $excludes, $add_config, $include_core_files, $exclude_zip, $exclude_non_wp ) {
		$this->exclude_zip = $exclude_zip;
		$this->zip = new ZipArchive();
		$this->zip_archive_file_count = 0;
		$this->zip_archive_size_count = 0;
		$this->zip_archive_file_name = $file_path;
		$zip_res = $this->zip->open( $file_path, ZipArchive::CREATE );

		if ( $zip_res ) {
			$nodes = glob( ABSPATH . '*' );
			if ( ! $include_core_files ) {
				$core_files = array(
					'favicon.ico',
					'index.php',
					'license.txt',
					'readme.html',
					'wp-activate.php',
					'wp-app.php',
					'wp-blog-header.php',
					'wp-comments-post.php',
					'wp-config.php',
					'wp-config-sample.php',
					'wp-cron.php',
					'wp-links-opml.php',
					'wp-load.php',
					'wp-login.php',
					'wp-mail.php',
					'wp-pass.php',
					'wp-register.php',
					'wp-settings.php',
					'wp-signup.php',
					'wp-trackback.php',
					'xmlrpc.php',
				);
				foreach ( $nodes as $key => $node ) {
					if ( Main_WP_Helper::startsWith( $node, ABSPATH . WPINC ) ) {
						unset( $nodes[ $key ] );
					} else if ( Main_WP_Helper::startsWith( $node, ABSPATH . basename( admin_url( '' ) ) ) ) {
						unset( $nodes[ $key ] );
					} else {
						foreach ( $core_files as $core_file ) {
							if ( ABSPATH . $core_file === $node ) {
								unset( $nodes[ $key ] );
							}
						}
					}
				}
				unset( $core_files );
			}

			$db_backup_file = dirname( $file_path ) . DIRECTORY_SEPARATOR . 'dbBackup.sql';

			$this->create_backup_db( $db_backup_file );
			$this->add_file_to_zip( $db_backup_file, basename( WP_CONTENT_DIR ) . '/dbBackup.sql' );

			if ( file_exists( ABSPATH . '.htaccess' ) ) {
				$this->add_file_to_zip( ABSPATH . '.htaccess', 'mainwp-htaccess' );
			}

			foreach ( $nodes as $node ) {
				if ( $exclude_non_wp && is_dir( $node ) ) {
					if ( ! Main_WP_Helper::startsWith( $node, WP_CONTENT_DIR ) && ! Main_WP_Helper::startsWith( $node,  ABSPATH . 'wp-admin' ) && ! Main_WP_Helper::startsWith( $node, ABSPATH . WPINC ) ) {
						continue;
					}
				}

				if ( ! Main_WP_Helper::inExcludes( $excludes, str_replace( ABSPATH, '', $node ) ) ) {
					if ( is_dir( $node ) ) {
						$this->zip_add_dir( $node, $excludes );
					} else if ( is_file( $node ) ) {
						$this->add_file_to_zip( $node, str_replace( ABSPATH, '', $node ) );
					}
				}
			}

			if ( $add_config ) {
				global $wpdb;
				$plugins = array();
				$dir = WP_CONTENT_DIR . '/plugins/'; // @TODO: Use WP_PLUGIN_DIR

				/* @codingStandardsIgnoreStart */
				$fh = @opendir( $dir );
				while ( $entry = @readdir( $fh ) ) {
					if ( ! @is_dir( $dir . $entry ) ) { continue; }
					if ( ($entry == '.') || ($entry == '..') ) { continue; }
					$plugins[] = $entry;
				}
				@closedir( $fh );
				/* @codingStandardsIgnoreEnd */

				$themes = array();
				$dir = WP_CONTENT_DIR . '/themes/';
				/* @codingStandardsIgnoreStart */
				$fh = @opendir( $dir );
				while ( $entry = @readdir( $fh ) ) {
					if ( ! @is_dir( $dir . $entry ) ) { continue; }
					if ( ($entry == '.') || ($entry == '..') ) { continue; }
					$themes[] = $entry;
				}
				@closedir( $fh );
				/* @codingStandardsIgnoreEnd */

				$string = base64_encode(
					serialize(
						array(
							'siteurl' => get_option( 'siteurl' ),
							'home' => get_option( 'home' ),
							'abspath' => ABSPATH,
							'prefix' => $wpdb->prefix,
							'lang' => defined( 'WPLANG' ) ? WPLANG : '',
							'plugins' => $plugins,
							'themes' => $themes,
						)
					)
				);

				$this->add_file_from_string_to_zip( 'clone/config.txt', $string );
			}

			// @TODO: This variable is unused. Use it or remove it.
			$return = $this->zip->close();

			/* @codingStandardsIgnoreStart */
			@unlink( dirname( $file_path ) . DIRECTORY_SEPARATOR . 'dbBackup.sql' );
			/* @codingStandardsIgnoreEnd */

			return true;
		}

		return false;
	}

	/**
	 * Create full backup using pclZip library
	 *
	 * @param $file_path
	 * @param $excludes
	 * @param $add_config
	 * @param $include_core_files
	 *
	 * @return bool
	 */
	public function create_zip_pcl_full_backup( $file_path, $excludes, $add_config, $include_core_files ) {
		require_once ( ABSPATH . 'wp-admin/includes/class-pclzip.php');

		$this->zip = new PclZip( $file_path );
		$nodes = glob( ABSPATH . '*' );
		if ( ! $include_core_files ) {
			$coreFiles = array(
				'favicon.ico',
				'index.php',
				'license.txt',
				'readme.html',
				'wp-activate.php',
				'wp-app.php',
				'wp-blog-header.php',
				'wp-comments-post.php',
				'wp-config.php',
				'wp-config-sample.php',
				'wp-cron.php',
				'wp-links-opml.php',
				'wp-load.php',
				'wp-login.php',
				'wp-mail.php',
				'wp-pass.php',
				'wp-register.php',
				'wp-settings.php',
				'wp-signup.php',
				'wp-trackback.php',
				'xmlrpc.php',
			);

			foreach ( $nodes as $key => $node ) {
				if ( Main_WP_Helper::startsWith( $node, ABSPATH . WPINC ) ) {
					unset( $nodes[ $key ] );
				} else if ( Main_WP_Helper::startsWith( $node, ABSPATH . basename( admin_url( '' ) ) ) ) {
					unset( $nodes[ $key ] );
				} else {
					foreach ( $coreFiles as $coreFile ) {
						if ( ABSPATH . $coreFile === $node ) {
							unset( $nodes[ $key ] );
						}
					}
				}
			}
			unset( $coreFiles );
		}

		$this->create_backup_db( dirname( $file_path ) . DIRECTORY_SEPARATOR . 'dbBackup.sql' );
		$error = false;
		// @TODO: Refactor to remove the $rslt var, it's unneeded
		if ( 0 === ( $rslt = $this->zip->add( dirname( $file_path ) . DIRECTORY_SEPARATOR . 'dbBackup.sql', PCLZIP_OPT_REMOVE_PATH, dirname( $file_path ), PCLZIP_OPT_ADD_PATH, basename( WP_CONTENT_DIR ) ) ) ) {
			$error = true;
		}

		/* @codingStandardsIgnoreStart */
		@unlink( dirname( $file_path ) . DIRECTORY_SEPARATOR . 'dbBackup.sql' );
		/* @codingStandardsIgnoreEnd */

		if ( ! $error ) {
			foreach ( $nodes as $node ) {
				if ( null !== $excludes || ! in_array( str_replace( ABSPATH, '', $node ), $excludes ) ) {
					if ( is_dir( $node ) ) {
						if ( ! $this->pcl_zip_add_dir( $node, $excludes ) ) {
							$error = true;
							break;
						}
					} else if ( is_file( $node ) ) {
						// @TODO: Refactor to remove the $rslt var, it's unneeded
						if ( 0 === ( $rslt = $this->zip->add( $node, PCLZIP_OPT_REMOVE_PATH, ABSPATH ) ) ) {
							$error = true;
							break;
						}
					}
				}
			}
		}

		if ( $add_config ) {
			global $wpdb;
			$string = base64_encode(
				serialize(
					array(
						'siteurl' => get_option( 'siteurl' ),
						'home' => get_option( 'home' ),
						'abspath' => ABSPATH,
						'prefix' => $wpdb->prefix,
						'lang' => WPLANG,
					)
				)
			);

			$this->add_file_from_string_to_pcl_zip( 'clone/config.txt', $string, $file_path );
		}

		if ( $error ) {
			/* @codingStandardsIgnoreStart */
			@unlink( $file_path );
			/* @codingStandardsIgnoreEnd */
			return false;
		}

		return true;
	}

	/**
	 * @param $nodes
	 * @param $excludes
	 * @param $backup_folder
	 * @param $exclude_non_wp
	 * @param $root
	 */
	function copy_dir( $nodes, $excludes, $backup_folder, $exclude_non_wp, $root ) {
		// @TODO: $root is not used - remove it

		if ( ! is_array( $nodes ) ) {
			return;
		}

		foreach ( $nodes as $node ) {
			if ( $exclude_non_wp && is_dir( $node ) ) {
				if ( ! Main_WP_Helper::startsWith( $node, WP_CONTENT_DIR ) && ! Main_WP_Helper::startsWith( $node,  ABSPATH . 'wp-admin' ) && ! Main_WP_Helper::startsWith( $node, ABSPATH . WPINC ) ) {
					continue;
				}
			}

			if ( ! Main_WP_Helper::inExcludes( $excludes, str_replace( ABSPATH, '', $node ) ) ) {
				if ( is_dir( $node ) ) {
					if ( ! file_exists( str_replace( ABSPATH, $backup_folder, $node ) ) ) {
						/* @codingStandardsIgnoreStart */
					   @mkdir( str_replace( ABSPATH, $backup_folder, $node ) );
						/* @codingStandardsIgnoreEnd */
					}

					$new_nodes = glob( $node . DIRECTORY_SEPARATOR . '*' );
					$this->copy_dir( $new_nodes, $excludes, $backup_folder, $exclude_non_wp, false );
					unset( $new_nodes );
				} else if ( is_file( $node ) ) {
					if ( $this->exclude_zip && Main_WP_Helper::endsWith( $node, '.zip' ) ) { continue; }

					/* @codingStandardsIgnoreStart */
					@copy( $node, str_replace( ABSPATH, $backup_folder, $node ) );
					/* @codingStandardsIgnoreEnd */
				}
			}
		}
	}

	/**
	 * @param $file_path
	 * @param $excludes
	 * @param $add_config
	 * @param $include_core_files
	 * @param $exclude_zip
	 * @param $exclude_non_wp
	 *
	 * @return bool
	 */
	public function create_zip_pcl_full_backup_2( $file_path, $excludes, $add_config, $include_core_files, $exclude_zip, $exclude_non_wp ) {
		// @TODO: $exclude_zip is unused

		global $classDir;
		// @TODO: $classDir is unused

		//Create backup folder
		$backup_folder = dirname( $file_path ) . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR;
		/* @codingStandardsIgnoreStart */
		@mkdir( $backup_folder );
		/* @codingStandardsIgnoreEnd */

		//Create DB backup
		$this->create_backup_db( $backup_folder . 'dbBackup.sql' );

		//Copy installation to backup folder
		$nodes = glob( ABSPATH . '*' );
		if ( ! $include_core_files ) {
			// @TODO: This is duplicate code - find a way to use it across methods
			$core_files = array(
				'favicon.ico',
				'index.php',
				'license.txt',
				'readme.html',
				'wp-activate.php',
				'wp-app.php',
				'wp-blog-header.php',
				'wp-comments-post.php',
				'wp-config.php',
				'wp-config-sample.php',
				'wp-cron.php',
				'wp-links-opml.php',
				'wp-load.php',
				'wp-login.php',
				'wp-mail.php',
				'wp-pass.php',
				'wp-register.php',
				'wp-settings.php',
				'wp-signup.php',
				'wp-trackback.php',
				'xmlrpc.php',
			);
			foreach ( $nodes as $key => $node ) {
				if ( Main_WP_Helper::startsWith( $node, ABSPATH . WPINC ) ) {
					unset( $nodes[ $key ] );
				} else if ( Main_WP_Helper::startsWith( $node, ABSPATH . basename( admin_url( '' ) ) ) ) {
					unset( $nodes[ $key ] );
				} else {
					foreach ( $core_files as $core_file ) {
						if ( ABSPATH . $core_file === $node ) {
							unset( $nodes[ $key ] );
						}
					}
				}
			}
			unset( $core_files );
		}
		$this->copy_dir( $nodes, $excludes, $backup_folder, $exclude_non_wp, true );
		// to fix bug wrong folder
		/* @codingStandardsIgnoreStart */
		@copy( $backup_folder.'dbBackup.sql', $backup_folder . basename( WP_CONTENT_DIR ) . '/dbBackup.sql' );
		@unlink( $backup_folder.'dbBackup.sql' );
		/* @codingStandardsIgnoreEnd */

		unset( $nodes );

		//Zip this backup folder..
		require_once ( ABSPATH . 'wp-admin/includes/class-pclzip.php');
		$this->zip = new PclZip( $file_path );
		$this->zip->create( $backup_folder, PCLZIP_OPT_REMOVE_PATH, $backup_folder );
		if ( $add_config ) {
			global $wpdb;
			$string = base64_encode(
				serialize(
					array(
						'siteurl' => get_option( 'siteurl' ),
						'home' => get_option( 'home' ),
						'abspath' => ABSPATH,
						'prefix' => $wpdb->prefix,
						'lang' => WPLANG,
					)
				)
			);

			$this->add_file_from_string_to_pcl_zip( 'clone/config.txt', $string, $file_path );
		}

		//Remove backup folder
		Main_WP_Helper::delete_dir( $backup_folder );
		return true;
	}

	/**
	 * Recursive add directory for default PHP zip library
	 *
	 * @param $path
	 * @param $excludes
	 */
	public function zip_add_dir( $path, $excludes ) {
		$this->zip->addEmptyDir( str_replace( ABSPATH, '', $path ) );

		if ( file_exists( rtrim( $path, '/' ) . '/.htaccess' ) ) { $this->add_file_to_zip( rtrim( $path, '/' ) . '/.htaccess', rtrim( str_replace( ABSPATH, '', $path ), '/' ) . '/mainwp-htaccess' ); }

		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ), RecursiveIteratorIterator::SELF_FIRST );

		foreach ( $iterator as $path ) {
			$name = $path->__toString();
			if ( ( '.' === basename( $name ) ) || ( '..' === basename( $name ) ) ) {
				continue;
			}

			if ( ! Main_WP_Helper::inExcludes( $excludes, str_replace( ABSPATH, '', $name ) ) ) {
				if ( $path->isDir() ) {
					$this->zip_add_dir( $name, $excludes );
				} else {
					$this->add_file_to_zip( $name, str_replace( ABSPATH, '', $name ) );
				}
			}
			$name = null;
			unset( $name );
		}

		$iterator = null;
		unset( $iterator );

		// @TODO: Remove this
		//        $nodes = glob(rtrim($path, '/') . '/*');
		//        if (empty($nodes)) return true;
		//
		//        foreach ($nodes as $node)
		//        {
		//            if (!Main_WP_Helper::inExcludes($excludes, str_replace(ABSPATH, '', $node)))
		//            {
		//                if (is_dir($node))
		//                {
		//                    $this->zip_add_dir($node, $excludes);
		//                }
		//                else if (is_file($node))
		//                {
		//                    $this->add_file_to_zip($node, str_replace(ABSPATH, '', $node));
		//                }
		//            }
		//        }
	}

	/**
	 * @param $path
	 * @param $excludes
	 *
	 * @return bool
	 */
	public function pcl_zip_add_dir( $path, $excludes ) {
		$error = false;
		$nodes = glob( rtrim( $path, '/' ) . '/*' );
		if ( empty( $nodes ) ) {
			return true;
		}

		foreach ( $nodes as $node ) {
			if ( null === $excludes || ! in_array( str_replace( ABSPATH, '', $node ), $excludes ) ) {
				if ( is_dir( $node ) ) {
					if ( ! $this->pcl_zip_add_dir( $node, $excludes ) ) {
						$error = true;
						break;
					}
				} else if ( is_file( $node ) ) {
					// @TODO: Refactor to remove the $rslt var, it's unneeded
					if ( 0 === ( $rslt = $this->zip->add( $node, PCLZIP_OPT_REMOVE_PATH, ABSPATH ) ) ) {
						$error = true;
						break;
					}
				}
			}
		}
		return ! $error;
	}

	/**
	 * @param $file
	 * @param $string
	 *
	 * @return bool
	 */
	function add_file_from_string_to_zip( $file, $string ) {
		return $this->zip->addFromString( $file, $string );
	}

	/**
	 * @param $file
	 * @param $string
	 * @param $file_path
	 *
	 * @return bool
	 */
	public function add_file_from_string_to_pcl_zip( $file, $string, $file_path ) {
		$file = preg_replace( '/(?:\.|\/)*(.*)/', '$1', $file );
		$local_path = dirname( $file );
		$tmp_file_name = dirname( $file_path ). '/' . basename( $file );

		if ( false !== file_put_contents( $tmp_file_name, $string ) ) {
			$this->zip->delete( PCLZIP_OPT_BY_NAME, $file );
			$add = $this->zip->add(
				$tmp_file_name,
				PCLZIP_OPT_REMOVE_PATH,
				dirname( $file_path ),
				PCLZIP_OPT_ADD_PATH,
				$local_path
			);
			unlink( $tmp_file_name );
			if ( ! empty( $add ) ) {
				return true;
			}
		}
		return false;
	}

	// @TODO: Move these to top of class
	protected $gc_cnt = 0;
	protected $test_content;

	/**
	 * @param $path
	 * @param $zip_entry_name
	 *
	 * @return bool
	 */
	function add_file_to_zip( $path, $zip_entry_name ) {
		if ( time() - $this->last_run > 20 ) {
			/* @codingStandardsIgnoreStart */
			@set_time_limit( $this->timeout );
			/* @codingStandardsIgnoreEnd */
			$this->last_run = time();
		}

		if ( $this->exclude_zip && Main_WP_Helper::endsWith( $path, '.zip' ) ) {
			return false;
		}

		/*
		 * This would fail with status ZIPARCHIVE::ER_OPEN
		 * after certain number of files is added since
		 * ZipArchive internally stores the file descriptors of all the
		 * added files and only on close writes the contents to the ZIP file
		 *
		 * @see http://bugs.php.net/bug.php?id=40494
		 * @see http://pecl.php.net/bugs/bug.php?id=9443
		 *
		 * return $zip->addFile( $path, $zip_entry_name );
		 */

		$this->zip_archive_size_count += filesize( $path );
		$this->gc_cnt++;

		// 5 mb limit!
		if ( ! $this->load_files_before_zip || ( filesize( $path ) > 5 * 1024 * 1024 ) ) {
			$this->zip_archive_file_count++;
			$added = $this->zip->addFile( $path, $zip_entry_name );
		} else {
			$this->zip_archive_file_count++;

			$this->test_content = file_get_contents( $path );
			if ( $this->test_content === false ) {
				return false;
			}
			$added = $this->zip->addFromString( $zip_entry_name, $this->test_content );
		}

		if ( $this->gc_cnt > 20 ) {
			/* @codingStandardsIgnoreStart */
			if ( function_exists( 'gc_enable' ) ) { @gc_enable(); }
			if ( function_exists( 'gc_collect_cycles' ) ) { @gc_collect_cycles(); }
			/* @codingStandardsIgnoreEnd */
			$this->gc_cnt = 0;
		}

		//Over limits?
		// @TODO: Refactor to remove unused brackets
		if ( ( ( $this->file_descriptors > 0 ) && ( $this->zip_archive_file_count > $this->file_descriptors ) ) ) { // || $this->zip_archive_size_count >= (31457280 * 2))
			$this->zip->close();
			$this->zip = null;
			unset( $this->zip );
			/* @codingStandardsIgnoreStart */
			if ( function_exists( 'gc_enable' ) ) { @gc_enable(); }
			if ( function_exists( 'gc_collect_cycles' ) ) { @gc_collect_cycles(); }
			/* @codingStandardsIgnoreEnd */
			$this->zip = new ZipArchive();
			$this->zip->open( $this->zip_archive_file_name );
			$this->zip_archive_file_count = 0;
			$this->zip_archive_size_count = 0;
		}

		return $added;
	}

	/**
	 * @param $file_path
	 * @param $excludes
	 * @param $add_config
	 * @param $include_core_files
	 * @param $exclude_zip
	 * @param $exclude_non_wp
	 *
	 * @return bool
	 */
	public function create_zip_console_full_backup( $file_path, $excludes, $add_config, $include_core_files, $exclude_zip, $exclude_non_wp ) {
		// @TODO to work with 'zip' from system if PHP Zip library not available
		//system('zip');
		return false;
	}

	/**
	 * @param $file_path
	 * @param bool|false $archive_ext
	 * @param null|Tar_Archiver $archiver
	 *
	 * @return array
	 */
	public function create_backup_db( $file_path, $archive_ext = false, &$archiver = null ) {
		// @TODO Use MINUTES_IN_SECONDS constant ( 20 * MINUTES IN SECONDS )
		// @TODO: Check this. 20 * 60 * 60 to me is 20 hours, not minutes
		$timeout = 20 * 60 * 60; // 20 minutes
		$mem = '512M';

		/* @codingStandardsIgnoreStart */
		@set_time_limit( $timeout );
		@ini_set( 'max_execution_time', $timeout );
		@ini_set( 'memory_limit', $mem );
		/* @codingStandardsIgnoreEnd */

		$fh = fopen( $file_path, 'w' ); //or error;

		/** @var $wpdb wpdb */
		global $wpdb;

		//Get all the tables
		$tables_db = $wpdb->get_results( 'SHOW TABLES FROM `' . DB_NAME . '`', ARRAY_N );
		foreach ( $tables_db as $curr_table ) {
			if ( null !== $archiver ) {
				$archiver->updatePidFile();
			}

			$table = $curr_table[0];

			fwrite( $fh, "\n\n" . 'DROP TABLE IF EXISTS ' . $table . ';' );
			$table_create = $wpdb->get_row( 'SHOW CREATE TABLE ' . $table, ARRAY_N );
			fwrite( $fh, "\n" . $table_create[1] . ";\n\n" );

			/* @codingStandardsIgnoreStart */
			// @TODO: Handle errors, instead of supressing them
			$rows = @Main_WP_Child_DB::_query( 'SELECT * FROM ' . $table, $wpdb->dbh );
			/* @codingStandardsIgnoreEnd */

			if ( $rows ) {
				$i = 0;
				$table_insert = 'INSERT INTO `' . $table . '` VALUES (';

				/* @codingStandardsIgnoreStart */
				// @TODO: Handle errors, instead of supressing them
				while ( $row = @Main_WP_Child_DB::fetch_array( $rows ) ) {
					/* @codingStandardsIgnoreEnd */
					$query = $table_insert;
					foreach ( $row as $value ) {
						$query .= '"' . Main_WP_Child_DB::real_escape_string( $value ) . '", ';
					}
					$query = trim( $query, ', ' ) . ');';

					fwrite( $fh, "\n" . $query );
					$i++;

					if ( $i >= 50 ) {
						fflush( $fh );
						$i = 0;
					}

					$query = null;
					$row = null;
				}
			}
			$rows = null;
			fflush( $fh );
		}

		fclose( $fh );

		if ( false !== $archive_ext ) {
			$new_file_path = $file_path . '.' . $archive_ext;

			if ( 'zip' === $archive_ext ) {
				$this->archiver = null;
			} else {
				$this->archiver = new Tar_Archiver( $this, $archive_ext );
			}

			if ( $this->zip_file( $file_path, $new_file_path ) && file_exists( $new_file_path ) ) {
				/* @codingStandardsIgnoreStart */
				@unlink( $file_path );
				/* @codingStandardsIgnoreEnd */
				$file_path = $new_file_path;
			}
		}
		return array( 'file_path' => $file_path );
	}

	// @TODO: Remove this
	/**
	 * @param $file_path
	 *
	 * @return bool
	 */
	public function create_backup_db_legacy( $file_path ) {
		$fh = fopen( $file_path, 'w' ); //or error;

		global $wpdb;
		$maxchars = 50000;

		//Get all the tables
		$tables_db = $wpdb->get_results( 'SHOW TABLES FROM `' . DB_NAME . '`', ARRAY_N );
		foreach ( $tables_db as $curr_table ) {
			$table = $curr_table[0];

			fwrite( $fh, "\n" . 'DROP TABLE IF EXISTS ' . $table . ';' );
			$table_create = $wpdb->get_row( 'SHOW CREATE TABLE ' . $table, ARRAY_N );
			fwrite( $fh, "\n" . $table_create[1] . ';' );

			//$rows = $wpdb->get_results('SELECT * FROM ' . $table, ARRAY_N);
			/* @codingStandardsIgnoreStart */
			// @TODO: Handle errors, instead of supressing them
			$rows = @Main_WP_Child_DB::_query( 'SELECT * FROM ' . $table, $wpdb->dbh );
			/* @codingStandardsIgnoreEnd */
			if ( $rows ) {
				$table_columns = $wpdb->get_results( 'SHOW COLUMNS FROM ' . $table );
				$table_columns_insert = '';
				foreach ( $table_columns as $table_column ) {
					if ( '' !== $table_columns_insert ) {
						$table_columns_insert .= ', '; }
					$table_columns_insert .= '`' . $table_column->Field . '`';
				}
				$table_insert = 'INSERT INTO `' . $table . '` (';
				$table_insert .= $table_columns_insert;
				$table_insert .= ') VALUES ' . "\n";

				$current_insert = $table_insert;

				$inserted = false;
				$add_insert = '';
				/* @codingStandardsIgnoreStart */
				// @TODO: Handle errors, instead of supressing them
				while ( $row = @Main_WP_Child_DB::fetch_array( $rows ) ) {
					/* @codingStandardsIgnoreEnd */
					//Create new insert!
					$add_insert = '(';
					$add_insert_each = '';
					foreach ( $row as $value ) {
						//$add_insert_each .= "'" . str_replace(array("\n", "\r", "'"), array('\n', '\r', "\'"), $value) . "',";

						$value = addslashes( $value );
						$value = str_replace( "\n","\\n",$value );
						$value = str_replace( "\r","\\r",$value );
						$add_insert_each .= '"'.$value.'",' ;
					}
					$add_insert .= trim( $add_insert_each, ',' ) . ')';

					//If we already inserted something & the total is too long - commit previous!
					if ( $inserted && strlen( $add_insert ) + strlen( $current_insert ) >= $maxchars ) {
						fwrite( $fh, "\n" . $current_insert . ';' );
						$current_insert = $table_insert;
						$current_insert .= $add_insert;
						$inserted = false;
					} else {
						if ( $inserted ) {
							$current_insert .= ', ' . "\n";
						}
						$current_insert .= $add_insert;
					}
					$inserted = true;
				}
				if ( $inserted ) {
					fwrite( $fh, "\n" . $current_insert . ';' );
				}
			}
		}

		fclose( $fh );
		return true;
	}
}
