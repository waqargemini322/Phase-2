<?php
/**
 * ProjectTheme - Investor Membership Confirmation and Payment Selection Page
 * This file displays details of a selected investor membership plan (from the main investor page)
 * and presents direct payment gateway options (eWallet, PayPal, Skrill, Clover).
 * It expects 'id' as a GET parameter for the membership package.
 *
 * IMPORTANT: This version now handles free memberships directly, activating them
 * and redirecting the user without showing payment options.
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

// Get membership ID from GET parameter
$pack_id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

// Validate pack_id (assuming 1 to 6 are valid IDs)
if ($pack_id == 0 || $pack_id < 1 || $pack_id > 6) {
    wp_redirect(get_bloginfo("siteurl") . "/?p_action=get_new_mem"); // Redirect to membership selection if invalid ID
    exit();
}

// Retrieve investor membership details using standard options (no 'yearly' suffix as per your request)
$membership_name = get_option("pt_investor_membership_name_" . $pack_id);
$membership_cost = get_option("pt_investor_membership_cost_" . $pack_id);
$membership_time = get_option("pt_investor_membership_time_" . $pack_id); // In months
$membership_bids = get_option("pt_investor_membership_bids_" . $pack_id);

// Retrieve additional features for the selected plan
$membership_features = [];
for ($f = 1; $f <= 5; $f++) {
    $feature_value = get_option("pt_investor_membership_feature" . $f . "_" . $pack_id);
    if (!empty($feature_value)) {
        $membership_features[] = $feature_value;
    }
}

// If membership details are not found
if (empty($membership_name) || $membership_cost === false) {
    wp_redirect(get_bloginfo("siteurl") . "/?p_action=get_new_mem"); // Redirect if details are missing
    exit();
}

// Check if the user is a restricted member (using Link2Investors custom features plugin function)
if (function_exists('l2i_is_restricted_member') && l2i_is_restricted_member($uid)) {
    wp_redirect(get_permalink(get_option("ProjectTheme_my_account_page_id")) . "?error=restricted_membership_purchase");
    exit();
}

// --- NEW LOGIC FOR FREE MEMBERSHIPS ---
if ($membership_cost == 0) {
    // Check if the user has already exhausted free memberships (optional, based on your theme's logic)
    // This check is already done in pt_mem_show_expiry and ProjectTheme_is_it_allowed_place_bids_memms
    // but can be reinforced here if needed. For now, we'll proceed with activation.

    $tm = current_time("timestamp");
    $new_expiry = $tm + ($membership_time * 30.5 * 24 * 3600); // Calculate expiry based on months

    // Update user meta for free plan activation
    update_user_meta($uid, "membership_available", $new_expiry);
    update_user_meta($uid, "mem_type", $membership_name);
    update_user_meta($uid, "projectTheme_monthly_nr_of_bids", $membership_bids);
    update_user_meta($uid, "projecttheme_monthly_zoom_invites", 0); // Explicitly set to 0 for free plans
    update_user_meta($uid, "pt_connect_credits", 0); // Explicitly set to 0 for free plans
    update_user_meta($uid, "free_membership_exhausted", "yes"); // Mark free membership used

    // Redirect to my account page with success message
    wp_redirect(get_permalink(get_option("ProjectTheme_my_account_page_id")) . "?success=membership_activated");
    exit();
}
// --- END NEW LOGIC ---

// If the cost is not 0, proceed to display payment options
get_header();
?>

<div id="main_wrapper">
	<div id="main" class="wrapper">
		<div class="container">
			<div id="content" class="content-full-width">
				<div class="box_content">
					<div class="box_title"><?php echo sprintf(__("Purchase %s Membership", "ProjectTheme"), esc_html($membership_name)); ?></div>
					<div class="box_content">
						<div class="padd10">
							<p>
								<?php echo sprintf(__("You are about to purchase your membership. You can see the details of your membership down below", "ProjectTheme")); ?>
							</p>

                            <div class="membership-details-table">
                                <div class="detail-row">
                                    <div class="detail-label"><?php _e("MEMBERSHIP NAME:", "ProjectTheme"); ?></div>
                                    <div class="detail-value"><?php echo esc_html($membership_name); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label"><?php _e("MEMBERSHIP COST:", "ProjectTheme"); ?></div>
                                    <div class="detail-value"><?php echo projecttheme_get_currency() . " " . esc_html($membership_cost); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label"><?php _e("VALID FOR:", "ProjectTheme"); ?></div>
                                    <div class="detail-value"><?php echo sprintf(__("%s MONTH(S)", "ProjectTheme"), esc_html($membership_time)); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label"><?php _e("BIDS INCLUDED:", "ProjectTheme"); ?></div>
                                    <div class="detail-value"><?php echo esc_html($membership_bids); ?></div>
                                </div>
                                <?php foreach ($membership_features as $index => $feature): ?>
                                <div class="detail-row">
                                    <div class="detail-label"><?php echo sprintf(__("FEATURE %d:", "ProjectTheme"), $index + 1); ?></div>
                                    <div class="detail-value"><?php echo esc_html($feature); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>

							<div class="clear10"></div>

							<div class="payment-buttons-container">
                                <!-- eWallet Form -->
                                <?php
                                $ProjectTheme_credits_enable = get_option("ProjectTheme_credits_enable");
                                if ($ProjectTheme_credits_enable == "yes"): ?>
                                <form action="<?php echo get_bloginfo("siteurl"); ?>/?p_action=credits_listing_mem" method="post">
                                    <input type="hidden" name="id" value="<?php echo esc_attr($pack_id); ?>" />
                                    <input type="hidden" name="tp" value="investor" />
                                    <button type="submit" class="btn btn-primary ewallet-btn">
                                        <?php _e("Pay by eWallet", "ProjectTheme"); ?>
                                    </button>
                                </form>
                                <?php endif; ?>

								<?php
								$ProjectTheme_paypal_enable = get_option("ProjectTheme_paypal_enable");
								if ($ProjectTheme_paypal_enable == "yes"): ?>
								<form action="<?php echo get_bloginfo("siteurl"); ?>/?p_action=paypal_membership_mem" method="post">
                                    <input type="hidden" name="mem_id" value="<?php echo esc_attr($pack_id); ?>" />
                                    <input type="hidden" name="mem_type" value="investor" />
									<button type="submit" class="btn btn-primary paypal-btn">
										<?php _e("Pay by PayPal", "ProjectTheme"); ?>
									</button>
								</form>
								<?php endif; ?>

								<?php
								$ProjectTheme_skrill_enable = get_option("ProjectTheme_skrill_enable");
								if ($ProjectTheme_skrill_enable == "yes"): ?>
								<form action="<?php echo get_bloginfo("siteurl"); ?>/?p_action=mb_membership_mem" method="post">
                                    <input type="hidden" name="mem_id" value="<?php echo esc_attr($pack_id); ?>" />
                                    <input type="hidden" name="mem_type" value="investor" />
									<button type="submit" class="btn btn-primary skrill-btn">
										<?php _e("Pay by Skrill", "ProjectTheme"); ?>
									</button>
								</form>
								<?php endif; ?>

                                <?php
                                $ProjectTheme_clover_enable = get_option("ProjectTheme_clover_enable");
                                if ($ProjectTheme_clover_enable == "yes"): ?>
                                <form action="<?php echo get_bloginfo("siteurl"); ?>/?p_action=purchase_membership_clover_investor" method="post">
                                    <input type="hidden" name="id" value="<?php echo esc_attr($pack_id); ?>" />
                                    <input type="hidden" name="tp" value="investor" />
                                    <button type="submit" class="btn btn-primary clover-btn">
                                        <?php _e("Pay by Credit Card", "ProjectTheme"); ?>
                                    </button>
                                </form>
                                <?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
    /* General container styling */
    #main_wrapper {
        padding: 20px 0;
        background-color: #f0f2f5;
        font-family: 'Inter', sans-serif;
    }

    #main.wrapper {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px;
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    }

    .box_content {
        padding: 25px;
    }

    .box_title {
        font-size: 2em;
        color: #333;
        margin-bottom: 20px;
        font-weight: 700;
        text-align: center;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    /* Membership details table styling */
    .membership-details-table {
        width: 100%;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 30px;
    }

    .detail-row {
        display: flex;
        padding: 12px 20px;
        border-bottom: 1px solid #f0f0f0;
        background-color: #ffffff;
    }

    .detail-row:nth-child(even) {
        background-color: #f9f9f9;
    }

    .detail-row:last-child {
        border-bottom: none;
    }

    .detail-label {
        flex: 1;
        font-weight: 600;
        color: #555;
        text-align: left;
    }

    .detail-value {
        flex: 2;
        color: #333;
        text-align: right;
    }

    /* Payment buttons container */
    .payment-buttons-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        justify-content: center;
        margin-top: 30px;
    }

    .payment-buttons-container form {
        flex: 1 1 auto; /* Allows items to grow and shrink */
        max-width: 250px; /* Max width for each button container */
        text-align: center;
    }

    .payment-buttons-container .btn {
        width: 100%; /* Make button fill its container */
        padding: 15px 25px;
        font-size: 1.1em;
        border-radius: 30px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(0, 123, 255, 0.25);
        border: none;
        cursor: pointer;
    }

    .payment-buttons-container .btn-primary {
        background: linear-gradient(45deg, #007bff, #0056b3);
        color: #fff;
    }

    .payment-buttons-container .btn-primary:hover {
        background: linear-gradient(45deg, #0056b3, #003f7f);
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 123, 255, 0.4);
    }

    /* Specific button colors */
    .payment-buttons-container .ewallet-btn {
        background: linear-gradient(45deg, #6c757d, #5a6268); /* Grey */
    }
    .payment-buttons-container .ewallet-btn:hover {
        background: linear-gradient(45deg, #5a6268, #495057);
    }

    .payment-buttons-container .paypal-btn {
        background: linear-gradient(45deg, #0070ba, #00457c); /* PayPal Blue */
    }
    .payment-buttons-container .paypal-btn:hover {
        background: linear-gradient(45deg, #00457c, #002b4d);
    }

    .payment-buttons-container .skrill-btn {
        background: linear-gradient(45deg, #ff6600, #cc5200); /* Skrill Orange */
    }
    .payment-buttons-container .skrill-btn:hover {
        background: linear-gradient(45deg, #cc5200, #993d00);
    }

    .payment-buttons-container .clover-btn {
        background: linear-gradient(45deg, #28a745, #1e7e34); /* Green for Credit Card */
    }
    .payment-buttons-container .clover-btn:hover {
        background: linear-gradient(45deg, #1e7e34, #155d28);
    }

    .clear10 {
        clear: both;
        height: 10px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .payment-buttons-container {
            flex-direction: column;
            align-items: center;
        }
        .payment-buttons-container form {
            max-width: 90%; /* Adjust for smaller screens */
        }
    }
</style>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

<?php get_footer(); ?>
