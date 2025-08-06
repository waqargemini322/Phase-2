<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates a Zoom meeting and returns the join URL.
 * This function will also deduct a Zoom invite from the user's count.
 * Exclusively for 'investor' role, but blocked for 'Investor Basic Monthly' tier.
 * The core restriction check is now handled by the new plugin's hook with priority 1.
 *
 * NOTE: This function is being removed/commented out as its functionality
 * has been consolidated into the link2investors-custom-features.php plugin
 * to avoid conflicts and centralize Zoom meeting creation logic.
 */
/*
function l2i_create_zoom_meeting_callback() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'link2investors_create_zoom_meeting_nonce')) {
        error_log('Zoom Integration Error: Security check failed for nonce: ' . ($_POST['nonce'] ?? 'not set'));
        wp_send_json_error('Security check failed. Please refresh the page and try again.', 403);
        wp_die();
    }

    if (!is_user_logged_in()) {
        error_log('Zoom Integration Error: User not logged in to create meeting.');
        wp_send_json_error('You must be logged in to create a Zoom meeting.', 401);
        wp_die();
    }

    $user_id = get_current_user_id();

    // The restriction for Investor Basic Monthly Members is now handled by
    // `l2i_restrict_create_zoom_meeting` in the new plugin with an earlier priority.
    // So, no need for a direct check here.

    $user_role = '';
    // Use the ProjectTheme_mems_get_current_user_role function for consistent role retrieval
    if (function_exists('ProjectTheme_mems_get_current_user_role')) {
        $user_role = ProjectTheme_mems_get_current_user_role($user_id);
    } else {
        // Fallback if the membership plugin's function is not available
        $user_data = wp_get_current_user();
        if ($user_data && !empty($user_data->roles)) {
            $user_role = array_shift($user_data->roles); // Get the primary role
        }
    }

    // --- Role Check: Only 'investor' (non-basic-monthly) can create Zoom meetings ---
    // This check remains as a secondary safeguard, but the primary block is in the new plugin.
    if ($user_role !== 'investor') {
        error_log('Zoom Integration Error: Access denied. User role is ' . $user_role . ', expected investor.');
        wp_send_json_error('Access denied. Only investors are allowed to create Zoom meetings. Your current role is: ' . $user_role, 403);
        wp_die();
    }

    // --- Invite Count Check ---
    $current_invites = (int) get_user_meta($user_id, 'projecttheme_monthly_zoom_invites', true);

    if ($current_invites <= 0) {
        wp_send_json_error('You have no remaining Zoom invites. Please renew your membership or purchase more invites.');
        wp_die();
    }

    // --- Zoom API Credentials (from your previous plugin, now integrated) ---
    // In a real plugin, these should be stored securely (e.g., in WP options, not hardcoded).
    $zoom_account_id    = 'tXUfLbQMQNK88KdpbYqzYA';      // Your Zoom Account ID
    $zoom_client_id     = '6T59zP6gQPCbvQOYuOuYdQ';      // Your Zoom Client ID
    $zoom_client_secret = 'ohUb5iIBUZUoDvLtD2ZxjtGZvJBHW7lH'; // Your Zoom Client Secret
    $zoom_host_user_id  = 'YkyCjGXVTEGlK4_y4lclmQ';    // The Zoom User ID under which meetings will be created

    if (empty($zoom_account_id) || empty($zoom_client_id) || empty($zoom_client_secret) || empty($zoom_host_user_id)) {
        error_log('Zoom Integration Error: Zoom API credentials are not fully configured.');
        wp_send_json_error('Zoom API credentials are not fully configured in the plugin file.', 500);
        wp_die();
    }

    // 1. Get Zoom OAuth Access Token (Client Credentials Grant)
    $token_url = 'https://zoom.us/oauth/token';
    $auth_string = base64_encode($zoom_client_id . ':' . $zoom_client_secret);

    $token_args = array(
        'method'    => 'POST',
        'headers'   => array(
            'Authorization' => 'Basic ' . $auth_string,
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ),
        'body'      => array(
            'grant_type'    => 'account_credentials',
            'account_id'    => $zoom_account_id,
        ),
        'timeout'   => 15, // seconds
    );

    $token_response = wp_remote_post($token_url, $token_args);
    $token_body = wp_remote_retrieve_body($token_response);
    error_log('Zoom Token Response: ' . $token_body); // Log token response for debugging

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


    // 2. Create the Zoom Meeting
    $meeting_url = "https://api.zoom.us/v2/users/{$zoom_host_user_id}/meetings";

    $meeting_payload = array(
        'topic'         => 'Meeting with ' . wp_get_current_user()->display_name, // Dynamic topic
        'type'          => 1, // Instant meeting
        'max_participants' => 2, // Limiting to 2 participants as per your request
        'settings'      => array(
            'host_video'        => true,
            'participant_video' => true,
            'join_before_host'  => true,
            'mute_participants_upon_entry' => false,
            'waiting_room'      => false,
        ),
    );

    $meeting_args = array(
        'method'    => 'POST',
        'headers'   => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
        ),
        'body'      => json_encode($meeting_payload),
        'timeout'   => 15,
    );

    $meeting_response = wp_remote_post($meeting_url, $meeting_args);
    $meeting_body = wp_remote_retrieve_body($meeting_response);
    error_log('Zoom Meeting Creation Response: ' . $meeting_body); // Log meeting creation response

    if (is_wp_error($meeting_response)) {
        wp_send_json_error('Failed to create Zoom meeting (WP Error): ' . $meeting_response->get_error_message(), 500);
        wp_die();
    }

    $meeting_data = json_decode($meeting_body, true);

    if (!isset($meeting_data['join_url'])) {
        wp_send_json_error('Invalid Zoom meeting response: ' . $meeting_body, 500);
        wp_die();
    }

    // --- Deduct invite after successful meeting creation ---
    $new_invites = $current_invites - 1;
    update_user_meta($user_id, 'projecttheme_monthly_zoom_invites', $new_invites);

    // Success! Send the join URL and updated invite count back to the frontend.
    wp_send_json_success(array(
        'join_url' => $meeting_data['join_url'],
        'remaining_invites' => $new_invites
    ));
    wp_die();
}
*/

/**
 * Function to get the current user's Zoom invite count.
 * Used by frontend for real-time updates.
 * The core restriction check is now handled by the new plugin's hook with priority 1.
 *
 * @return int The number of remaining Zoom invites.
 */
function l2i_get_user_zoom_invites($user_id) {
    if (empty($user_id)) {
        return 0;
    }
    // Note: The main AJAX callback `l2i_get_zoom_invites_callback` in the main plugin file
    // already performs the Investor Basic Monthly check via the new restrictions plugin.
    $zoom_invites = (int) get_user_meta($user_id, 'projecttheme_monthly_zoom_invites', true);
    return max(0, $zoom_invites); // Ensure it's not negative
}
