<?php
/**
 * ProjectTheme - Skrill Membership Payment Gateway
 * This file handles the redirection to Skrill for membership payments.
 * It has been updated to support 'investor' membership type.
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

$title_post = __('Membership Fee','ProjectTheme');
$mem_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mem_type = isset($_GET['tp']) ? sanitize_text_field($_GET['tp']) : ''; // Renamed $slug to $mem_type for clarity

// Validate mem_id and mem_type
if ($mem_id < 1 || $mem_id > 6 || !in_array($mem_type, array('freelancer', 'project_owner', 'investor'))) {
    die('oops, error, invalid membership selection or type');
}

$cost = 0;
switch ($mem_type) {
    case 'freelancer':
        $cost = get_option('pt_freelancer_membership_cost_' . $mem_id);
        break;
    case 'project_owner':
        $cost = get_option('pt_project_owner_membership_cost_' . $mem_id);
        break;
    case 'investor':
        $cost = get_option('pt_investor_membership_cost_' . $mem_id);
        break;
    default:
        die('oops, error, unknown membership type');
}

if ($cost === false || $cost == 0) {
    die('oops, error, membership cost not found or is zero');
}

//------------------

$business = get_option('ProjectTheme_moneybookers_email');
if(empty($business)) die('ERROR. Please input your Moneybookers email.');

//------------------

$tm             = current_time('timestamp',0);
$cancel_url     = ProjectTheme_get_payments_page_url('deposit');
$response_url   = home_url().'/?p_action=skrill_membership_payment_response';
$ccnt_url       = ProjectTheme_get_payments_page_url();
$currency       = get_option('ProjectTheme_currency');

$uid = get_current_user_id();

if(ProjectTheme_using_permalinks()) {
    $return_url = get_permalink(get_option('ProjectTheme_my_account_page_id')) . "?payment_done=1";
} else {
    $return_url = get_permalink(get_option('ProjectTheme_my_account_page_id')) . "&payment_done=1";
}

?>

<html>
<head><title>Processing Skrill Payment...</title></head>
<body onLoad="document.form_mb.submit();">
<center><h3><?php _e('Please wait, your order is being processed...', 'ProjectTheme'); ?></h3></center>

    <form name="form_mb" action="https://www.skrill.com/app/payment.pl" method="POST">
    <input type="hidden" name="pay_to_email" value="<?php echo esc_attr($business); ?>">
    <input type="hidden" name="payment_methods" value="ACC,OBT,GIR,DID,SFT,ENT,EBT,SO2,IDL,PLI,NPY,EPY">

    <input type="hidden" name="recipient_description" value="<?php bloginfo('name'); ?>">

    <input type="hidden" name="cancel_url" value="<?php echo esc_url(get_permalink(get_option('ProjectTheme_my_account_page_id'))); ?>">
    <input type="hidden" name="status_url" value="<?php echo esc_url($response_url); ?>">

    <input type="hidden" name="language" value="EN">

    <input type="hidden" name="merchant_fields" value="field1">
    <input type="hidden" name="field1" value="<?php echo esc_attr($mem_id.'|'.$uid.'|'.current_time('timestamp',0)."|" . $mem_type); ?>">

    <input type="hidden" name="amount" value="<?php echo esc_attr($cost); ?>">
    <input type="hidden" name="currency" value="<?php echo esc_attr($currency); ?>">

    <input type="hidden" name="detail1_description" value="Product: ">
    <input type="hidden" name="detail1_text" value="<?php echo esc_attr($title_post); ?>">

    <input type="hidden" name="return_url" value="<?php echo esc_url($return_url); ?>">

    </form>

</body>
</html>
