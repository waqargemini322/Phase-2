<?php
/**
 * ProjectTheme - Unified Clover Payment Initiation Page
 * This file initiates a Clover payment for any selected membership plan.
 * It expects 'id', 'mem_type', and optionally 'plan_type' as GET parameters.
 */

// Ensure WordPress environment is loaded
if (!defined('ABSPATH')) {
    $wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
    } else {
        die('WordPress environment not loaded. Cannot proceed.');
    }
}

// Redirect to login if user is not logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit();
}

global $current_user;
get_currentuserinfo();
$uid = $current_user->ID;

// Get membership parameters from GET
$pack_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mem_type = isset($_GET['mem_type']) ? sanitize_text_field($_GET['mem_type']) : '';
$plan_type = isset($_GET['plan_type']) ? sanitize_text_field($_GET['plan_type']) : 'monthly'; // Default to 'monthly'

// Validate pack_id
if ($pack_id == 0 || empty($mem_type)) {
    wp_redirect(get_bloginfo("siteurl") . "/?p_action=get_new_mem"); // Redirect to membership selection if invalid ID or type
    exit();
}

// Initialize membership details for cost retrieval
$membership_cost = 0;
$membership_name = '';

// Dynamically retrieve membership cost and name based on mem_type and plan_type
switch ($mem_type) {
    case 'project_owner':
        $membership_cost = get_option("pt_project_owner_membership_cost_" . $pack_id);
        $membership_name = get_option("pt_project_owner_membership_name_" . $pack_id);
        break;
    case 'investor':
        if ($plan_type == 'yearly') {
            $membership_cost = get_option("pt_investor_membership_cost_yearly_" . $pack_id);
            $membership_name = get_option("pt_investor_membership_name_yearly_" . $pack_id);
        } else { // monthly
            $membership_cost = get_option("pt_investor_membership_cost_" . $pack_id);
            $membership_name = get_option("pt_investor_membership_name_" . $pack_id);
        }
        break;
    case 'service_provider':
        $membership_cost = get_option("pt_freelancer_membership_cost_" . $pack_id);
        $membership_name = get_option("pt_freelancer_membership_name_" . $pack_id);
        break;
    default:
        // Invalid mem_type, redirect
        wp_redirect(get_bloginfo("siteurl") . "/?p_action=get_new_mem");
        exit();
}

// If cost is 0 or not found, redirect to prevent payment for free plans or invalid plans
if ($membership_cost === false || $membership_cost <= 0) {
    wp_redirect(get_bloginfo("siteurl") . "/?p_action=get_new_mem");
    exit();
}

// --- Clover API Credentials (Replace with your actual values) ---
// It's better to store these in WordPress options and retrieve them here.
$clover_api_key = get_option('ProjectTheme_clover_api_key'); // Example: 'YOUR_CLOVER_API_KEY'
$clover_merchant_id = get_option('ProjectTheme_clover_merchant_id'); // Example: 'YOUR_CLOVER_MERCHANT_ID'
$clover_app_id = get_option('ProjectTheme_clover_app_id'); // Example: 'YOUR_CLOVER_APP_ID'
$clover_environment = get_option('ProjectTheme_clover_environment', 'sandbox'); // 'sandbox' or 'production'

// Determine Clover API URL based on environment
$clover_api_url = ($clover_environment === 'production')
    ? 'https://api.clover.com'
    : 'https://sandbox.dev.clover.com';

if (empty($clover_api_key) || empty($clover_merchant_id) || empty($clover_app_id)) {
    wp_die(__('Clover API credentials are not fully configured in the plugin settings.', 'ProjectTheme'));
}

// Calculate amount in cents
$amount_cents = round($membership_cost * 100);

// Generate a unique order ID for Clover
$order_id = 'MEM-' . $uid . '-' . $pack_id . '-' . time();

// Prepare the custom data to pass through Clover redirect (for IPN/callback)
$custom_data = json_encode([
    'mem_id' => $pack_id,
    'uid' => $uid,
    'mem_type' => $mem_type,
    'plan_type' => $plan_type,
    'order_id' => $order_id,
    'amount' => $membership_cost, // Store original amount for verification
    'timestamp' => current_time('timestamp'),
]);

// Encode custom data for URL
$encoded_custom_data = urlencode(base64_encode($custom_data));

// Construct the Clover payment URL
// This is a simplified example. In a real scenario, you'd create an order via Clover API
// and then redirect to their hosted checkout or use their SDK.
// For direct payment initiation via URL, you'd use their payment gateway's hosted page.
// The structure below is illustrative and might need adjustment based on your specific Clover integration type.

// Assuming a direct redirect to a hosted checkout page (like Clover Go or similar)
// This part is highly dependent on your specific Clover integration.
// If you are using Clover's hosted checkout, you'd typically create an order via their API
// and then redirect to the URL provided in the API response.
// The example below is a placeholder for a direct payment link if one exists.

// For a more robust integration, you would make a server-side API call to Clover
// to create an order and get a checkout URL.
// Example of a hypothetical direct payment link (replace with actual Clover hosted checkout URL if applicable)
$clover_checkout_url = $clover_api_url . '/v3/oauth/authorize?response_type=code&client_id=' . $clover_app_id . '&redirect_uri=' . urlencode(get_bloginfo('siteurl') . '/?p_action=clover_payment_success') . '&state=' . $encoded_custom_data;

// For a more complete Clover integration, you'd typically do something like this:
/*
$order_create_url = $clover_api_url . '/v3/merchants/' . $clover_merchant_id . '/orders';
$headers = [
    'Authorization' => 'Bearer ' . $clover_api_key,
    'Content-Type' => 'application/json',
];
$body = [
    'currency' => projecttheme_get_currency_code(), // e.g., 'USD'
    'total' => $amount_cents,
    'lineItems' => [
        [
            'name' => $membership_name . ' Membership',
            'price' => $amount_cents,
            'quantity' => 1,
        ]
    ],
    'note' => 'Membership Purchase: ' . $membership_name . ' (User ID: ' . $uid . ')',
    'externalReferenceId' => $order_id, // Your unique order ID
];

$response = wp_remote_post($order_create_url, [
    'headers' => $headers,
    'body' => json_encode($body),
    'timeout' => 30,
]);

if (is_wp_error($response)) {
    wp_die('Clover API Error: ' . $response->get_error_message());
}

$response_body = wp_remote_retrieve_body($response);
$order_data = json_decode($response_body, true);

if (isset($order_data['id'])) {
    // Now, redirect to Clover's hosted checkout page for this order
    // This URL structure is highly dependent on your Clover setup (e.g., Clover Go, custom app)
    // You might need to consult Clover's documentation for the exact redirect URL.
    $clover_hosted_checkout_url = 'https://www.clover.com/checkout/' . $order_data['id'] . '?redirect_url=' . urlencode(get_bloginfo('siteurl') . '/?p_action=clover_payment_success&state=' . $encoded_custom_data);
    wp_redirect($clover_hosted_checkout_url);
    exit();
} else {
    wp_die('Failed to create Clover order: ' . print_r($order_data, true));
}
*/

// For now, we'll use a simplified redirect to the success page,
// assuming Clover's actual payment processing happens externally
// and then redirects back to 'clover_payment_success' with relevant data.
// This is primarily for testing the flow.
wp_redirect(get_bloginfo('siteurl') . '/?p_action=clover_payment_success&state=' . $encoded_custom_data . '&amount=' . $amount_cents);
exit();
?>
