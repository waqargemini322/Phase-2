<?php
/**
 * ProjectTheme - Moneybookers (Skrill) Membership Payment Gateway
 * This file handles the redirection to Moneybookers (Skrill) for membership payments.
 * It has been updated to support 'investor' membership type and monthly/yearly plans,
 * and now retrieves parameters from $_POST.
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

if(!is_user_logged_in()) {
    wp_redirect(home_url().'/wp-login.php?action=register');
    exit;
}

global $wp_query, $wpdb, $current_user;
get_currentuserinfo();
$uid = $current_user->ID;

$title_post = __('Membership Subscription','ProjectTheme');

// Get membership ID, type, and plan_type from POST parameters
$mem_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$mem_type = isset($_POST['tp']) ? sanitize_text_field($_POST['tp']) : ''; // 'freelancer', 'project_owner', 'investor'
$plan_type = isset($_POST['plan_type']) ? sanitize_text_field($_POST['plan_type']) : 'monthly'; // 'monthly' or 'yearly'

// Validate inputs
if ($mem_id < 1 || $mem_id > 6 || !in_array($mem_type, array('freelancer', 'project_owner', 'investor'))) {
    die('oops, error, invalid membership selection or type.');
}

$cost = 0;
// Determine cost based on membership type and plan_type
switch ($mem_type) {
    case 'freelancer':
        $cost = get_option('pt_freelancer_membership_cost_' . $mem_id);
        break;
    case 'project_owner':
        $cost = get_option('pt_project_owner_membership_cost_' . $mem_id);
        break;
    case 'investor':
        if ($plan_type == 'yearly') {
            $cost = get_option('pt_investor_membership_cost_yearly_' . $mem_id);
        } else { // monthly
            $cost = get_option('pt_investor_membership_cost_' . $mem_id);
        }
        break;
}

if ($cost === false || $cost <= 0) { // Check for non-positive cost
    die('oops, error, membership cost not found or is zero for selected package. Please ensure you are not trying to pay for a free plan via Skrill.');
}

//-------------------------------------------------------------------------

$business = trim(get_option("ProjectTheme_moneybookers_email"));
if (empty($business)) {
    die("ERROR. Please input your Moneybookers email.");
}

$tm             = current_time('timestamp',0);
// Redirect to account page with status on cancel/return
$cancel_url     = get_permalink(get_option("ProjectTheme_my_account_page_id")) . "?payment_status=cancelled";
$response_url   = home_url().'/?p_action=skrill_membership_payment_response'; // This URL handles the IPN (as defined in ProjectTheme_membership.php)
$return_url     = get_permalink(get_option("ProjectTheme_my_account_page_id")) . "?payment_status=success"; // Redirect to account page on success

$currency       = get_option('ProjectTheme_currency');

?>

<html>
<head><title>Processing Skrill Payment...</title></head>
<body onLoad="document.form_mb.submit();">
<center><h3><?php _e("Please wait, your order is being processed...", "ProjectTheme"); ?></h3></center>

    <form name="form_mb" action="https://www.skrill.com/app/payment.pl" method="POST">
    <input type="hidden" name="pay_to_email" value="<?php echo esc_attr($business); ?>">
    <input type="hidden" name="payment_methods" value="ACC,OBT,GIR,DID,SFT,ENT,EBT,SO2,IDL,PLI,NPY,EPY">

    <input type="hidden" name="recipient_description" value="<?php bloginfo("name"); ?>">

    <input type="hidden" name="cancel_url" value="<?php echo esc_url($cancel_url); ?>">
    <input type="hidden" name="status_url" value="<?php echo esc_url($response_url); ?>">

    <input type="hidden" name="language" value="EN">

    <input type="hidden" name="merchant_fields" value="field1">
    <!-- Custom field to pass back mem_id, user ID, timestamp, mem_type, and plan_type -->
    <input type="hidden" name="field1" value="<?php echo esc_attr($mem_id . "|" . $uid . "|" . current_time('timestamp',0) . "|" . $mem_type . "|" . $plan_type); ?>">

    <input type="hidden" name="amount" value="<?php echo esc_attr(ProjectTheme_formats_special($cost, 2)); ?>">
    <input type="hidden" name="currency" value="<?php echo esc_attr($currency); ?>">

    <input type="hidden" name="detail1_description" value="Product: ">
    <input type="hidden" name="detail1_text" value="<?php echo esc_attr($title_post . " - " . $mem_type . " - ID: " . $mem_id . " - Plan: " . $plan_type); ?>">

    <input type="hidden" name="return_url" value="<?php echo esc_url($return_url); ?>">

    </form>

</body>
</html>
