<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://facebook.com/jaydeep.gajera
 * @since             1.0.0
 * @package           Botxbyte
 *
 * @wordpress-plugin
 * Plugin Name:       Botxbyte
 * Plugin URI:        https://botxbyte.com
 * Description:       This is an amalgamation of various automated components, each offering different functionality modules. It has been developed solely for personal use.
 * Version:           1.0.0
 * Author:            Jaydeep Gajera
 * Author URI:        https://facebook.com/jaydeep.gajera
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       botxbyte
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'BOTXBYTE_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-botxbyte-activator.php
 */
function activate_botxbyte() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-botxbyte-activator.php';
	Botxbyte_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-botxbyte-deactivator.php
 */
function deactivate_botxbyte() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-botxbyte-deactivator.php';
	Botxbyte_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_botxbyte' );
register_deactivation_hook( __FILE__, 'deactivate_botxbyte' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-botxbyte.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_botxbyte() {

	$plugin = new Botxbyte();
	$plugin->run();

}
run_botxbyte();
