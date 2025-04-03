<?php
/**
 * Plugin Name: Client Status API
 * Plugin URI: https://captivation.agency/
 * Description: A WordPress plugin that manages and monitors client websites using cron jobs, REST API routes, and external API integrations.
 * Version: 1.0.4
 * Author: Barry Ross
 * Author URI: https://captivation.agency/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: client-status-api
 * Domain Path: /languages
 *
 * @package Client_Status_API
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Include the main Client_Status_API class.
require_once plugin_dir_path(__FILE__) . 'class-client-status-api.php';

// Initialize the class
new Client_Status_API();
