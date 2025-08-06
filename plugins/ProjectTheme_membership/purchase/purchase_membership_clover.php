<?php
/**
 * ProjectTheme - Clover Membership Payment Gateway
 * This file handles the redirection to Clover for membership payments
 * and processes the success/failure response.
 * It has been updated to support 'investor' membership type and monthly/yearly plans.
 */

// Ensure WordPress environment is loaded
if(!defined('ABSPATH')) {
    $wp_load_path = dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
    } else {
        die('WordPress environment not loaded. Cannot proceed.');
    }
}

// Ensure user is logged in
if(!is_user_logged_in()) {
    wp_redirect(home_url().'/wp-login.php?action=register');
    exit;
}

global $current_user;
get_currentuserinfo();
$uid = $current_user->ID;

// Handle the initial redirect to Clover payment page (Unified action)
if (isset($_GET['p_action']) && $_GET['p_action'] == "purchase_membership_clover_unified") {
    $memid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $mem_type = isset($_GET['mem_type']) ? sanitize_text_field($_GET['mem_type']) : '';
    $plan_type = isset($_GET['plan_type']) ? sanitize_text_field($_GET['plan_type']) : 'monthly'; // Default to 'monthly'

    if ($memid < 1 || $memid > 6 || empty($mem_type)) {
        echo 'oops, error, invalid membership selection or type';
        exit;
    }

    $package_name = '';
    $total = 0;
    $membership_duration_raw = 0; // Raw duration from options (months or years)

    // Determine package details based on mem_type and plan_type
    switch ($mem_type) {
        case 'project_owner':
            $package_name = get_option('pt_project_owner_membership_name_' . $memid);
            $total = get_option('pt_project_owner_membership_cost_' . $memid);
            $membership_duration_raw = get_option('pt_project_owner_membership_time_' . $memid);
            break;
        case 'investor':
            if ($plan_type == 'yearly') {
                $package_name = get_option('pt_investor_membership_name_yearly_' . $memid);
                $total = get_option('pt_investor_membership_cost_yearly_' . $memid);
                $membership_duration_raw = get_option('pt_investor_membership_time_yearly_' . $memid); // In years
            } else { // monthly
                $package_name = get_option('pt_investor_membership_name_' . $memid);
                $total = get_option('pt_investor_membership_cost_' . $memid);
                $membership_duration_raw = get_option('pt_investor_membership_time_' . $memid); // In months
            }
            break;
        case 'service_provider': // Assuming service_provider might also use Clover
            $package_name = get_option('pt_freelancer_membership_name_' . $memid);
            $total = get_option('pt_freelancer_membership_cost_' . $memid);
            $membership_duration_raw = get_option('pt_freelancer_membership_time_' . $memid);
            break;
        default:
            echo 'oops, error, unsupported membership type.';
            exit;
    }

    if ($total === false || $total <= 0 || empty($package_name)) { // Check for non-positive cost
        echo 'oops, error, membership cost not found or is zero for selected package. Please ensure you are not trying to pay for a free plan via Clover.';
        exit;
    }

    // Clover API credentials (ensure these are set in your theme options)
    $clover_api_key = get_option('ProjectTheme_clover_api_key');
    $clover_merchant_id = get_option('ProjectTheme_clover_merchant_id'); // If needed for your specific Clover integration
    $clover_app_id = get_option('ProjectTheme_clover_app_id'); // If needed for your specific Clover integration
    $clover_environment = get_option('ProjectTheme_clover_environment', 'sandbox'); // 'sandbox' or 'production'

    if (empty($clover_api_key)) { // Only require API key for this simple redirect
        echo 'Clover API key is not configured. Please set it in the admin panel.';
        exit;
    }

    // Generate a unique order ID for Clover
    $order_id = 'MEM-' . $uid . '-' . $memid . '-' . time();

    // Prepare the custom data to pass through Clover redirect (for callback)
    $custom_data = json_encode([
        'mem_id' => $memid,
        'uid' => $uid,
        'mem_type' => $mem_type,
        'plan_type' => $plan_type,
        'order_id' => $order_id,
        'amount' => $total, // Store original amount for verification
        'timestamp' => current_time('timestamp'),
    ]);

    // Encode custom data for URL
    $encoded_custom_data = urlencode(base64_encode($custom_data));

    // Construct the Clover payment URL
    // IMPORTANT: This is a simplified example for direct payment.
    // In a real production environment, you would typically make a server-side API call to Clover
    // to create an order and receive a hosted checkout URL, then redirect the user to that URL.
    // The 'amount' parameter for Clover usually expects cents, so multiply by 100
    $clover_payment_url = "https://www.clover.com/pay?amount=" . (round($total * 100)) . "&api_key=" . esc_attr($clover_api_key) . "&redirect_url=" . esc_url(get_bloginfo('siteurl') . '/?p_action=clover_payment_success') . "&state=" . $encoded_custom_data;

    wp_redirect($clover_payment_url);
    exit;
}

// Handle Clover payment success callback
if (isset($_GET['p_action']) && $_GET['p_action'] == "clover_payment_success") {
    $payment_id = isset($_GET['payment_id']) ? sanitize_text_field($_GET['payment_id']) : '';
    $state_encoded = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';

    // Decode the state parameter
    $custom_data_decoded = base64_decode(urldecode($state_encoded));
    $received_data = json_decode($custom_data_decoded, true);

    $memid          = isset($received_data['mem_id']) ? (int)$received_data['mem_id'] : 0;
    $uid            = isset($received_data['uid']) ? (int)$received_data['uid'] : 0;
    $mem_type       = isset($received_data['mem_type']) ? sanitize_text_field($received_data['mem_type']) : '';
    $plan_type      = isset($received_data['plan_type']) ? sanitize_text_field($received_data['plan_type']) : 'monthly';
    $order_id       = isset($received_data['order_id']) ? sanitize_text_field($received_data['order_id']) : '';
    $expected_amount = isset($received_data['amount']) ? floatval($received_data['amount']) : 0;
    $timestamp      = isset($received_data['timestamp']) ? intval($received_data['timestamp']) : 0;


    // Basic validation of received data
    if (empty($payment_id) || $memid < 1 || empty($mem_type) || $uid == 0 || empty($order_id)) {
        error_log("Clover Callback Error: Invalid or missing parameters. Payment ID: " . $payment_id . ", State: " . $state_encoded);
        wp_redirect(get_permalink(get_option('ProjectTheme_my_account_page_id')) . "?payment_error=invalid_clover_callback_params");
        exit;
    }

    // Verify user ID if current user is logged in and matches
    if (is_user_logged_in() && get_current_user_id() !== $uid) {
        error_log("Clover Callback Error: User ID mismatch. Expected " . $uid . ", Got " . get_current_user_id());
        wp_redirect(get_permalink(get_option('ProjectTheme_my_account_page_id')) . "?payment_error=clover_user_id_mismatch");
        exit;
    }

    $clover_api_key = get_option('ProjectTheme_clover_api_key');
    if (empty($clover_api_key)) {
        error_log("Clover Callback Error: API key missing.");
        wp_redirect(get_permalink(get_option('ProjectTheme_my_account_page_id')) . "?payment_error=clover_api_key_missing");
        exit;
    }

    // Check if this payment has already been processed to prevent double processing
    $processed_flag_meta_key = 'clover_membership_processed_' . $order_id; // Use order_id for unique tracking
    $is_processed = get_user_meta($uid, $processed_flag_meta_key, true);

    if (!empty($is_processed)) {
        error_log("Clover Callback Info: Payment for order ID {$order_id} already processed for user {$uid}.");
        wp_redirect(get_permalink(get_option('ProjectTheme_my_account_page_id')) . "?payment_status=success"); // Already processed, redirect to success
        exit;
    }

    // Call Clover API to verify payment (SERVER-SIDE API call is highly recommended for security)
    // This example uses a simplified direct API call.
    $api_url = "https://api.clover.com/v3/payments/" . esc_attr($payment_id) . "?access_token=" . esc_attr($clover_api_key); // Use access_token for API calls
    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        error_log("Clover Payment Verification Error: " . $response->get_error_message());
        wp_redirect(get_permalink(get_option('ProjectTheme_my_account_page_id')) . "?payment_error=clover_verification_failed");
        exit;
    }

    $payment_data = json_decode(wp_remote_retrieve_body($response), true);

    // Verify payment status and amount
    // You might need to adjust 'status' field based on actual Clover API response.
    // Also, verify the amount to prevent tampering.
    $received_amount_cents = isset($payment_data['amount']) ? (int)$payment_data['amount'] : 0;
    $expected_amount_cents = round($expected_amount * 100);

    if (isset($payment_data['result']) && $payment_data['result'] == 'SUCCESS' && $received_amount_cents == $expected_amount_cents) {
        // Payment successful, update membership
        $membership_duration = 0;
        $membership_items = 0; // projects or bids
        $membership_name = '';

        // Determine which options to fetch based on mem_type and plan_type
        switch ($mem_type) {
            case 'project_owner':
                $membership_duration = get_option('pt_project_owner_membership_time_' . $memid);
                $membership_items = get_option('pt_project_owner_membership_projects_' . $memid);
                $membership_name = get_option('pt_project_owner_membership_name_' . $memid);
                break;
            case 'investor':
                if ($plan_type == 'yearly') {
                    $membership_duration = get_option('pt_investor_membership_time_yearly_' . $memid); // In years
                    $membership_items = get_option('pt_investor_membership_bids_yearly_' . $memid);
                    $membership_name = get_option('pt_investor_membership_name_yearly_' . $memid);
                } else { // monthly
                    $membership_duration = get_option('pt_investor_membership_time_' . $memid); // In months
                    $membership_items = get_option('pt_investor_membership_bids_' . $memid);
                    $membership_name = get_option('pt_investor_membership_name_' . $memid);
                }
                break;
            case 'service_provider':
                $membership_duration = get_option('pt_freelancer_membership_time_' . $memid);
                $membership_items = get_option('pt_freelancer_membership_bids_' . $memid);
                $membership_name = get_option('pt_freelancer_membership_name_' . $memid);
                break;
        }

        if ($membership_duration === false || $membership_items === false) {
            error_log("Clover Payment Success: Membership details not found for mem_id {$memid}, type {$mem_type}, plan_type {$plan_type}.");
            wp_redirect(get_permalink(get_option('ProjectTheme_my_account_page_id')) . "?payment_error=membership_details_missing");
            exit;
        }

        // Calculate new expiry time based on retrieved duration and plan type
        $duration_in_seconds = 0;
        if ($mem_type == 'investor' && $plan_type == 'yearly') {
            $duration_in_seconds = $membership_duration * 365 * 24 * 3600; // Years to seconds
        } else {
            $duration_in_seconds = $membership_duration * 30.5 * 24 * 3600; // Months to seconds (approx)
        }

        update_user_meta($uid, 'membership_available', current_time('timestamp') + $duration_in_seconds);
        update_user_meta($uid, 'mem_type', $membership_name); // Update user's active membership type

        // Update projects or bids based on membership type
        if ($mem_type == 'project_owner') {
            update_user_meta($uid, 'projectTheme_monthly_nr_of_projects', $membership_items);
            update_user_meta($uid, "projecttheme_monthly_zoom_invites", 0); // Explicitly set to 0
            update_user_meta($uid, "pt_connect_credits", 0); // Explicitly set to 0
        } elseif ($mem_type == 'investor' || $mem_type == 'service_provider') {
            update_user_meta($uid, 'projectTheme_monthly_nr_of_bids', $membership_items);
            // For investor, bids are also zoom invites
            if ($mem_type == 'investor') {
                update_user_meta($uid, "projecttheme_monthly_zoom_invites", $membership_items);
            } else {
                update_user_meta($uid, "projecttheme_monthly_zoom_invites", 0); // Freelancer/Service Provider gets 0 zoom invites
            }
            update_user_meta($uid, "pt_connect_credits", 0); // Explicitly set to 0
        }

        // Mark as processed to prevent re-processing
        update_user_meta($uid, $processed_flag_meta_key, 'yes');

        // Redirect to success page
        wp_redirect(get_permalink(get_option('ProjectTheme_my_account_page_id')) . "?payment_status=success");
        exit;
    } else {
        error_log("Clover Payment Failed: Payment ID {$payment_id}, Result: " . (isset($payment_data['result']) ? $payment_data['result'] : 'Unknown') . ", Amount Mismatch: Received {$received_amount_cents}, Expected {$expected_amount_cents}.");
        wp_redirect(get_permalink(get_option('ProjectTheme_my_account_page_id')) . "?payment_error=clover_payment_failed");
        exit;
    }
}
?>
