<?php
/*
Plugin Name: ProjectTheme LiveChat Users
Plugin URI: https://sitemile.com
Description: Adds live chat between users for your project theme from sitemile.com
Version: 1.5.5 (Updated)
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
    wp_enqueue_script( 'live-chat-js-lib', plugin_dir_url( __FILE__ )  . 'bootstrap-filestyle.min.js', array('jquery'), '2.1.0', true );
    wp_enqueue_script( 'live-chat-js', plugin_dir_url( __FILE__ )  . 'messages.js', array('jquery'), '1.5.0', true );

    // Pass PHP variables to our JavaScript file
    wp_localize_script( 'live-chat-js', 'pt_livechat_ajax', array(
        'ajaxurl'            => admin_url( 'admin-ajax.php' ),
        'nonce'              => wp_create_nonce( 'pt-livechat-nonce' ),
        'MESSAGE_EMPTY_STRING' => __('You need to type a message.', 'pt_livechat'),
        'video_meeting_text' => __('Join Video Meeting', 'pt_livechat'),
    ));

    wp_enqueue_style( 'live-chat-css', plugin_dir_url( __FILE__ ) . "messages.css", array(), '1.5.0' );
}
add_action( 'wp_enqueue_scripts', 'pt_live_chat_thing_style' );


/**
 * Plugin activation hook to create the necessary messaging page.
 */
register_activation_hook( __FILE__, 'lv_pp_myplugin_activate' );
function lv_pp_myplugin_activate() {
    if(function_exists('ProjectTheme_insert_pages_account')) {
        ProjectTheme_insert_pages_account('ProjectTheme_my_account_livechat_id', "Messaging", '[project_theme_my_account_livechat]', get_option('ProjectTheme_my_account_page_id') );
    }
}

/**
 * Updates user's last online status on every page load.
 * This is for the currently logged-in user.
 */
add_action('template_redirect','lv_pt_update_user_online_status');
function lv_pt_update_user_online_status()
{
    if(is_user_logged_in()) {
        $current_user_id = get_current_user_id();
        update_user_meta($current_user_id, 'lastonline', current_time('timestamp') );
        clean_user_cache($current_user_id);
    }
}


//======================================================================
// AJAX HANDLERS
//======================================================================

/**
 * AJAX handler for sending a regular chat message.
 * Will also handle file uploads and update lastonline status for the recipient.
 */
add_action( 'wp_ajax_send_regular_chat_message', 'pt_handle_send_regular_chat_message' );
function pt_handle_send_regular_chat_message() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'pt-livechat-nonce' ) ) {
        wp_send_json_error( ['message' => 'Security check failed.'] );
    }

    $raw_message_content = isset( $_POST['chatbox_textarea'] ) ? wp_unslash($_POST['chatbox_textarea']) : '';
    $thid = isset( $_POST['thid'] ) ? (int) $_POST['thid'] : 0;
    $to_user = isset( $_POST['to_user'] ) ? (int) $_POST['to_user'] : 0;
    $current_user_id = get_current_user_id();

    // --- DEBUGGING FOR "Invalid request" ---
    error_log("DEBUG: send_regular_chat_message - current_user_id: " . $current_user_id);
    error_log("DEBUG: send_regular_chat_message - thid: " . $thid);
    error_log("DEBUG: send_regular_chat_message - to_user: " . $to_user);

    if ( ! $current_user_id || $thid <= 0 || $to_user <= 0 ) {
        wp_send_json_error( ['message' => 'Invalid request.'] );
    }

    $uploaded_files_data = []; // Array to store data for JSON encoding
    $has_attachment = 0;

    if (isset($_FILES['file']) && is_array($_FILES['file']['name'])) {
        $files = $_FILES['file'];
        
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        $upload_overrides = array( 'test_form' => false );
        $uploaded_count = 0;

        foreach ($files['name'] as $key => $value) {
            if ($files['name'][$key] && $uploaded_count < 3) {
                $file_array = array(
                    'name'     => $files['name'][$key],
                    'type'     => $files['type'][$key],
                    'tmp_name' => $files['tmp_name'][$key],
                    'error'    => $files['error'][$key],
                    'size'     => $files['size'][$key],
                );

                $uploaded_file = wp_handle_upload( $file_array, $upload_overrides );

                if (isset($uploaded_file['file'])) {
                    $attachment_id = wp_insert_attachment( array(
                        'guid'           => $uploaded_file['url'],
                        'post_mime_type' => $uploaded_file['type'],
                        'post_title'     => sanitize_file_name( $uploaded_file['name'] ),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    ), $uploaded_file['file'] );

                    if (!is_wp_error($attachment_id)) {
                        update_post_meta( $attachment_id, '_wp_attached_file', _wp_relative_upload_path( $uploaded_file['file'] ) );
                        $attachment_data = wp_generate_attachment_metadata( $attachment_id, $uploaded_file['file'] );
                        wp_update_attachment_metadata( $attachment_id, $attachment_data );

                        $file_url = $uploaded_file['url'];
                        $file_name = basename(parse_url($file_url, PHP_URL_PATH));
                        $mime_type = $uploaded_file['type'];

                        // Store data for JSON
                        $uploaded_files_data[] = [
                            'url'  => $file_url,
                            'name' => $file_name,
                            'type' => $mime_type,
                        ];
                        
                        $uploaded_count++;
                        $has_attachment = 1;

                    } else {
                        error_log("Error inserting attachment: " . $attachment_id->get_error_message());
                    }
                } else {
                    error_log("Error uploading file: " . ($uploaded_file['error'] ?? 'Unknown error'));
                }
            }
        }
    }

    // Build the final message content string for saving.
    // Text content is escaped. File data is JSON encoded and embedded with markers.
    $final_message_to_save = esc_html($raw_message_content); 

    if (!empty($uploaded_files_data)) {
        $files_json_string = json_encode($uploaded_files_data, JSON_UNESCAPED_SLASHES);
        // Embed the JSON string with the markers that JavaScript expects
        $final_message_to_save .= '__FILE_ATTACHMENT_JSON_START__' . $files_json_string . '__FILE_ATTACHMENT_JSON_END__';
    }

    $chat = new project_chat( $thid );
    // Pass the message content (which now contains embedded JSON if files exist)
    // The 'attached_files_json' parameter in insert_message is now NULL as we embed it in 'content'.
    $message_inserted_id = $chat->insert_message( 
        $current_user_id, 
        $to_user, 
        $final_message_to_save, 
        $has_attachment, 
        NULL 
    );

    if ( $message_inserted_id ) {
        update_user_meta($to_user, 'lastonline', current_time('timestamp'));
        clean_user_cache($to_user);

        wp_send_json_success( ['new_last_id' => $message_inserted_id] );
    } else {
        wp_send_json_error( ['message' => 'Failed to save message.'] );
    }
}

/**
 * AJAX handler for polling for new messages.
 * This version now passes the content as is, as it should contain full HTML.
 */
add_action( 'wp_ajax_updatemessages_regular', 'pt_handle_updatemessages_regular' );
function pt_handle_updatemessages_regular() {
    $last_id = isset( $_GET['last_id'] ) ? (int) $_GET['last_id'] : 0;
    $thid = isset( $_GET['thid'] ) ? (int) $_GET['thid'] : 0;
    $current_user_id = get_current_user_id();

    if ( ! $current_user_id || $thid <= 0 ) {
        wp_send_json_error( ['message' => 'Invalid request.'] );
    }

    $chat_orders = new project_chat( $thid );
    // We fetch the 'content' column which now contains the embedded JSON for files.
    $messages = $chat_orders->get_messages_from_order_higher_than_id($current_user_id, $last_id); 
    
    $response_data = ['last_id' => $last_id, 'content_messages' => ''];

    if ( count( $messages ) > 0 ) {
        ob_start();
        foreach ( $messages as $message ) {
            $response_data['last_id'] = $message->id;
            $message_class = ( $message->initiator == $current_user_id ) ? 'sent' : 'replies';
            $avatar_url    = function_exists('projectTheme_get_avatar') ? projectTheme_get_avatar( $message->initiator, 30, 30 ) : '';
            
            update_user_meta($message->initiator, 'lastonline', current_time('timestamp'));
            clean_user_cache($message->initiator);

            // Pass the raw content from the database.
            // It should now contain the full HTML for file previews.
            $display_content = wp_unslash($message->content); 
            
            ?>
            <li class="<?php echo esc_attr($message_class); ?>" data-message-id="<?php echo esc_attr($message->id); ?>">
                <img src="<?php echo esc_url($avatar_url); ?>" width="30" height="30" alt="" />
                <p><?php echo $display_content; // Directly echo the content, JS will process ?></p>
            </li>
            <?php
        }
        $response_data['content_messages'] = ob_get_clean();
    }

    wp_send_json_success( $response_data );
}

/**
 * AJAX handler for searching chat contacts and displaying all threads.
 * This function will now generate the HTML for the contact list.
 */
add_action( 'wp_ajax_get_chat_search', 'pt_handle_get_chat_search' );
function pt_handle_get_chat_search() {
    $current_user_id = get_current_user_id();
    if ( ! $current_user_id ) {
        wp_send_json_error( ['message' => 'User not logged in.'] );
    }

    $search_query = isset( $_GET['get_chat_search'] ) ? sanitize_text_field( $_GET['get_chat_search'] ) : '';
    $current_thid_from_js = isset($_GET['thid']) ? (int)$_GET['thid'] : 0; 

    $chat_instance = new project_chat();
    $threads = $chat_instance->get_all_thread_ids( $current_user_id );
    
    ob_start();

    if ( $threads ) {
        foreach ( $threads as $thread ) {
            $other_user_id = ( $thread->user1 == $current_user_id ) ? $thread->user2 : $thread->user1;
            $other_user_data = get_userdata( $other_user_id );

            if ( ! $other_user_data ) continue;

            clean_user_cache($other_user_id); 
            $online_status_class = function_exists('projecttheme_is_user_online') && projecttheme_is_user_online($other_user_id) ? 'online' : 'offline';
            
            $last_message = $chat_instance->get_last_message_of_thread( $thread->id );
            $last_message_content_preview = '';
            if ($last_message) {
                if ($last_message->file_attached) {
                    $last_message_content_preview = __('File attached', 'pt_livechat');
                } else {
                    // Strip tags from the content, as it might contain embedded JSON markers or HTML
                    $last_message_content_preview = wp_strip_all_tags(wp_unslash($last_message->content));
                    // Remove the JSON markers if they are visible after strip_tags
                    $last_message_content_preview = preg_replace('/__FILE_ATTACHMENT_JSON_START__.*?__FILE_ATTACHMENT_JSON_END__/i', '', $last_message_content_preview);
                }
            }
            $last_message_time = $last_message ? human_time_diff( $last_message->datemade, current_time('timestamp') ) . ' ago' : '';


            $avatar_url = function_exists('projectTheme_get_avatar') ? projectTheme_get_avatar($other_user_id, 40, 40) : '';

            if ( ! empty( $search_query ) && strpos( strtolower( $other_user_data->user_login ), strtolower( $search_query ) ) === false ) {
                continue;
            }

            $active_class = ($thread->id == $current_thid_from_js) ? 'active' : '';

            $unread_count = 0;
            if ($thread->id != $current_thid_from_js) {
                global $wpdb;
                $unread_messages_query = $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}project_pm WHERE threadid = %d AND user = %d AND read_status = 0",
                    $thread->id, $current_user_id
                );
                $unread_count = $wpdb->get_var($unread_messages_query);
            }
            
            ?>
            <li class="contact <?php echo esc_attr($active_class); ?>" data-thid="<?php echo esc_attr($thread->id); ?>" data-to-user="<?php echo esc_attr($other_user_id); ?>">
                <div class="wrap">
                    <span class="contact-status <?php echo esc_attr($online_status_class); ?>"></span>
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="" />
                    <div class="meta">
                        <p class="name"><?php echo esc_html($other_user_data->user_login); ?></p>
                        <p class="preview"><?php echo esc_html($last_message_content_preview); ?></p>
                        <?php if (!empty($last_message_time)): ?><span class="time"><?php echo esc_html($last_message_time); ?></span><?php endif; ?>
                        <?php if ($unread_count > 0): ?>
                            <span class="unread-count"><?php echo esc_html($unread_count); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
            <?php
        }
    } else {
        echo '<li class="contact no-results"><div class="wrap"><p class="name">' . __('No contacts found.', 'pt_livechat') . '</p></div></li>';
    }

    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}


/**
 * Generates a link to the private messaging page for a given user.
 * This is the updated version that handles insufficient connection credits.
 */
function projecttheme_get_pm_link_from_user($current_uid, $uid2)
{
    $pm_page_id = get_option('ProjectTheme_my_account_livechat_id');
    if (empty($pm_page_id)) return '#';
    
    $pm_page_link = get_permalink($pm_page_id);

    if(!is_user_logged_in()) return wp_login_url($pm_page_link);
    if($current_uid == $uid2) return $pm_page_link;

    $chat_instance = new project_chat();
    $thid = $chat_instance->get_thread_id($current_uid, $uid2);

    if ($thid === false) {
        return add_query_arg('chat_error', 'no_connect_credits', $pm_page_link);
    }

    return add_query_arg('thid', $thid, $pm_page_link);
}
