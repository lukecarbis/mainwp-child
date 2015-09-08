<?php
/*
  Plugin Name: MainWP Child
  Plugin URI: http://mainwp.com/
  Description: Child Plugin for MainWP. The plugin is used so the installed blog can be securely managed remotely by your network. Plugin documentation and options can be found here http://docs.mainwp.com
  Author: MainWP
  Author URI: http://mainwp.com
  Version: 2.0.28
 */

// @TODO: Simplify if statement to make it readable
if ( ( isset( $_REQUEST['heatmap'] ) && '1' === $_REQUEST['heatmap'] ) || ( isset($_REQUEST['mainwpsignature'] ) && ( ! empty($_REQUEST['mainwpsignature'] ) ) ) ) {
	header( 'X-Frame-Options: ALLOWALL' );
}

//header('X-Frame-Options: GOFORIT');
// @TODO: What is this version information used for? Why not use bloginfo('version') instead of including this file?
include_once( ABSPATH . 'wp-includes' . DIRECTORY_SEPARATOR . 'version.php' ); //Version information from wordpress

// @TODO: Simplify with plugin_dir_path( __FILE__ ) and trailingslashit()
$classDir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace( basename( __FILE__ ), '', plugin_basename( __FILE__ ) ) . 'class' . DIRECTORY_SEPARATOR;

function mainwp_child_autoload( $class_name ) {
	$class_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace( basename( __FILE__ ), '', plugin_basename( __FILE__ ) ) . 'class' . DIRECTORY_SEPARATOR . $class_name . '.class.php';
	if ( file_exists( $class_file ) ) {
		require_once( $class_file );
	}
}

if ( function_exists( 'spl_autoload_register' ) ) {
	spl_autoload_register( 'mainwp_child_autoload' );
} else {
	function __autoload( $class_name ) {
		mainwp_child_autoload( $class_name );
	}
}

// @TODO: Couldn't this simply be MainWPChild( __FILE__ )?
$main_wp_child = new MainWPChild( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . plugin_basename( __FILE__ ) );

// Back compatibility
$mainWPChild = $main_wp_child;

register_activation_hook( __FILE__, array( $mainWPChild, 'activation' ) );
register_deactivation_hook( __FILE__, array( $mainWPChild, 'deactivation' ) );
