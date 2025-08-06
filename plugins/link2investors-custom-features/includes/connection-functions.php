<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles sending a connection request.
 * The restriction check is now handled by the new plugin's hook with priority 1.
 */
function l2i_handle_send_connection_request() {
    // Security check: nonce verification
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'pt_send_connection_request_nonce')) {
        wp_send_json_error('Security check failed.', 403);
    }

    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to send a connection request.', 401);
    }

    $sender_id = get_current_user_id();

    // The restriction for Investor Basic Monthly Members is now handled by
    // `l2i_restrict_send_connection_request` in the new plugin with an earlier priority.
    // So, no need for a direct check here.

    $receiver_id = isset($_POST['receiver_id']) ? (int) $_POST['receiver_id'] : 0;

    // Validate receiver ID
    if ($receiver_id <= 0 || $sender_id === $receiver_id) {
        wp_send_json_error('Invalid recipient for connection request.', 400);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'l2i_connection_requests';

    // Check if request already exists (pending or accepted)
    $existing_request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE (sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d) AND status IN ('pending', 'accepted')",
        $sender_id, $receiver_id, $receiver_id, $sender_id
    ));

    if ($existing_request) {
        if ($existing_request->status === 'pending') {
            wp_send_json_error('A connection request is already pending with this user.', 409);
        } else { // 'accepted'
            wp_send_json_error('You are already connected with this user.', 409);
        }
    }

    // Deduct CONNECTS credit (assuming this logic is in ProjectTheme or similar)
    // Placeholder: In a real scenario, you'd call a function from your main theme/plugin
    // that manages credits for 'connects'.
    // Example: if (! ProjectTheme_deduct_connect_credit($sender_id)) {
    //     wp_send_json_error('Insufficient CONNECTS credits to send request.', 403);
    // }

    $inserted = $wpdb->insert(
        $table_name,
        array(
            'sender_id'   => $sender_id,
            'receiver_id' => $receiver_id,
            'status'      => 'pending',
            'request_date' => current_time('mysql'),
        ),
        array('%d', '%d', '%s', '%s')
    );

    if ($inserted) {
        wp_send_json_success('Connection request sent successfully!');
    } else {
        // If insert fails, refund credits (important!) - if credit deduction was implemented here
        // update_user_meta( $sender_id, get_option( 'pt_connect_credits_meta_key' ), pt_get_connect_credits( $sender_id ) + $credits_needed );
        wp_send_json_error('Failed to send connection request. Database error.', 500);
    }
}

/**
 * Handles accepting or rejecting a connection request.
 * The restriction check is now handled by the new plugin's hook with priority 1.
 */
function l2i_handle_connection_action() {
    // Security check: nonce verification
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'pt_handle_connection_action_nonce')) {
        wp_send_json_error('Security check failed.', 403);
    }

    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to perform this action.', 401);
    }

    $receiver_id = get_current_user_id(); // The current user is the receiver

    // The restriction for Investor Basic Monthly Members is now handled by
    // `l2i_restrict_handle_connection_action` in the new plugin with an earlier priority.
    // So, no need for a direct check here.

    $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
    $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : ''; // 'accept' or 'reject'

    // Validate inputs
    if ($request_id <= 0 || !in_array($action_type, array('accept', 'reject'))) {
        wp_send_json_error('Invalid request or action type.', 400);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'l2i_connection_requests';

    // Fetch the request to ensure it belongs to the current user as receiver and is pending
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d AND receiver_id = %d AND status = 'pending'",
        $request_id, $receiver_id
    ));

    if (!$request) {
        wp_send_json_error('Connection request not found or already processed.', 404);
    }

    $new_status = ($action_type === 'accept') ? 'accepted' : 'rejected';

    $updated = $wpdb->update(
        $table_name,
        array(
            'status'        => $new_status,
            'response_date' => current_time('mysql'),
        ),
        array('id' => $request_id),
        array('%s', '%s'),
        array('%d')
    );

    if ($updated !== false) {
        wp_send_json_success('Connection request ' . $new_status . ' successfully!');
    } else {
        wp_send_json_error('Failed to update connection request. Database error.', 500);
    }
}

/**
 * Checks if two users are connected.
 *
 * @param int $user1_id
 * @param int $user2_id
 * @return bool True if connected, false otherwise.
 */
function l2i_are_users_connected($user1_id, $user2_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'l2i_connection_requests';

    $connected = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d)) AND status = 'accepted'",
        $user1_id, $user2_id, $user2_id, $user1_id
    ));

    return (bool) $connected;
}

/**
 * Checks if a connection request is pending between two users.
 *
 * @param int $user1_id
 * @param int $user2_id
 * @return bool True if pending, false otherwise.
 */
function l2i_is_connection_pending($user1_id, $user2_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'l2i_connection_requests';

    $pending = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d)) AND status = 'pending'",
        $user1_id, $user2_id, $user2_id, $user1_id
    ));

    return (bool) $pending;
}

/**
 * Get pending incoming connection requests for a user.
 *
 * @param int $user_id The ID of the user.
 * @return array An array of request objects.
 */
function l2i_get_pending_incoming_requests($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'l2i_connection_requests';

    $requests = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE receiver_id = %d AND status = 'pending' ORDER BY request_date DESC",
        $user_id
    ));

    return $requests;
}

/**
 * Get pending sent connection requests by a user.
 *
 * @param int $user_id The ID of the user.
 * @return array An array of request objects.
 */
function l2i_get_pending_sent_requests($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'l2i_connection_requests';

    $requests = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE sender_id = %d AND status = 'pending' ORDER BY request_date DESC",
        $user_id
    ));

    return $requests;
}