<?php
/*
Plugin Name: ProjectTheme LiveChat Users
Plugin URI: https://sitemile.com
Description: Adds live chat between users for your project theme from sitemile.com
Version: 1.6.0 (Cloudways Compatible)
Author: sitemile.com
Author URI: https://sitemile.com
Text Domain: pt_livechat
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Include the class and function files for messaging
include 'messaging.php';

/**
 * Enqueue scripts and styles for the live chat functionality.
 */
function pt_live_chat_thing_style() {
    // Only load on messaging pages to reduce conflicts
    if (is_page() && has_shortcode(get_post()->post_content, 'project_theme_my_account_livechat')) {
        wp_enqueue_script('jquery');
        wp_enqueue_script('live-chat-js-lib', plugin_dir_url(__FILE__) . 'bootstrap-filestyle.min.js', array('jquery'), '2.1.0', true);
        wp_enqueue_script('live-chat-js', plugin_dir_url(__FILE__) . 'messages.js', array('jquery'), '1.6.0', true);

        // Pass PHP variables to our JavaScript file
        wp_localize_script('live-chat-js', 'pt_livechat_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pt-livechat-nonce'),
            'MESSAGE_EMPTY_STRING' => __('You need to type a message.', 'pt_livechat'),
            'video_meeting_text' => __('Join Video Meeting', 'pt_livechat'),
            'site_url' => site_url(),
        ));

        wp_enqueue_style('live-chat-css', plugin_dir_url(__FILE__) . "messages.css", array(), '1.6.0');
    }
}
add_action('wp_enqueue_scripts', 'pt_live_chat_thing_style');

/**
 * Plugin activation hook to create the necessary messaging page and database tables.
 */
register_activation_hook(__FILE__, 'lv_pp_myplugin_activate');
function lv_pp_myplugin_activate() {
    if (function_exists('ProjectTheme_insert_pages_account')) {
        ProjectTheme_insert_pages_account('ProjectTheme_my_account_livechat_id', "Messaging", '[project_theme_my_account_livechat]', get_option('ProjectTheme_my_account_page_id'));
    }
    
    // Ensure database tables have the required columns
    pt_livechat_ensure_database_schema();
}

/**
 * Ensure the database schema is up to date with required columns.
 */
function pt_livechat_ensure_database_schema() {
    global $wpdb;
    
    // Add zoom_link_url and zoom_link_timestamp to project_pm_threads table if they don't exist
    $threads_table_name = $wpdb->prefix . 'project_pm_threads';
    
    $column_exists_url = $wpdb->query("SHOW COLUMNS FROM `$threads_table_name` LIKE 'zoom_link_url'");
    $column_exists_timestamp = $wpdb->query("SHOW COLUMNS FROM `$threads_table_name` LIKE 'zoom_link_timestamp'");
    
    if (!$column_exists_url) {
        $wpdb->query("ALTER TABLE `$threads_table_name` ADD COLUMN `zoom_link_url` TEXT NULL");
    }
    if (!$column_exists_timestamp) {
        $wpdb->query("ALTER TABLE `$threads_table_name` ADD COLUMN `zoom_link_timestamp` BIGINT(20) NULL");
    }
    
    // Add attached_files_json to project_pm table if it doesn't exist
    $pm_table_name = $wpdb->prefix . 'project_pm';
    $column_exists_json = $wpdb->query("SHOW COLUMNS FROM `$pm_table_name` LIKE 'attached_files_json'");
    
    if (!$column_exists_json) {
        $wpdb->query("ALTER TABLE `$pm_table_name` ADD COLUMN `attached_files_json` TEXT NULL");
    }
}

/**
 * Updates user's last online status on every page load.
 */
add_action('template_redirect', 'lv_pt_update_user_online_status');
function lv_pt_update_user_online_status() {
    if (is_user_logged_in()) {
        $current_user_id = get_current_user_id();
        update_user_meta($current_user_id, 'lastonline', current_time('timestamp'));
        clean_user_cache($current_user_id);
    }
}

//======================================================================
// AJAX HANDLERS
//======================================================================

/**
 * AJAX handler for sending a regular chat message.
 */
add_action('wp_ajax_send_regular_chat_message', 'pt_handle_send_regular_chat_message');
function pt_handle_send_regular_chat_message() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pt-livechat-nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }

    $raw_message_content = isset($_POST['chatbox_textarea']) ? wp_unslash($_POST['chatbox_textarea']) : '';
    $thid = isset($_POST['thid']) ? (int) $_POST['thid'] : 0;
    $to_user = isset($_POST['to_user']) ? (int) $_POST['to_user'] : 0;
    $current_user_id = get_current_user_id();

    // Basic validation
    if (!$current_user_id || $thid <= 0 || $to_user <= 0) {
        wp_send_json_error(['message' => 'Invalid request parameters.']);
    }

    // Check if user has connect credits (if credit system is enabled)
    if (function_exists('pt_get_connect_credits')) {
        $current_credits = pt_get_connect_credits($current_user_id);
        if ($current_credits <= 0) {
            wp_send_json_error(['message' => 'Insufficient connect credits.']);
        }
    }

    $uploaded_files_data = [];
    $has_attachment = 0;

    // Handle file uploads if present
    if (isset($_FILES['file']) && is_array($_FILES['file']['name'])) {
        $files = $_FILES['file'];
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        foreach ($files['name'] as $key => $value) {
            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                $file = array(
                    'name' => $files['name'][$key],
                    'type' => $files['type'][$key],
                    'tmp_name' => $files['tmp_name'][$key],
                    'error' => $files['error'][$key],
                    'size' => $files['size'][$key]
                );

                $attachment_id = media_handle_sideload($file, 0);
                
                if (!is_wp_error($attachment_id)) {
                    $file_url = wp_get_attachment_url($attachment_id);
                    $uploaded_files_data[] = array(
                        'id' => $attachment_id,
                        'url' => $file_url,
                        'name' => $files['name'][$key],
                        'type' => $files['type'][$key]
                    );
                    $has_attachment = 1;
                }
            }
        }
    }

    // Prepare message content
    $message_content = trim($raw_message_content);
    if (empty($message_content) && empty($uploaded_files_data)) {
        wp_send_json_error(['message' => 'Message cannot be empty.']);
    }

    // Add file attachment JSON to message content if files were uploaded
    $attached_files_json_string = null;
    if (!empty($uploaded_files_data)) {
        $attached_files_json_string = '__FILE_ATTACHMENT_JSON_START__' . json_encode($uploaded_files_data) . '__FILE_ATTACHMENT_JSON_END__';
    }

    // Insert the message
    $chat = new project_chat($thid);
    $message_id = $chat->insert_message($current_user_id, $to_user, $message_content, $has_attachment, $attached_files_json_string);

    if ($message_id === false) {
        wp_send_json_error(['message' => 'Failed to send message.']);
    }

    // Deduct connect credit if successful
    if (function_exists('pt_deduct_connect_credits')) {
        pt_deduct_connect_credits($current_user_id, 1);
    }

    // Update recipient's last online status
    update_user_meta($to_user, 'lastonline', current_time('timestamp'));
    clean_user_cache($to_user);

    // Send email notification if recipient is offline
    if (!projecttheme_is_user_online($to_user)) {
        if (function_exists('ProjectTheme_send_email_on_priv_mess_received')) {
            ProjectTheme_send_email_on_priv_mess_received($current_user_id, $to_user);
        }
    }

    wp_send_json_success([
        'message' => 'Message sent successfully.',
        'message_id' => $message_id,
        'timestamp' => current_time('timestamp')
    ]);
}

/**
 * AJAX handler for updating messages.
 */
add_action('wp_ajax_updatemessages_regular', 'pt_handle_updatemessages_regular');
function pt_handle_updatemessages_regular() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pt-livechat-nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }

    $thid = isset($_POST['thid']) ? (int) $_POST['thid'] : 0;
    $last_id = isset($_POST['last_id']) ? (int) $_POST['last_id'] : 0;
    $current_user_id = get_current_user_id();

    if (!$current_user_id || $thid <= 0) {
        wp_send_json_error(['message' => 'Invalid request parameters.']);
    }

    $chat = new project_chat($thid);
    $messages = $chat->get_messages_from_order_higher_than_id($current_user_id, $last_id, true);

    if ($messages === false) {
        wp_send_json_error(['message' => 'Failed to fetch messages.']);
    }

    wp_send_json_success([
        'messages' => $messages,
        'last_id' => $last_id
    ]);
}

/**
 * AJAX handler for chat search.
 */
add_action('wp_ajax_get_chat_search', 'pt_handle_get_chat_search');
function pt_handle_get_chat_search() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pt-livechat-nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }

    $search_query = isset($_POST['search_query']) ? sanitize_text_field($_POST['search_query']) : '';
    $current_user_id = get_current_user_id();

    if (!$current_user_id) {
        wp_send_json_error(['message' => 'User not authenticated.']);
    }

    $chat = new project_chat();
    $threads = empty($search_query) ? 
        $chat->get_all_thread_ids($current_user_id) : 
        $chat->get_all_thread_ids_by_search($current_user_id, $search_query);

    if ($threads === false) {
        wp_send_json_success(['threads' => []]);
    }

    wp_send_json_success(['threads' => $threads]);
}

/**
 * Helper function to get PM link from user.
 */
function projecttheme_get_pm_link_from_user($current_uid, $uid2) {
    $pm_page = get_option('ProjectTheme_my_account_livechat_id');
    
    if (projecttheme_using_permalinks()) {
        return get_permalink($pm_page) . "?thid=" . $uid2;
    } else {
        return get_permalink($pm_page) . "&thid=" . $uid2;
    }
}