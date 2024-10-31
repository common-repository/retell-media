<?php
/*
Plugin Name: Retell.media
Description: AI-Powered Content Monitoring and Writing System: Revolutionizing Article Creation! 
Version: 1.0.6
Author: Retell.media
Author URI: https://retell.media/?utm_source=plugin&utm_medium=store_page
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'retell-functions.php';

register_uninstall_hook(__FILE__, 'retell_uninstall');
register_deactivation_hook(__FILE__, 'retell_deactivate');
