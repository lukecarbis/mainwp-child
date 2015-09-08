<?php
class Main_WP_Backup {
	protected static $instance = null;
	protected $excludeZip;
	protected $zipArchiveFileCount;
	protected $zipArchiveSizeCount;
	protected $zipArchiveFileName;
	protected $file_descriptors;
	protected $loadFilesBeforeZip;

	protected $timeout;
	protected $lastRun;

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
	 * @param string $filePrefix
	 * @param bool|false $addConfig
	 * @param bool|false $includeCoreFiles
	 * @param int $file_descriptors
	 * @param bool|false $fileSuffix
	 * @param bool|false $excludezip
	 * @param bool|false $excludenonwp
	 * @param bool|true $loadFilesBeforeZip
	 * @param string $ext
	 * @param bool|false $pid
	 * @param bool|false $append
	 *
	 * @return array|bool
	 */
	public function create_full_backup( $excludes, $filePrefix = '', $addConfig = false, $includeCoreFiles = false, $file_descriptors = 0, $fileSuffix = false, $excludezip = false, $excludenonwp = false, $loadFilesBeforeZip = true, $ext = 'zip', $pid = false, $append = false ) {
		$this->file_descriptors = $file_descriptors;
		$this->loadFilesBeforeZip = $loadFilesBeforeZip;

		$dirs = Main_WP_Helper::getMainWPDir( 'backup' );
		$backupdir = $dirs[0];
		if ( ! defined( 'PCLZIP_TEMPORARY_DIR' ) ) {
			define( 'PCLZIP_TEMPORARY_DIR', $backupdir );
		}

		if ( false !== $pid ) {
			$pid = trailingslashit( $backupdir ) . 'backup-' . $pid . '.pid';
		}

		// Verify if another backup is running, if so, return an error
		$files = glob( $backupdir . '*.pid' );
		foreach ( $files as $file ) {
			if ( basename( $file ) === basename( $pid ) ) { continue; }

			if ( (time() - filemtime( $file )) < 160 ) {
				Main_WP_Helper::error( 'Another backup process is running, try again later' );
			}
		}

		$timestamp = time();
		if ( '' !== $filePrefix ) {
			$filePrefix .= '-';
		}

		if ( 'zip' === $ext ) {
			$this->archiver = null;
			$ext = '.zip';
		} else {
			$this->archiver = new Tar_Archiver( $this, $ext, $pid );
			$ext = $this->archiver->getExtension();
		}

		// Throw new Exception('Test 1 2 : ' . print_r($append,1));
		if ( ( false !== $fileSuffix ) && ! empty( $fileSuffix ) ) {
			$file = $fileSuffix . ( true === $append ? '' : $ext ); // Append already contains extension!
		} else {
			$file = 'backup-' . $filePrefix . $timestamp . $ext;
		}
		$filepath = $backupdir . $file;
		$fileurl = $file;

		// @TODO: Remove this
		//        if (!$append)
		//        {
		//            if ($dh = opendir($backupdir))
		//            {
		//                while (($file = readdir($dh)) !== false)
		//                {
		//                    if ($file != '.' && $file != '..' && preg_match('/(.*).(zip|tar|tar.gz|tar.bz2|pid|done)$/', $file))
		//                    {
		//                        @unlink($backupdir . $file);
		//                    }
		//                }
		//                closedir($dh);
		//            }
		//        }

		if ( ! $addConfig ) {
			if ( ! in_array( str_replace( ABSPATH, '', WP_CONTENT_DIR ), $excludes ) && ! in_array( 'wp-admin', $excludes ) && ! in_array( WPINC, $excludes ) ) {
				$addConfig = true;
				$includeCoreFiles = true;
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
			$success = $this->archiver->createFullBackup( $filepath, $excludes, $addConfig, $includeCoreFiles, $excludezip, $excludenonwp, $append );
		} else if ( $this->check_zip_support() ) {
			$success = $this->create_zip_full_backup( $filepath, $excludes, $addConfig, $includeCoreFiles, $excludezip, $excludenonwp );
		} else if ( $this->check_zip_console() ) {
			$success = $this->create_zip_console_full_backup( $filepath, $excludes, $addConfig, $includeCoreFiles, $excludezip, $excludenonwp );
		} else {
			$success = $this->create_zip_pcl_full_backup_2( $filepath, $excludes, $addConfig, $includeCoreFiles, $excludezip, $excludenonwp );
		}

		// @TODO: Refactor as an if statement, to make this easier to read.
		return ( $success ) ? array(
			'timestamp' => $timestamp,
			'file' => $fileurl,
			'filesize' => filesize( $filepath ),
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
		$this->zipArchiveFileCount = 0;
		$this->zipArchiveSizeCount = 0;

		$zipRes = $this->zip->open( $archive, ZipArchive::CREATE );
		if ( $zipRes ) {
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
	 * @param $filepath
	 * @param $excludes
	 * @param $addConfig
	 * @param $includeCoreFiles
	 * @param $excludezip
	 * @param $excludenonwp
	 *
	 * @return bool
	 */
	public function create_zip_full_backup( $filepath, $excludes, $addConfig, $includeCoreFiles, $excludezip, $excludenonwp ) {
		$this->excludeZip = $excludezip;
		$this->zip = new ZipArchive();
		$this->zipArchiveFileCount = 0;
		$this->zipArchiveSizeCount = 0;
		$this->zipArchiveFileName = $filepath;
		$zipRes = $this->zip->open( $filepath, ZipArchive::CREATE );

		if ( $zipRes ) {
			$nodes = glob( ABSPATH . '*' );
			if ( ! $includeCoreFiles ) {
				$coreFiles = array( 'favicon.ico', 'index.php', 'license.txt', 'readme.html', 'wp-activate.php', 'wp-app.php', 'wp-blog-header.php', 'wp-comments-post.php', 'wp-config.php', 'wp-config-sample.php', 'wp-cron.php', 'wp-links-opml.php', 'wp-load.php', 'wp-login.php', 'wp-mail.php', 'wp-pass.php', 'wp-register.php', 'wp-settings.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php' );
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

			$db_backup_file = dirname( $filepath ) . DIRECTORY_SEPARATOR . 'dbBackup.sql';

			$this->create_backup_db( $db_backup_file );
			$this->add_file_to_zip( $db_backup_file, basename( WP_CONTENT_DIR ) . '/dbBackup.sql' );

			if ( file_exists( ABSPATH . '.htaccess' ) ) {
				$this->add_file_to_zip( ABSPATH . '.htaccess', 'mainwp-htaccess' );
			}

			foreach ( $nodes as $node ) {
				if ( $excludenonwp && is_dir( $node ) ) {
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

			if ( $addConfig ) {
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
			@unlink( dirname( $filepath ) . DIRECTORY_SEPARATOR . 'dbBackup.sql' );
			/* @codingStandardsIgnoreEnd */

			return true;
		}

		return false;
	}

	/**
	 * Create full backup using pclZip library
	 *
	 * @param $filepath
	 * @param $excludes
	 * @param $addConfig
	 * @param $includeCoreFiles
	 *
	 * @return bool
	 */
	public function create_zip_pcl_full_backup( $filepath, $excludes, $addConfig, $includeCoreFiles ) {
		require_once ( ABSPATH . 'wp-admin/includes/class-pclzip.php');

		$this->zip = new PclZip( $filepath );
		$nodes = glob( ABSPATH . '*' );
		if ( ! $includeCoreFiles ) {
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

		$this->create_backup_db( dirname( $filepath ) . DIRECTORY_SEPARATOR . 'dbBackup.sql' );
		$error = false;
		// @TODO: Refactor to remove the $rslt var, it's unneeded
		if ( 0 === ( $rslt = $this->zip->add( dirname( $filepath ) . DIRECTORY_SEPARATOR . 'dbBackup.sql', PCLZIP_OPT_REMOVE_PATH, dirname( $filepath ), PCLZIP_OPT_ADD_PATH, basename( WP_CONTENT_DIR ) ) ) ) {
			$error = true;
		}

		/* @codingStandardsIgnoreStart */
		@unlink( dirname( $filepath ) . DIRECTORY_SEPARATOR . 'dbBackup.sql' );
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

		if ( $addConfig ) {
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

			$this->add_file_from_string_to_pcl_zip( 'clone/config.txt', $string, $filepath );
		}

		if ( $error ) {
			/* @codingStandardsIgnoreStart */
			@unlink( $filepath );
			/* @codingStandardsIgnoreEnd */
			return false;
		}

		return true;
	}

	/**
	 * @param $nodes
	 * @param $excludes
	 * @param $backupfolder
	 * @param $excludenonwp
	 * @param $root
	 */
	function copy_dir( $nodes, $excludes, $backupfolder, $excludenonwp, $root ) {
		if ( ! is_array( $nodes ) ) { return; }

		foreach ( $nodes as $node ) {
			if ( $excludenonwp && is_dir( $node ) ) {
				if ( ! Main_WP_Helper::startsWith( $node, WP_CONTENT_DIR ) && ! Main_WP_Helper::startsWith( $node,  ABSPATH . 'wp-admin' ) && ! Main_WP_Helper::startsWith( $node, ABSPATH . WPINC ) ) {
					continue;
				}
			}

			if ( ! Main_WP_Helper::inExcludes( $excludes, str_replace( ABSPATH, '', $node ) ) ) {
				if ( is_dir( $node ) ) {
					if ( ! file_exists( str_replace( ABSPATH, $backupfolder, $node ) ) ) {
						/* @codingStandardsIgnoreStart */
					   @mkdir( str_replace( ABSPATH, $backupfolder, $node ) );
						/* @codingStandardsIgnoreEnd */
					}

					$newnodes = glob( $node . DIRECTORY_SEPARATOR . '*' );
					$this->copy_dir( $newnodes, $excludes, $backupfolder, $excludenonwp, false );
					unset( $newnodes );
				} else if ( is_file( $node ) ) {
					if ( $this->excludeZip && Main_WP_Helper::endsWith( $node, '.zip' ) ) { continue; }

					/* @codingStandardsIgnoreStart */
					@copy( $node, str_replace( ABSPATH, $backupfolder, $node ) );
					/* @codingStandardsIgnoreEnd */
				}
			}
		}
	}

	/**
	 * @param $filepath
	 * @param $excludes
	 * @param $addConfig
	 * @param $includeCoreFiles
	 * @param $excludezip
	 * @param $excludenonwp
	 *
	 * @return bool
	 */
	public function create_zip_pcl_full_backup_2( $filepath, $excludes, $addConfig, $includeCoreFiles, $excludezip, $excludenonwp ) {
		// @TODO: $excludezip is unused

		global $classDir;
		// @TODO: $classDir is unused

		//Create backup folder
		$backupFolder = dirname( $filepath ) . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR;
		/* @codingStandardsIgnoreStart */
		@mkdir( $backupFolder );
		/* @codingStandardsIgnoreEnd */

		//Create DB backup
		$this->create_backup_db( $backupFolder . 'dbBackup.sql' );

		//Copy installation to backup folder
		$nodes = glob( ABSPATH . '*' );
		if ( ! $includeCoreFiles ) {
			// @TODO: This is duplicate code - find a way to use it across methods
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
		$this->copy_dir( $nodes, $excludes, $backupFolder, $excludenonwp, true );
		// to fix bug wrong folder
		/* @codingStandardsIgnoreStart */
		@copy( $backupFolder.'dbBackup.sql', $backupFolder . basename( WP_CONTENT_DIR ) . '/dbBackup.sql' );
		@unlink( $backupFolder.'dbBackup.sql' );
		/* @codingStandardsIgnoreEnd */

		unset( $nodes );

		//Zip this backup folder..
		require_once ( ABSPATH . 'wp-admin/includes/class-pclzip.php');
		$this->zip = new PclZip( $filepath );
		$this->zip->create( $backupFolder, PCLZIP_OPT_REMOVE_PATH, $backupFolder );
		if ( $addConfig ) {
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

			$this->add_file_from_string_to_pcl_zip( 'clone/config.txt', $string, $filepath );
		}

		//Remove backup folder
		Main_WP_Helper::delete_dir( $backupFolder );
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
			if ( nul === $excludes || ! in_array( str_replace( ABSPATH, '', $node ), $excludes ) ) {
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
	 * @param $filepath
	 *
	 * @return bool
	 */
	public function add_file_from_string_to_pcl_zip( $file, $string, $filepath ) {
		$file = preg_replace( '/(?:\.|\/)*(.*)/', '$1', $file );
		$localpath = dirname( $file );
		$tmpfilename = dirname( $filepath ). '/' . basename( $file );

		if ( false !== file_put_contents( $tmpfilename, $string ) ) {
			$this->zip->delete( PCLZIP_OPT_BY_NAME, $file );
			$add = $this->zip->add(
				$tmpfilename,
				PCLZIP_OPT_REMOVE_PATH,
				dirname( $filepath ),
				PCLZIP_OPT_ADD_PATH,
				$localpath
			);
			unlink( $tmpfilename );
			if ( ! empty( $add ) ) {
				return true;
			}
		}
		return false;
	}

	// @TODO: Move these to top of class
	protected $gcCnt = 0;
	protected $testContent;

	/**
	 * @param $path
	 * @param $zipEntryName
	 *
	 * @return bool
	 */
	function add_file_to_zip( $path, $zipEntryName ) {
		if ( time() - $this->lastRun > 20 ) {
			/* @codingStandardsIgnoreStart */
			@set_time_limit( $this->timeout );
			/* @codingStandardsIgnoreEnd */
			$this->lastRun = time();
		}

		if ( $this->excludeZip && Main_WP_Helper::endsWith( $path, '.zip' ) ) { return false; }

		/*
		 * This would fail with status ZIPARCHIVE::ER_OPEN
		 * after certain number of files is added since
		 * ZipArchive internally stores the file descriptors of all the
		 * added files and only on close writes the contents to the ZIP file
		 *
		 * @see http://bugs.php.net/bug.php?id=40494
		 * @see http://pecl.php.net/bugs/bug.php?id=9443
		 *
		 * return $zip->addFile( $path, $zipEntryName );
		 */

		$this->zipArchiveSizeCount += filesize( $path );
		$this->gcCnt++;

		// 5 mb limit!
		if ( ! $this->loadFilesBeforeZip || ( filesize( $path ) > 5 * 1024 * 1024 ) ) {
			$this->zipArchiveFileCount++;
			$added = $this->zip->addFile( $path, $zipEntryName );
		} else {
			$this->zipArchiveFileCount++;

			$this->testContent = file_get_contents( $path );
			if ( $this->testContent === false ) {
				return false;
			}
			$added = $this->zip->addFromString( $zipEntryName, $this->testContent );
		}

		if ( $this->gcCnt > 20 ) {
			/* @codingStandardsIgnoreStart */
			if ( function_exists( 'gc_enable' ) ) { @gc_enable(); }
			if ( function_exists( 'gc_collect_cycles' ) ) { @gc_collect_cycles(); }
			/* @codingStandardsIgnoreEnd */
			$this->gcCnt = 0;
		}

		//Over limits?
		// @TODO: Refactor to remove unused brackets
		if ( ( ( $this->file_descriptors > 0 ) && ( $this->zipArchiveFileCount > $this->file_descriptors ) ) ) { // || $this->zipArchiveSizeCount >= (31457280 * 2))
			$this->zip->close();
			$this->zip = null;
			unset( $this->zip );
			/* @codingStandardsIgnoreStart */
			if ( function_exists( 'gc_enable' ) ) { @gc_enable(); }
			if ( function_exists( 'gc_collect_cycles' ) ) { @gc_collect_cycles(); }
			/* @codingStandardsIgnoreEnd */
			$this->zip = new ZipArchive();
			$this->zip->open( $this->zipArchiveFileName );
			$this->zipArchiveFileCount = 0;
			$this->zipArchiveSizeCount = 0;
		}

		return $added;
	}

	/**
	 * @param $filepath
	 * @param $excludes
	 * @param $addConfig
	 * @param $includeCoreFiles
	 * @param $excludezip
	 * @param $excludenonwp
	 *
	 * @return bool
	 */
	public function create_zip_console_full_backup( $filepath, $excludes, $addConfig, $includeCoreFiles, $excludezip, $excludenonwp ) {
		// @TODO to work with 'zip' from system if PHP Zip library not available
		//system('zip');
		return false;
	}

	/**
	 * @param $filepath
	 * @param bool|false $archiveExt
	 * @param null|Tar_Archiver $archiver
	 *
	 * @return array
	 */
	public function create_backup_db( $filepath, $archiveExt = false, &$archiver = null ) {
		// @TODO Use MINUTES_IN_SECONDS constant ( 20 * MINUTES IN SECONDS )
		// @TODO: Check this. 20 * 60 * 60 to me is 20 hours, not minutes
		$timeout = 20 * 60 * 60; // 20 minutes
		$mem = '512M';

		/* @codingStandardsIgnoreStart */
		@set_time_limit( $timeout );
		@ini_set( 'max_execution_time', $timeout );
		@ini_set( 'memory_limit', $mem );
		/* @codingStandardsIgnoreEnd */

		$fh = fopen( $filepath, 'w' ); //or error;

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

		if ( false !== $archiveExt ) {
			$newFilepath = $filepath . '.' . $archiveExt;

			if ( 'zip' === $archiveExt ) {
				$this->archiver = null;
			} else {
				$this->archiver = new Tar_Archiver( $this, $archiveExt );
			}

			if ( $this->zip_file( $filepath, $newFilepath ) && file_exists( $newFilepath ) ) {
				/* @codingStandardsIgnoreStart */
				@unlink( $filepath );
				/* @codingStandardsIgnoreEnd */
				$filepath = $newFilepath;
			}
		}
		return array( 'filepath' => $filepath );
	}

	// @TODO: Remove this
	/**
	 * @param $filepath
	 *
	 * @return bool
	 */
	public function create_backup_db_legacy( $filepath ) {

		$fh = fopen( $filepath, 'w' ); //or error;

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
