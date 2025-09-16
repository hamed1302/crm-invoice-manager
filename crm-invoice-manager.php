<?php
/**
 * Plugin Name:       CRM Invoice Manager
 * Plugin URI:        https://haamdast.ir/
 * Description:       A custom plugin to manage client invoices, generate secure links, and track views.
 * Version:           1.6.5
 * Author:            muharramnia
 * Author URI:        https://haamdast.ir/
 * License:           GPL v2 or later
 * Text Domain:       crm-invoice-manager
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define Constants
define( 'CIM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CIM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once CIM_PLUGIN_DIR . 'includes/post-types.php';
require_once CIM_PLUGIN_DIR . 'includes/meta-boxes.php';
require_once CIM_PLUGIN_DIR . 'includes/tracking-handler.php';
require_once CIM_PLUGIN_DIR . 'includes/settings-page.php';

/**
 * Function to run on plugin activation.
 */
function cim_activate_plugin() {
    cim_create_default_invoice_statuses();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'cim_activate_plugin' );

/**
 * A single, unified function to load all necessary admin scripts on the correct pages.
 */
function cim_unified_admin_scripts_loader($hook) {
    global $post;

    // Scripts for the settings page
    if (strpos($hook, 'cim-settings') !== false) {
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('cim-admin-script', CIM_PLUGIN_URL . 'assets/js/admin-script.js', ['jquery'], '1.3.0', true);
        $custom_css = ".cim-settings-wrap .logo-preview-wrapper { display: flex; gap: 10px; flex-wrap: wrap; padding-top: 10px; } .cim-settings-wrap .logo-item { position: relative; border: 1px solid #ddd; padding: 5px; background: #f7f7f7; border-radius: 4px; } .cim-settings-wrap .logo-item img { max-height: 60px; max-width: 150px; display: block; } .cim-settings-wrap .remove-logo { position: absolute; top: -10px; right: -10px; background: #d63638; color: white; border-radius: 50%; width: 22px; height: 22px; text-align: center; line-height: 22px; cursor: pointer; font-weight: bold; } .cim-settings-wrap .remove-single-logo { margin-right: 10px; vertical-align: middle; }";
        wp_add_inline_style('wp-admin', $custom_css);
    }

    // Enqueue media scripts for invoice and client post types
    if ( ($hook == 'post-new.php' || $hook == 'post.php') ) {
        if ( is_object($post) && isset($post->post_type) && ($post->post_type === 'invoice' || $post->post_type === 'client') ) {
            wp_enqueue_media();
        }
    }
}
add_action('admin_enqueue_scripts', 'cim_unified_admin_scripts_loader');

/**
 * Changes the name of the main admin menu for the plugin.
 */
function cim_change_admin_menu_name() {
    global $menu;
    foreach ($menu as $key => $value) {
        if (isset($value[2]) && $value[2] === 'edit.php?post_type=invoice') {
            $menu[$key][0] = 'مدیریت فاکتور';
            break;
        }
    }
}
add_action('admin_menu', 'cim_change_admin_menu_name', 99);

// The function 'cim_remove_unwanted_meta_boxes' and its action hook have been completely removed
// to ensure nothing is hidden on the edit screen.