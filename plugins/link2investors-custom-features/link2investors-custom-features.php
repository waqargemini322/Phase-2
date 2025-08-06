<?php
/**
 * Plugin Name: Link2Investors Custom Features
 * Plugin URI:  https://link2investors.com/
 * Description: Custom functionalities for Link2Investors, including connection requests, and a credit-based Zoom invite system.
 * Version:     1.5.7 (Final Cooldown and Text Fix)
 * Author:      Link2Investors Development
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pt-custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// --- Activation Hook to create custom table and add Zoom link columns ---
register_activation_hook( __FILE__, 'pt_custom_activate_plugin' );
function pt_custom_activate_plugin() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pt_connections_requests';
    $charset_collate = $wpdb->get_charset_collate();

    // SQL for pt_connections_requests table (existing)
    $sql_connections = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        sender_id BIGINT(20) UNSIGNED NOT NULL,
        receiver_id BIGINT(20) UNSIGNED NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        date_created DATETIME NOT NULL,
        date_updated DATETIME NULL,
        PRIMARY KEY (`id`), KEY `sender_id` (`sender_id`), KEY `receiver_id` (`receiver_id`)
    ) $charset_collate;";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_connections );

    // Add zoom_link_url and zoom_link_timestamp to project_pm_threads table
    $threads_table_name = $wpdb->prefix . 'project_pm_threads';
    
    $column_exists_url = $wpdb->query("SHOW COLUMNS FROM `$threads_table_name` LIKE 'zoom_link_url'");
    $column_exists_timestamp = $wpdb->query("SHOW COLUMNS FROM `$threads_table_name` LIKE 'zoom_link_timestamp'");

    if (!$column_exists_url) {
        $wpdb->query("ALTER TABLE `$threads_table_name` ADD COLUMN `zoom_link_url` TEXT NULL");
    }
    if (!$column_exists_timestamp) {
        $wpdb->query("ALTER TABLE `$threads_table_name` ADD COLUMN `zoom_link_timestamp` BIGINT(20) NULL");
    }
}

// --- Enqueue Scripts and Styles ---
add_action( 'wp_enqueue_scripts', 'pt_custom_enqueue_scripts' );
function pt_custom_enqueue_scripts() {
    wp_enqueue_script( 'pt-custom-script', plugin_dir_url( __FILE__ ) . 'assets/js/pt-custom-script.js', array( 'jquery' ), '1.5.7', true );
    wp_localize_script( 'pt-custom-script', 'pt_ajax_obj', array(
        'ajax_url'                => admin_url( 'admin-ajax.php' ),
        'send_connection_nonce'   => wp_create_nonce( 'pt_send_connection_nonce' ),
        'connection_action_nonce' => wp_create_nonce( 'pt_connection_action_nonce' ),
        'zoom_meeting_nonce'      => wp_create_nonce( 'link2investors_zoom_nonce' ),
        'all_credits_nonce'       => wp_create_nonce( 'l2i_all_credits_nonce' ),
    ) );
    wp_enqueue_style( 'pt-custom-style', plugin_dir_url( __FILE__ ) . 'assets/css/pt-custom-style.css', array(), '1.0.0', 'all', 999 );
}


//======================================================================
// 1. CREDIT MANAGEMENT HELPER FUNCTIONS
//======================================================================
function pt_get_connect_credits( $user_id ) {
    clean_user_cache( $user_id );
    return (int) get_user_meta( $user_id, 'pt_connect_credits', true );
}

function pt_deduct_connect_credits( $user_id, $amount = 1 ) {
    $current_credits = pt_get_connect_credits( $user_id );
    if ( $current_credits >= $amount ) {
        $new_credits = $current_credits - $amount;
        update_user_meta( $user_id, 'pt_connect_credits', $new_credits );
        clean_user_cache( $user_id );
        return true;
    }
    return false;
}

function pt_get_invite_credits( $user_id ) {
    clean_user_cache( $user_id );
    return (int) get_user_meta( $user_id, 'projecttheme_monthly_zoom_invites', true );
}

function pt_deduct_invite_credits( $user_id, $amount = 1 ) {
    $current_credits = pt_get_invite_credits( $user_id );
    if ( $current_credits >= $amount ) {
        $new_credits = $current_credits - $amount;
        update_user_meta( $user_id, 'projecttheme_monthly_zoom_invites', $new_credits );
        clean_user_cache( $user_id );
        return true;
    }
    return false;
}

function pt_get_bid_credits( $user_id ) {
    clean_user_cache( $user_id );
    return (int) get_user_meta( $user_id, 'projectTheme_monthly_nr_of_bids', true );
}


//======================================================================
// 2. ZOOM INVITE SYSTEM
//======================================================================
add_action('wp_ajax_l2i_get_zoom_invites', 'l2i_ajax_get_zoom_invites_callback');
function l2i_ajax_get_zoom_invites_callback() {
    if ( !is_user_logged_in() || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'link2investors_zoom_nonce') ) {
        wp_send_json_error( ['message' => 'Security check failed.'] );
    }
    wp_send_json_success(['invites' => pt_get_invite_credits(get_current_user_id())]);
}

add_action('wp_ajax_l2i_get_all_credits', 'l2i_ajax_get_all_credits_callback');
function l2i_ajax_get_all_credits_callback() {
    if ( !is_user_logged_in() || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'l2i_all_credits_nonce') ) {
        wp_send_json_error( ['message' => 'Security check failed.'] );
    }
    $user_id = get_current_user_id();
    wp_send_json_success([
        'bids' => pt_get_bid_credits($user_id),
        'connects' => pt_get_connect_credits($user_id),
        'invites' => pt_get_invite_credits($user_id)
    ]);
}


add_action( 'wp_ajax_link2investors_create_zoom_meeting', 'link2investors_create_zoom_meeting_callback' );
function link2investors_create_zoom_meeting_callback() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'link2investors_zoom_nonce' ) ) {
        wp_send_json_error( ['message' => 'Security check failed.'] );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( ['message' => 'You must be logged in.'] );
    }
    $current_user_id = get_current_user_id();
    $to_user_id = isset( $_POST['to_user'] ) ? intval( $_POST['to_user'] ) : 0;
    $thread_id = isset( $_POST['thid'] ) ? intval( $_POST['thid'] ) : 0;
    if ( $to_user_id === 0 || $thread_id === 0 ) {
        wp_send_json_error( ['message' => 'Invalid recipient or thread ID.'] );
    }
    if (!class_exists('project_chat')) {
        $chat_class_path = WP_PLUGIN_DIR . '/ProjectTheme_livechat/chat-regular.class.php';
        if (file_exists($chat_class_path)) {
            require_once $chat_class_path;
        } else {
            wp_send_json_error(['message' => 'Chat class not found.']);
        }
    }
    $chat_instance = new project_chat($thread_id);
    $thread_info = $chat_instance->get_single_thread_info($thread_id);
    $existing_zoom_url = $thread_info->zoom_link_url ?? null;
    $existing_zoom_timestamp = (int)($thread_info->zoom_link_timestamp ?? 0);
    $cooldown_period = 3600; // 1 hour
    $current_time = current_time('timestamp');

    if ($existing_zoom_url && ($current_time - $existing_zoom_timestamp) < $cooldown_period) {
        // A valid link exists. Resend the message with remaining time in minutes only.
        $time_left_seconds = $cooldown_period - ($current_time - $existing_zoom_timestamp);
        $minutes = ceil($time_left_seconds / 60);
        $minutes_text = ($minutes == 1) ? 'minute' : 'minutes';
        
        // Create the new time string without "approximately"
        $time_left_string = sprintf('%d %s', $minutes, $minutes_text);

        $chat_message_content = "A Zoom meeting is currently active. Join Here: <a href=\"" . esc_url($existing_zoom_url) . "\" target=\"_blank\">Join Video Meeting</a>. This link will expire in " . $time_left_string . ".";
        
        $message_inserted = $chat_instance->insert_message($current_user_id, $to_user_id, $chat_message_content);

        if (!$message_inserted) {
            error_log('link2investors_create_zoom_meeting: Failed to insert reused link message for thread ' . $thread_id);
        }

        wp_send_json_success([
            'message' => 'A meeting is already active. The link has been sent again with expiry time.',
            'meeting_link' => esc_url($existing_zoom_url),
            'remaining_invites' => pt_get_invite_credits($current_user_id),
            'reused_link' => true,
            'cooldown_remaining' => $time_left_seconds
        ]);
        wp_die();
    }

    // If no valid link exists, proceed to create a new one
    $current_invites = pt_get_invite_credits( $current_user_id );
    if ( $current_invites <= 0 ) {
        wp_send_json_error( ['message' => 'You do not have any Zoom Invite credits left.'] );
    }

    // Zoom API Credentials
    $zoom_account_id = get_option('vczapi_oauth_account_id');
    $zoom_client_id = get_option('vczapi_oauth_client_id');
    $zoom_client_secret = get_option('vczapi_oauth_client_secret');
    $zoom_host_user_id = get_option('vczapi_zoom_host_user_id', 'YkyCjGXVTEGlK4_y4lclmQ');
    if (empty($zoom_account_id) || empty($zoom_client_id) || empty($zoom_client_secret) || empty($zoom_host_user_id)) {
        wp_send_json_error('Zoom API credentials are not fully configured.', 500);
        wp_die();
    }
    $access_token = get_transient('zoom_s2s_access_token');
    if ( false === $access_token ) {
        $token_url = 'https://zoom.us/oauth/token';
        $auth_string = base64_encode($zoom_client_id . ':' . $zoom_client_secret);
        $token_args = array( 'method' => 'POST', 'headers' => array( 'Authorization' => 'Basic ' . $auth_string, 'Content-Type'  => 'application/x-www-form-urlencoded', ), 'body' => array( 'grant_type' => 'account_credentials', 'account_id' => $zoom_account_id, ), 'timeout' => 15, );
        $token_response = wp_remote_post($token_url, $token_args);
        $token_body = wp_remote_retrieve_body($token_response);
        if (is_wp_error($token_response)) {
            wp_send_json_error('Failed to get Zoom access token (WP Error): ' . $token_response->get_error_message(), 500);
            wp_die();
        }
        $token_data = json_decode($token_body, true);
        if (!isset($token_data['access_token'])) {
            wp_send_json_error('Invalid Zoom access token response: ' . $token_body, 500);
            wp_die();
        }
        $access_token = $token_data['access_token'];
        set_transient('zoom_s2s_access_token', $access_token, 3500);
    }
    $meeting_url = "https://api.zoom.us/v2/users/{$zoom_host_user_id}/meetings";
    $meeting_payload = array( 'topic' => 'Meeting with ' . wp_get_current_user()->display_name, 'type' => 1, 'settings' => array( 'host_video' => true, 'participant_video' => true, 'join_before_host'  => true, 'mute_participants_upon_entry' => false, 'waiting_room' => false, ), );
    $meeting_args = array( 'method' => 'POST', 'headers' => array( 'Authorization' => 'Bearer ' . $access_token, 'Content-Type'  => 'application/json', ), 'body' => json_encode($meeting_payload), 'timeout' => 15, );
    $meeting_response = wp_remote_post($meeting_url, $meeting_args);
    $meeting_body = wp_remote_retrieve_body($meeting_response);
    if (is_wp_error($meeting_response)) {
        wp_send_json_error('Failed to create Zoom meeting (WP Error): ' . $meeting_response->get_error_message(), 500);
        wp_die();
    }
    $meeting_data = json_decode($meeting_body, true);
    if (!isset($meeting_data['join_url'])) {
        wp_send_json_error('Invalid Zoom meeting response: ' . $meeting_body, 500);
        wp_die();
    }
    
    $deduction_success = pt_deduct_invite_credits($current_user_id, 1);
    if (!$deduction_success) {
        wp_send_json_error(['message' => 'Failed to deduct Zoom invite. Please try again.']);
    }
    $new_remaining_invites = $current_invites - 1;
    $new_zoom_link = $meeting_data['join_url'];
    $chat_instance->update_zoom_link_in_thread($thread_id, $new_zoom_link, $current_time);

    $chat_message_content = "A Zoom meeting has been initiated! Join here: <a href=\"" . esc_url($new_zoom_link) . "\" target=\"_blank\">Join Video Meeting</a>. This link will expire in 60 minutes.";
    $message_inserted = $chat_instance->insert_message($current_user_id, $to_user_id, $chat_message_content);
    if (!$message_inserted) {
        error_log('link2investors_create_zoom_meeting: Failed to insert new link message for thread ' . $thread_id);
    }
    
    wp_send_json_success([
        'message' => 'Zoom meeting created!',
        'meeting_link' => esc_url($new_zoom_link),
        'remaining_invites' => $new_remaining_invites,
        'reused_link' => false,
        'cooldown_remaining' => $cooldown_period
    ]);
    wp_die();
}


//======================================================================
// 3. CONNECTION REQUEST SYSTEM
//======================================================================
add_action( 'wp_ajax_pt_send_connection_request', 'pt_ajax_send_connection_request' );
function pt_ajax_send_connection_request() {
    check_ajax_referer( 'pt_send_connection_nonce', '_wpnonce' );
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'User not logged in.' );
    }
    $sender_id = get_current_user_id();
    $receiver_id = isset( $_POST['receiver_id'] ) ? intval( $_POST['receiver_id'] ) : 0;
    if ( $sender_id === $receiver_id || $receiver_id === 0 ) {
        wp_send_json_error( 'Invalid recipient.' );
    }
    if ( ! pt_deduct_connect_credits( $sender_id, 1 ) ) {
        wp_send_json_error( 'You do not have enough Connection Credits.' );
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'pt_connections_requests';
    $inserted = $wpdb->insert(
        $table_name,
        ['sender_id' => $sender_id, 'receiver_id' => $receiver_id, 'status' => 'pending', 'date_created' => current_time( 'mysql' )]
    );
    if ($inserted) {
        wp_send_json_success( 'Connection request sent!' );
    } else {
        wp_send_json_error('Failed to send connection request. Database error.');
    }
}
add_shortcode( 'pt_connection_button', 'pt_connection_button_shortcode' );
function pt_connection_button_shortcode( $atts ) {
    $atts = shortcode_atts( array('user_id' => 0), $atts, 'pt_connection_button' );
    $receiver_id = intval( $atts['user_id'] );
    $current_user_id = get_current_user_id();
    if ( ! is_user_logged_in() || $current_user_id === $receiver_id || $receiver_id === 0 ) return '';
    global $wpdb;
    $table_name = $wpdb->prefix . 'pt_connections_requests';
    $existing_request = $wpdb->get_row( $wpdb->prepare(
        "SELECT status FROM $table_name WHERE (sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d)",
        $current_user_id, $receiver_id, $receiver_id, $current_user_id
    ) );
    if ( $existing_request ) {
        if ( $existing_request->status === 'pending' ) return '<button disabled>Request Pending</button>';
        if ( $existing_request->status === 'accepted' ) return '<button disabled>Connected</button>';
    }
    return '<button id="pt-send-connection-request" data-receiver-id="'.esc_attr($receiver_id).'">Send Connection Request</button>';
}


//======================================================================
// 4. ADMIN USER PROFILE FIELDS
//======================================================================
add_action( 'show_user_profile', 'pt_custom_user_profile_fields' );
add_action( 'edit_user_profile', 'pt_custom_user_profile_fields' );
function pt_custom_user_profile_fields( $user ) {
    ?>
    <h3><?php _e( 'Link2Investors Credits', 'pt-custom' ); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="pt_bid_credits"><?php _e( 'Bid Credits', 'pt-custom' ); ?></label></th>
            <td><input type="number" name="pt_bid_credits" id="pt_bid_credits" value="<?php echo esc_attr( pt_get_bid_credits( $user->ID ) ); ?>" class="regular-text" min="0" /></td>
        </tr>
        <tr>
            <th><label for="pt_connect_credits"><?php _e( 'Connection Credits', 'pt-custom' ); ?></label></th>
            <td><input type="number" name="pt_connect_credits" id="pt_connect_credits" value="<?php echo esc_attr( pt_get_connect_credits( $user->ID ) ); ?>" class="regular-text" min="0" /></td>
        </tr>
        <tr>
            <th><label for="pt_invite_credits"><?php _e( 'INVITE Credits (Zoom)', 'pt-custom' ); ?></label></th>
            <td><input type="number" name="pt_invite_credits" id="pt_invite_credits" value="<?php echo esc_attr( pt_get_invite_credits( $user->ID ) ); ?>" class="regular-text" min="0" /></td>
        </tr>
    </table>
    <?php
}
add_action( 'personal_options_update', 'pt_custom_save_user_profile_fields' );
add_action( 'edit_user_profile_update', 'pt_custom_save_user_profile_fields' );
function pt_custom_save_user_profile_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) return false;
    if ( isset( $_POST['pt_bid_credits'] ) ) update_user_meta( $user_id, 'projectTheme_monthly_nr_of_bids', absint( $_POST['pt_bid_credits'] ) );
    if ( isset( $_POST['pt_connect_credits'] ) ) update_user_meta( $user_id, 'pt_connect_credits', absint( $_POST['pt_connect_credits'] ) );
    if ( isset( $_POST['pt_invite_credits'] ) ) update_user_meta( $user_id, 'projecttheme_monthly_zoom_invites', absint( $_POST['pt_invite_credits'] ) );
}
