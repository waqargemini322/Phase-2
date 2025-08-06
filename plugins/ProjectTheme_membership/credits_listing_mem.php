<?php
/**
 * ProjectTheme - eWallet Membership Payment Processing Page
 * This file handles the confirmation and processing of membership payments via eWallet.
 * It retrieves all membership details from a transient set by purchase_membership_display.php.
 *
 * v1.1: Corrected credit assignment logic to properly allocate Bids, Connects, and Zoom Invites.
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

// Retrieve membership details from transient
$transient_key = 'pt_membership_purchase_' . $uid;
$membership_details = get_transient($transient_key);

// If transient data is missing or invalid, redirect back to the purchase display page.
if (false === $membership_details || !is_array($membership_details) || empty($membership_details['name'])) {
    wp_redirect(add_query_arg('error', 'transient_expired', get_bloginfo("siteurl") . '/?p_action=purchase_membership_display'));
    exit();
}

// Extract details from the transient
$mem_type           = $membership_details['mem_type'];
$pack_id            = $membership_details['id'];
$plan_type          = $membership_details['plan_type'];
$membership_name    = $membership_details['name'];
$membership_cost    = $membership_details['cost'];
$membership_time    = $membership_details['time'];
$membership_items   = $membership_details['bids']; // Generic term for Bids/Projects
$membership_connects = isset($membership_details['connects']) ? $membership_details['connects'] : 0;
$membership_zoom_invites = isset($membership_details['zoom_invites']) ? $membership_details['zoom_invites'] : 0;
$membership_features = isset($membership_details['features']) ? $membership_details['features'] : [];

// Determine the correct label for items and the time unit for display
$membership_items_label = '';
if ($mem_type == 'project_owner') {
    $membership_items_label = __('Projects included', 'ProjectTheme');
} else { // service_provider or investor
    $membership_items_label = __('Bids included', 'ProjectTheme');
}
$time_unit = ($mem_type == 'investor' && $plan_type == 'yearly') ? __('year(s)', 'ProjectTheme') : __('month(s)', 'ProjectTheme');

// Get user's current e-wallet balance
$current_balance = function_exists('projectTheme_get_credits') ? projectTheme_get_credits($uid) : 0;

// Set page title
add_filter('wp_title', function($title) use ($membership_name) {
    return sprintf(__("Confirm eWallet Payment - %s", "ProjectTheme"), $membership_name) . " - " . $title;
}, 10, 3);

// --- PAYMENT CONFIRMATION LOGIC ---
$payment_confirmed = isset($_GET['confirm_cred']) && $_GET['confirm_cred'] == "1";
$has_enough_balance = ($current_balance >= $membership_cost);

if ($payment_confirmed && $has_enough_balance) {
    
    // 1. Process payment from eWallet
    projectTheme_update_credits($uid, $current_balance - $membership_cost);
    $reason = sprintf(__('Payment for purchasing membership %s', 'ProjectTheme'), $membership_name);
    if (function_exists('projectTheme_add_history_log')) {
        projectTheme_add_history_log('0', $reason, $membership_cost, $uid);
    }

    // --- CORRECTED LOGIC START ---

    // 2. Calculate new membership expiry timestamp
    $tm = current_time("timestamp");
    $new_expiry_time = $tm;
    
    // Handle both yearly and monthly plans correctly
    if ($mem_type == 'investor' && $plan_type == 'yearly') {
        $new_expiry_time = strtotime("+" . $membership_time . " years", $tm);
    } else {
        $new_expiry_time = strtotime("+" . $membership_time . " months", $tm);
    }

    // 3. Update core user meta
    update_user_meta($uid, "membership_available", $new_expiry_time);
    update_user_meta($uid, "mem_type", $membership_name);

    // 4. Correctly assign all three credit types based on the purchased plan
    if ($mem_type == "project_owner") {
        update_user_meta($uid, "projectTheme_monthly_nr_of_projects", $membership_items);
        update_user_meta($uid, "projecttheme_monthly_zoom_invites", $membership_zoom_invites);
        update_user_meta($uid, "pt_connect_credits", $membership_connects);
    } elseif ($mem_type == "investor") {
        update_user_meta($uid, "projectTheme_monthly_nr_of_bids", $membership_items);
        update_user_meta($uid, "projecttheme_monthly_zoom_invites", $membership_zoom_invites);
        update_user_meta($uid, "pt_connect_credits", $membership_connects);
    } else { // service_provider
        update_user_meta($uid, "projectTheme_monthly_nr_of_bids", $membership_items);
        update_user_meta($uid, "projecttheme_monthly_zoom_invites", $membership_zoom_invites);
        update_user_meta($uid, "pt_connect_credits", $membership_connects);
    }
    
    // 5. Set last allocation date for the cron job to handle future renewals correctly
    update_user_meta($uid, 'pt_last_credit_allocation', $tm);

    // --- CORRECTED LOGIC END ---

    // Delete the transient after successful use
    delete_transient($transient_key);

    // Redirect to success page
    wp_redirect(get_permalink(get_option('ProjectTheme_my_account_page_id')) . "?success=membership_upgraded");
    exit();

} elseif ($payment_confirmed && !$has_enough_balance) {
    // Redirect to payments page if not enough balance after confirmation attempt
    wp_redirect(get_permalink(get_option('ProjectTheme_my_account_payments_id')) . "?error=insufficient_funds");
    exit();
}

// Display header and sidebar
get_header('account');
get_template_part('lib/my_account/aside-menu');
?>

<div class="page-wrapper" style="display:block">
    <div class="container-fluid">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-lg-12">
                    <div class="page-header">
                        <h1 class="page-title">
                            <?php printf(__("Confirm eWallet Payment - %s", "ProjectTheme"), esc_html($membership_name)); ?>
                        </h1>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="account-main-area col-xs-12 col-sm-12 col-md-12 col-lg-12">
                    <div class="card">
                        <div class="padd10">
                            <div class="box_content">
                                <div class="row">
                                    <div class="col-lg-12 mb-4">
                                        <?php _e('You are about to pay for your membership using your e-wallet balance. Please review the details below:', 'ProjectTheme'); ?>
                                    </div>
                                </div>

                                <table class="table">
                                    <tbody>
                                        <tr>
                                            <th scope="row"><?php _e('Membership name: ', 'ProjectTheme') ?></th>
                                            <th><?php echo esc_html($membership_name); ?></th>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php _e('Membership cost: ', 'ProjectTheme') ?></th>
                                            <th><?php echo projectTheme_get_show_price($membership_cost); ?></th>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php _e('Your e-wallet Balance: ', 'ProjectTheme') ?></th>
                                            <th class="<?php echo ($has_enough_balance ? '' : 'font-weight-bold text-danger'); ?>">
                                                <?php echo projectTheme_get_show_price($current_balance); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php _e('Valid for: ', 'ProjectTheme') ?></th>
                                            <th><?php echo sprintf(__("%s %s", "ProjectTheme"), esc_html($membership_time), esc_html($time_unit)); ?></th>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <?php echo esc_html($membership_items_label); ?>
                                            </th>
                                            <th><?php echo esc_html($membership_items); ?></th>
                                        </tr>
                                        <?php if ($membership_connects > 0): ?>
                                            <tr>
                                                <th scope="row"><?php _e('Connection Credits: ', 'ProjectTheme') ?></th>
                                                <th><?php echo esc_html($membership_connects); ?></th>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if ($membership_zoom_invites > 0): ?>
                                            <tr>
                                                <th scope="row"><?php _e('Zoom Invites: ', 'ProjectTheme') ?></th>
                                                <th><?php echo esc_html($membership_zoom_invites); ?></th>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>

                                <div class="row">
                                    <div class="col-lg-12 mb-4 align-right-thing">
                                        <?php if (!$has_enough_balance): ?>
                                            <div class="alert alert-danger" role="alert" style="width: 100%; text-align: center;">
                                                <div class=""><?php _e('You do not have enough balance in your e-wallet. Please deposit money or use another payment method.', 'ProjectTheme'); ?></div>
                                                <div class="mt-4">
                                                    <a href="<?php echo get_site_url() . "/?p_action=purchase_membership_display&mem_type=" . esc_attr($mem_type) . "&id=" . esc_attr($pack_id) . "&plan_type=" . esc_attr($plan_type); ?>" class="btn btn-secondary"><?php _e('Go back', 'ProjectTheme') ?></a>
                                                    <a href="<?php echo get_permalink(get_option('ProjectTheme_my_account_payments_id')) ?>" class="btn btn-primary"><?php _e('Go to Finances', 'ProjectTheme') ?></a>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-secondary" role="alert" style="width: 100%; text-align: center;">
                                                <div class=""><?php _e('You are about to pay for your membership using your e-wallet balance.', 'ProjectTheme'); ?></div>
                                                <div class="mt-4">
                                                    <a href="<?php echo get_site_url() . "/?p_action=purchase_membership_display&mem_type=" . esc_attr($mem_type) . "&id=" . esc_attr($pack_id) . "&plan_type=" . esc_attr($plan_type); ?>" class="btn btn-secondary"><?php _e('Go back', 'ProjectTheme') ?></a>
                                                    <a href="<?php echo get_site_url() . "/?p_action=credits_listing_mem&mem_type=" . esc_attr($mem_type) . "&id=" . esc_attr($pack_id) . "&plan_type=" . esc_attr($plan_type) . "&confirm_cred=1"; ?>" class="btn btn-success"><?php _e('Confirm Payment', 'ProjectTheme') ?></a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer('footer'); ?>

<style>
/* This file had inline styles, which are preserved here for consistency. */
.page-wrapper { display: block; }
.card { background-color: #fff; border: 1px solid rgba(0,0,0,.125); border-radius: .25rem; }
.padd10 { padding: 10px; }
.box_content { padding: 15px; }
.table { width: 100%; margin-bottom: 1rem; color: #212529; }
/* additional styles from original file would go here... */
</style>