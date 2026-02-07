<?php
/**
 * Plugin Name: Personalization API
 * Plugin URI: https://github.com/lloan/personalization-api
 * Description: REST API and admin tools for personalized content recommendations based on user attributes (industry, company_size, role).
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: lloan alas
 * License: GPL v2 or later
 * Text Domain: personalization-api
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PERSONALIZATION_API_VERSION', '1.0.0' );
define( 'PERSONALIZATION_API_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PERSONALIZATION_API_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PERSONALIZATION_API_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once PERSONALIZATION_API_PLUGIN_DIR . 'includes/class-post-meta.php';
require_once PERSONALIZATION_API_PLUGIN_DIR . 'includes/class-cache.php';
require_once PERSONALIZATION_API_PLUGIN_DIR . 'includes/class-logger.php';
require_once PERSONALIZATION_API_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once PERSONALIZATION_API_PLUGIN_DIR . 'includes/class-analytics.php';
require_once PERSONALIZATION_API_PLUGIN_DIR . 'includes/class-admin.php';

/**
 * Bootstrap the plugin.
 */
function personalization_api_init() {
	Personalization_API\Post_Meta::instance();
	Personalization_API\REST_API::instance();
	Personalization_API\Analytics::instance();
	if ( is_admin() ) {
		Personalization_API\Admin::instance();
	}
}
add_action( 'plugins_loaded', 'personalization_api_init' );

/**
 * Activation: set default options and capability.
 */
function personalization_api_activate() {
	Personalization_API\Post_Meta::instance()->register_meta();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'personalization_api_activate' );

/**
 * Deactivation: flush rewrite rules.
 */
function personalization_api_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'personalization_api_deactivate' );
