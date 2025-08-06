<?php
/**
 * ProjectTheme - Unified Membership Purchase Display and Payment Selection Page
 * This file dynamically displays details of a selected membership plan (for any role)
 * and presents direct payment gateway options (eWallet, PayPal, Skrill, Clover).
 * It expects 'mem_type', 'id', and optionally 'plan_type' as GET parameters.
 *
 * This version's HTML structure and styling are preserved from the working 'purchase_membership_buyer.php'
 * (which you confirmed gave the right look) and integrated with unified logic.
 */

// IMPORTANT: The main plugin file (ProjectTheme_membership.php) now uses 'template_redirect' hook,
// which ensures WordPress environment is fully loaded before this file is included.
// No need for redundant wp-load.php check here.

// Redirect to login if user is not logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit();
}

global $current_user;
get_currentuserinfo();
$uid = $current_user->ID;

// Get membership parameters from GET
$mem_type  = isset($_GET['mem_type']) ? sanitize_text_field($_GET['mem_type']) : '';
$pack_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$plan_type = isset($_GET['plan_type']) ? sanitize_text_field($_GET['plan_type']) : 'monthly'; // Default to 'monthly'

// Log incoming parameters for debugging
error_log("DEBUG: purchase_membership_display.php: Initial Call - mem_type=$mem_type, id=$pack_id, plan_type=$plan_type");

// Basic validation for membership ID
if ($pack_id == 0 || empty($mem_type)) {
    error_log("ERROR: purchase_membership_display.php: Missing pack_id or mem_type. Redirecting to site URL.");
    wp_redirect(get_bloginfo('siteurl'));
    exit();
}

// Initialize membership details
$membership_name = '';
$membership_cost = 0;
$membership_time = 0;
$membership_items_label = ''; // Will be 'Bids' or 'Projects'
$membership_items_count = 0;
$membership_features = []; // Only for Investor plans
$time_unit = __('month(s)', 'ProjectTheme');
$membership_connects = 0; // Initialize connects
$membership_zoom_invites = 0; // Initialize zoom invites

// Fetch membership details based on mem_type, id, and plan_type
switch ($mem_type) {
    case 'investor':
        if ($plan_type == 'yearly') {
            $membership_name        = get_option("pt_investor_membership_name_yearly_" . $pack_id);
            $membership_cost        = get_option("pt_investor_membership_cost_yearly_" . $pack_id);
            $membership_time        = get_option("pt_investor_membership_time_yearly_" . $pack_id);
            $membership_items_count = get_option("pt_investor_membership_bids_yearly_" . $pack_id);
            $membership_connects    = get_option("pt_investor_membership_connects_yearly_" . $pack_id, 0);
            $membership_zoom_invites = get_option("pt_investor_membership_zoom_invites_yearly_" . $pack_id, 0);
            $time_unit              = __('year(s)', 'ProjectTheme');
            for ($f = 1; $f <= 5; $f++) {
                $feature_value = get_option("pt_investor_membership_feature" . $f . "_yearly_" . $pack_id);
                if (!empty($feature_value)) {
                    $membership_features[] = $feature_value;
                }
            }
        } else { // monthly
            $membership_name        = get_option("pt_investor_membership_name_" . $pack_id);
            $membership_cost        = get_option("pt_investor_membership_cost_" . $pack_id);
            $membership_time        = get_option("pt_investor_membership_time_" . $pack_id);
            $membership_items_count = get_option("pt_investor_membership_bids_" . $pack_id);
            $membership_connects    = get_option("pt_investor_membership_connects_" . $pack_id, 0);
            $membership_zoom_invites = get_option("pt_investor_membership_zoom_invites_" . $pack_id, 0);
            $time_unit              = __('month(s)', 'ProjectTheme');
            for ($f = 1; $f <= 5; $f++) {
                $feature_value = get_option("pt_investor_membership_feature" . $f . "_" . $pack_id);
                if (!empty($feature_value)) {
                    $membership_features[] = $feature_value;
                }
            }
        }
        $membership_items_label = __('Bids included', 'ProjectTheme'); // Changed from 'Invites (Zoom)' for consistency with table
        break;

    case 'project_owner': // Entrepreneur
        $membership_name        = get_option("pt_project_owner_membership_name_" . $pack_id);
        $membership_cost        = get_option("pt_project_owner_membership_cost_" . $pack_id);
        $membership_time        = get_option("pt_project_owner_membership_time_" . $pack_id);
        $membership_items_count = get_option("pt_project_owner_membership_projects_" . $pack_id);
        $membership_connects    = get_option("pt_project_owner_membership_connects_" . $pack_id, 0);
        $membership_zoom_invites = get_option("pt_project_owner_membership_zoom_invites_" . $pack_id, 0);
        $membership_items_label = __('Projects included', 'ProjectTheme');
        $time_unit              = __('month(s)', 'ProjectTheme');
        break;

    case 'service_provider': // Freelancer
        $membership_name        = get_option("pt_freelancer_membership_name_" . $pack_id);
        $membership_cost        = get_option("pt_freelancer_membership_cost_" . $pack_id);
        $membership_time        = get_option("pt_freelancer_membership_time_" . $pack_id);
        $membership_items_count = get_option("pt_freelancer_membership_bids_" . $pack_id);
        $membership_connects    = get_option("pt_freelancer_membership_connects_" . $pack_id, 0);
        $membership_zoom_invites = get_option("pt_freelancer_membership_zoom_invites_" . $pack_id, 0);
        $membership_items_label = __('Bids included', 'ProjectTheme');
        $time_unit              = __('month(s)', 'ProjectTheme');
        break;

    default:
        error_log("ERROR: purchase_membership_display.php: Invalid membership type '$mem_type'. Redirecting to site URL.");
        wp_redirect(get_bloginfo('siteurl'));
        exit();
}

// Validate that we actually retrieved a valid membership name/cost
if (empty($membership_name) || $membership_cost === false) {
    error_log("ERROR: purchase_membership_display.php: Could not retrieve valid membership details for mem_type=$mem_type, pack_id=$pack_id, plan_type=$plan_type. Name: '$membership_name', Cost: '$membership_cost'");
    wp_redirect(get_permalink(get_option('ProjectTheme_my_account_page_id')) . "?error=invalid_package_data");
    exit();
}


// If cost is 0, it's a free membership, redirect to get_free_membership handler
if ($membership_cost == 0) {
    $redirect_url = get_bloginfo("siteurl") . '/?get_free_membership=' . $pack_id . '&type=' . $mem_type;
    if ($mem_type == 'investor') {
        $redirect_url .= '&plan_type=' . $plan_type;
    }
    error_log("DEBUG: purchase_membership_display.php: Free membership, redirecting to: " . $redirect_url);
    wp_redirect($redirect_url);
    exit();
}

// Store ALL membership details in transient for payment processing pages
$transient_key = 'pt_membership_purchase_' . $uid;
set_transient($transient_key, [
    'mem_type'          => $mem_type,
    'id'                => $pack_id,
    'plan_type'         => $plan_type,
    'name'              => $membership_name,
    'cost'              => $membership_cost,
    'time'              => $membership_time,
    'bids'              => $membership_items_count,
    'connects'          => $membership_connects,
    'zoom_invites'      => $membership_zoom_invites,
    'features'          => $membership_features // Store features here
], HOUR_IN_SECONDS); // Increased expiry to 1 hour (originally 5 minutes)
error_log("DEBUG: Transient set for user " . $uid . ": " . print_r(get_transient($transient_key), true));


// Set page title (used by WordPress if this was a template, but useful for context)
add_filter('wp_title', function($title) use ($membership_name) {
    return sprintf(__("Purchase Membership - %s", "ProjectTheme"), $membership_name) . " - " . $title;
}, 10, 3);

// If the cost is not 0, proceed to display payment options
get_header('account'); // Use 'account' header as in original file
get_template_part('lib/my_account/aside-menu'); // Include aside menu as in original file
?>

<div class="page-wrapper" style="display:block">
    <div class="container-fluid">

        <?php do_action('pt_for_demo_work_3_0'); ?>

        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-lg-12">
                    <div class="page-header">
                        <h1 class="page-title">
                            <?php printf(__("Purchase Membership - %s", "ProjectTheme"), esc_html($membership_name)); ?>
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
                                        <?php
                                        // Display messages from previous redirects
                                        if (isset($_GET['error'])) {
                                            $error_message = '';
                                            switch ($_GET['error']) {
                                                case 'transient_expired':
                                                    $error_message = __('Your previous membership selection expired. Please choose again.', 'ProjectTheme');
                                                    break;
                                                case 'invalid_mem_type':
                                                    $error_message = __('Invalid membership type selected.', 'ProjectTheme');
                                                    break;
                                                case 'insufficient_funds':
                                                    $error_message = __('You do not have enough funds in your e-wallet. Please add funds or choose another payment method.', 'ProjectTheme');
                                                    break;
                                                case 'invalid_package_data':
                                                    $error_message = __('The selected membership package could not be found or is invalid. Please try again.', 'ProjectTheme');
                                                    break;
                                                case 'invalid_purchase_request':
                                                    $error_message = __('Invalid purchase request. Please select a membership plan from the main page.', 'ProjectTheme');
                                                    break;
                                                case 'missing_details': // From credits_listing_mem.php if essential data is missing
                                                    $error_message = __('Missing membership details. Please try selecting your plan again.', 'ProjectTheme');
                                                    break;
                                                default:
                                                    $error_message = __('An error occurred. Please try again.', 'ProjectTheme');
                                            }
                                            echo '<div class="alert alert-danger">' . esc_html($error_message) . '</div>';
                                        }
                                        ?>
                                        <p><?php _e('You are about to purchase your membership. You can see the details of your membership down below: ', 'ProjectTheme'); ?></p>
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
                                            <th scope="row"><?php _e('Valid for: ', 'ProjectTheme') ?></th>
                                            <th><?php echo sprintf(__("%s %s", "ProjectTheme"), esc_html($membership_time), esc_html($time_unit)); ?></th>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <?php echo esc_html($membership_items_label); ?>
                                            </th>
                                            <th><?php echo esc_html($membership_items_count); ?></th>
                                        </tr>
                                        <?php if ($membership_connects > 0): ?>
                                            <tr>
                                                <th scope="row"><?php _e('Connects Credits: ', 'ProjectTheme') ?></th>
                                                <th><?php echo esc_html($membership_connects); ?></th>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if ($membership_zoom_invites > 0): ?>
                                            <tr>
                                                <th scope="row"><?php _e('Zoom Invites: ', 'ProjectTheme') ?></th>
                                                <th><?php echo esc_html($membership_zoom_invites); ?></th>
                                            </tr>
                                        <?php endif; ?>
                                        <?php
                                        // Display investor specific features if available
                                        if ($mem_type == 'investor' && !empty($membership_features)) {
                                            foreach ($membership_features as $feature) {
                                                echo '<tr>';
                                                echo '<th scope="row">' . __('Feature: ', 'ProjectTheme') . '</th>';
                                                echo '<th>' . esc_html($feature) . '</th>';
                                                echo '</tr>';
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>

                                <div class="row">
                                    <div class="col-lg-12 mb-4 align-right-thing">
                                        <?php
                                        // Base parameters for payment links
                                        $base_params = [
                                            'mem_type'  => $mem_type,
                                            'id'        => $pack_id,
                                            'plan_type' => $plan_type,
                                        ];

                                        // eWallet button
                                        $ProjectTheme_enable_credits_wallet = get_option('ProjectTheme_enable_credits_wallet');
                                        if ($ProjectTheme_enable_credits_wallet == "yes"):
                                            $ewallet_link = add_query_arg(array_merge($base_params, ['p_action' => 'credits_listing_mem']), get_site_url());
                                            ?>
                                            <button type="button" onclick="location.href='<?php echo esc_url($ewallet_link); ?>'" class="btn btn-secondary"><?php _e('Pay by eWallet', 'ProjectTheme') ?></button>
                                        <?php endif; ?>

                                        <?php
                                        // PayPal button
                                        $ProjectTheme_paypal_enable = get_option('ProjectTheme_paypal_enable');
                                        if ($ProjectTheme_paypal_enable == "yes"):
                                            $paypal_link = add_query_arg(array_merge($base_params, ['p_action' => 'paypal_membership_mem']), get_site_url());
                                            ?>
                                            <button type="button" onclick="location.href='<?php echo esc_url($paypal_link); ?>'" class="btn btn-primary"><?php _e('Pay by PayPal', 'ProjectTheme') ?></button>
                                        <?php endif; ?>

                                        <?php
                                        // Skrill button
                                        $ProjectTheme_moneybookers_enable = get_option('ProjectTheme_moneybookers_enable');
                                        if ($ProjectTheme_moneybookers_enable == "yes"):
                                            $skrill_link = add_query_arg(array_merge($base_params, ['p_action' => 'mb_membership_mem']), get_site_url());
                                            ?>
                                            <button type="button" onclick="location.href='<?php echo esc_url($skrill_link); ?>'" class="btn btn-primary"><?php _e('Pay by Skrill', 'ProjectTheme') ?></button>
                                        <?php endif; ?>

                                        <?php
                                        // Clover payment button
                                        $ProjectTheme_clover_enable = get_option('ProjectTheme_clover_enable');
                                        if ($ProjectTheme_clover_enable == "yes"):
                                            $clover_link = add_query_arg(array_merge($base_params, ['p_action' => 'purchase_membership_clover_unified']), get_site_url());
                                            ?>
                                            <button type="button" onclick="location.href='<?php echo esc_url($clover_link); ?>'" class="btn btn-primary">
                                                <?php _e('Pay By Clover', 'ProjectTheme'); ?>
                                            </button>
                                        <?php endif; ?>
                                        <?php do_action('project_theme_membership_purchase_buyer'); // This hook might add other payment buttons ?>
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
