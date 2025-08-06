<?php
/*
Plugin Name: ProjectTheme Memberships
Plugin URI: http://sitemile.com/
Description: Adds a membership/subscription feature to the Project Bidding Theme from sitemile
Author: SiteMile.com
Author URI: http://sitemile.com/
Version: 2.2.0
Text Domain: pt_mem
*/

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Enable error reporting for debugging (remove in production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Include other plugin files
include "membership-shortcodes.php";
include "admin_func.php";

// Add filters and actions for the plugin's main functionality
add_filter(
	"ProjectTheme_general_settings_main_details_options_save",
	"ProjectTheme_general_settings_main_details_options_save_memms"
);
add_filter("ProjectTheme_is_it_allowed_place_bids", "ProjectTheme_is_it_allowed_place_bids_memms");
add_filter(
	"ProjectTheme_is_it_not_allowed_place_bids_action",
	"ProjectTheme_is_it_not_allowed_place_bids_action_meeems"
);
add_filter("ProjectTheme_before_payments_in_payments", "ProjectTheme_before_payments_in_payments_meemss");

add_filter("ProjectTheme_post_bid_ok_action", "ProjectTheme_post_bid_ok_action_mem_fncs");
add_filter("ProjectTheme_display_bidding_panel", "ProjectTheme_display_bidding_panel_mms");
add_filter("ProjectTheme_is_it_allowed_post_projects", "ProjectTheme_is_it_allowed_post_projects_fn");
add_filter("ProjectTheme_when_creating_auto_draft", "ProjectTheme_when_creating_auto_draft_ff");

add_action("pt_at_account_dash_top", "pt_mem_show_expiry");

register_activation_hook(__FILE__, "PT_mem_my_plugin_activate");
register_deactivation_hook(__FILE__, "PT_mem_my_plugin_deactivate"); // NEW: Deactivation hook

// --- CRITICAL CHANGE: Use 'template_redirect' hook for p_action handling ---
add_action('template_redirect', 'ProjectTheme_handle_paction_requests');

// --- NEW: Schedule and implement monthly credit replenishment cron ---
add_action('wp', 'pt_schedule_monthly_credits_cron');
add_action('pt_monthly_credits_event', 'pt_membership_monthly_credits_cron_callback');

function pt_schedule_monthly_credits_cron() {
    if (!wp_next_scheduled('pt_monthly_credits_event')) {
        wp_schedule_event(time(), 'daily', 'pt_monthly_credits_event'); // Schedule daily, check monthly inside
    }
}

function PT_mem_my_plugin_deactivate() { // NEW: Deactivation function
    wp_clear_scheduled_hook('pt_monthly_credits_event');
}

function pt_membership_monthly_credits_cron_callback() {
    // This function will run daily, but we'll check for monthly replenishment
    error_log("DEBUG: Running pt_membership_monthly_credits_cron_callback at " . current_time('mysql'));

    $users = get_users(array(
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key'     => 'mem_type',
                'compare' => 'EXISTS',
            ),
            array(
                'key'     => 'membership_available',
                'compare' => 'EXISTS',
            ),
        ),
    ));

    $current_time = current_time('timestamp');

    foreach ($users as $user) {
        $uid = $user->ID;
        $membership_type = get_user_meta($uid, 'mem_type', true);
        $membership_available = get_user_meta($uid, 'membership_available', true); // Expiry timestamp
        $last_credit_allocation = get_user_meta($uid, 'pt_last_credit_allocation', true); // NEW: Last allocation timestamp

        // Only process active memberships and yearly plans
        if ($membership_available > $current_time && (strpos($membership_type, 'Yearly') !== false || strpos($membership_type, 'Annual') !== false)) {

            $user_role = ProjectTheme_mems_get_current_user_role($uid);
            $pack_id = 0; // We need to find the pack_id based on membership_type
            $plan_type = 'monthly'; // Default for options lookup, will be 'yearly' for yearly plans

            // Determine pack_id and plan_type from membership_type
            // This is a reverse lookup, might be inefficient if many plans.
            // A better way would be to store pack_id and plan_type directly in user meta on purchase.
            // For now, let's try to match.
            $found_plan = false;
            for ($i = 1; $i <= 6; $i++) {
                // Check monthly investor plans
                if ($membership_type == get_option('pt_investor_membership_name_' . $i)) {
                    $pack_id = $i;
                    $plan_type = 'monthly';
                    $found_plan = true;
                    break;
                }
                // Check yearly investor plans
                if ($membership_type == get_option('pt_investor_membership_name_yearly_' . $i)) {
                    $pack_id = $i;
                    $plan_type = 'yearly';
                    $found_plan = true;
                    break;
                }
                // Check freelancer plans
                if ($membership_type == get_option('pt_freelancer_membership_name_' . $i)) {
                    $pack_id = $i;
                    $plan_type = 'monthly'; // Freelancer plans are monthly
                    $found_plan = true;
                    break;
                }
                 // Check project owner plans
                if ($membership_type == get_option('pt_project_owner_membership_name_' . $i)) {
                    $pack_id = $i;
                    $plan_type = 'monthly'; // Project owner plans are monthly
                    $found_plan = true;
                    break;
                }
            }

            if (!$found_plan) {
                error_log("WARNING: pt_membership_monthly_credits_cron_callback: Could not find matching plan for user " . $uid . " with membership type " . $membership_type);
                continue; // Skip if plan not found
            }

            $next_allocation_time = 0;
            if (empty($last_credit_allocation)) {
                // If no previous allocation, assume it was allocated at membership start
                // and next allocation is one month from membership start.
                // For yearly plans, membership_available is the expiry, not start.
                // We need to infer start date or store it. For now, let's assume
                // initial allocation was at purchase, and next is 1 month after purchase.
                // This is tricky without a 'membership_start_date' meta.
                // For simplicity, let's use the current time for first check if no last_allocation.
                // A better approach: when membership is assigned, set last_credit_allocation to current time.
                $next_allocation_time = $current_time; // Will be immediately replenished on first run if no previous record
            } else {
                $next_allocation_time = strtotime('+1 month', $last_credit_allocation);
            }

            error_log("DEBUG: User " . $uid . ", Membership: " . $membership_type . ", Last Allocation: " . date('Y-m-d H:i:s', $last_credit_allocation) . ", Next Allocation Check: " . date('Y-m-d H:i:s', $next_allocation_time));

            // Check if it's time for a new monthly allocation
            if ($current_time >= $next_allocation_time) {
                $bids_to_add = 0;
                $connects_to_add = 0;
                $zoom_invites_to_add = 0;

                // Retrieve the monthly amounts from options based on role and plan type
                if ($user_role == 'service_provider') {
                    $bids_to_add = get_option('pt_freelancer_membership_bids_' . $pack_id, 0);
                    $connects_to_add = get_option('pt_freelancer_membership_connects_' . $pack_id, 0);
                    $zoom_invites_to_add = get_option('pt_freelancer_membership_zoom_invites_' . $pack_id, 0);
                } elseif ($user_role == 'business_owner') {
                    $bids_to_add = get_option('pt_project_owner_membership_projects_' . $pack_id, 0); // Projects are "bids" for project owners
                    $connects_to_add = get_option('pt_project_owner_membership_connects_' . $pack_id, 0);
                    $zoom_invites_to_add = get_option('pt_project_owner_membership_zoom_invites_' . $pack_id, 0);
                } elseif ($user_role == 'investor') {
                    if ($plan_type == 'yearly') {
                        $bids_to_add = get_option('pt_investor_membership_bids_yearly_' . $pack_id, 0);
                        $connects_to_add = get_option('pt_investor_membership_connects_yearly_' . $pack_id, 0);
                        $zoom_invites_to_add = get_option('pt_investor_membership_zoom_invites_yearly_' . $pack_id, 0);
                    } else { // Monthly investor plan (should not be replenished by cron, but included for completeness)
                        $bids_to_add = get_option('pt_investor_membership_bids_' . $pack_id, 0);
                        $connects_to_add = get_option('pt_investor_membership_connects_' . $pack_id, 0);
                        $zoom_invites_to_add = get_option('pt_investor_membership_zoom_invites_' . $pack_id, 0);
                    }
                }

                // Add credits/invites to user's current meta
                $current_bids = get_user_meta($uid, 'projectTheme_monthly_nr_of_bids', true);
                $current_connects = get_user_meta($uid, 'pt_connect_credits', true);
                $current_zoom_invites = get_user_meta($uid, 'projecttheme_monthly_zoom_invites', true);

                update_user_meta($uid, 'projectTheme_monthly_nr_of_bids', $current_bids + $bids_to_add);
                update_user_meta($uid, 'pt_connect_credits', $current_connects + $connects_to_add);
                update_user_meta($uid, 'projecttheme_monthly_zoom_invites', $current_zoom_invites + $zoom_invites_to_add);

                // Update the last allocation timestamp
                update_user_meta($uid, 'pt_last_credit_allocation', $current_time);

                error_log("DEBUG: User " . $uid . ": Replenished " . $bids_to_add . " bids, " . $connects_to_add . " connects, " . $zoom_invites_to_add . " zoom invites.");
            }
        }
    }
}


//************************************************************************
//
//	function
//
//************************************************************************

function PT_mem_my_plugin_activate()
{
	global $wpdb;
	$ss =
		"CREATE TABLE `" .
		$wpdb->prefix .
		"project_membership_coupons` (
				`id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
				`coupon_code` varchar(255) NOT NULL DEFAULT '0',
				`discount_amount` varchar(255) NOT NULL DEFAULT '0'
				) ENGINE = MYISAM ;
				";
	$wpdb->query($ss);

    // Schedule the cron event on activation
    if (!wp_next_scheduled('pt_monthly_credits_event')) {
        wp_schedule_event(time(), 'daily', 'pt_monthly_credits_event'); // Check daily for monthly replenishment
    }
}

//************************************************************************
//
// function
//************************************************************************

function projecttheme_membership($uid)
{
	$mem_type = get_user_meta($uid, "mem_type", true);
	if (empty($mem_type)) {
		return __("n/a", "ProjectTheme");
	}
	return $mem_type;
}

function pt_mem_show_expiry()
{
	global $current_user;
	get_currentuserinfo();
	$uid = $current_user->ID;

	error_log("DEBUG: pt_mem_show_expiry called for user ID: " . $uid);

	$ProjectTheme_enable_membs = get_option("ProjectTheme_enable_membs");
	$tm = current_time("timestamp");
	$show_it = 0;
	$role = ProjectTheme_mems_get_current_user_role($uid);

    // Get all user roles for debugging
    $user_data = get_userdata($uid);
    $all_user_roles = $user_data ? implode(', ', $user_data->roles) : 'N/A';

	error_log("DEBUG: User Role (primary): " . $role);
    error_log("DEBUG: All User Roles: " . $all_user_roles); // Log all roles
	error_log("DEBUG: ProjectTheme_enable_membs: " . $ProjectTheme_enable_membs);

	// Check role conditions to set $show_it
	if ($role == "service_provider") {
		$ProjectTheme_free_mode_freelancers = get_option("ProjectTheme_free_mode_freelancers");
		if ($ProjectTheme_free_mode_freelancers == "paid") {
			$show_it = 1;
		}
	} elseif ($role == "investor") {
        // For investors, always show membership info if memberships are enabled.
        // The upgrade link logic inside will handle free/paid distinction.
        $show_it = 1;
	} elseif ($role == "business_owner") {
		$ProjectTheme_free_mode_buyers = get_option("ProjectTheme_free_mode_buyers");
		if ($ProjectTheme_free_mode_buyers == "paid") {
			$show_it = 1;
		}
	} else {
		$show_it = 1; // For other roles, we set $show_it to 1
	}
	error_log("DEBUG: show_it flag (after role check): " . $show_it);

    // Check current user capabilities for debugging
    $can_edit_others_pages = current_user_can("edit_others_pages") ? 'true' : 'false';
    error_log("DEBUG: current_user_can('edit_others_pages'): " . $can_edit_others_pages);

	// Display membership information only if conditions are met
	if ($ProjectTheme_enable_membs == "yes" && !current_user_can("edit_others_pages") && $show_it == 1) {
        error_log("DEBUG: Display conditions met. Proceeding to render membership info.");

		$membership_available = get_user_meta($uid, "membership_available", true);
        $membership_type = projecttheme_membership($uid); // Get the current membership type name

        error_log("DEBUG: Membership Type (from user meta): '" . $membership_type . "'");
        error_log("DEBUG: Membership Available (timestamp): " . $membership_available . " (Current: " . $tm . ")");


        // Determine the correct renewal/upgrade link based on user role
        $renewal_link = '';
        if ($role == 'business_owner') {
            $renewal_link = get_bloginfo('siteurl') . '/entrepreneur-membership/';
        } elseif ($role == 'investor') {
            $renewal_link = get_bloginfo('siteurl') . '/investor-membership/'; // Link to the main investor membership page for upgrade
        } else {
            $renewal_link = get_bloginfo('siteurl') . '/?p_action=get_new_mem';
        }


		echo '<div class="alert alert-info">';

		if ($membership_available < $tm) {
            error_log("DEBUG: Membership expired.");
			echo sprintf(
					__(
						'Your membership is expired, <a href="%s">click here</a> to renew your membership.',
						"ProjectTheme"
					),
					$renewal_link // Use the dynamically determined link
				);
		} else {
			// Membership is active
            error_log("DEBUG: Membership active.");
			echo sprintf(
				__("Your membership is active and will expire on %s.", "ProjectTheme"),
				date_i18n("d-M-Y H:i:s", $membership_available)
			);
			echo "<br/>";

            // Display bids/projects based on role
			if ($role == "service_provider" || $role == "investor") { // Now both service_provider and investor show "bids"
				$monthly_nr_of_bids = get_user_meta($uid, "projectTheme_monthly_nr_of_bids", true);
				echo sprintf(__("You have %s bids left.", "ProjectTheme"), $monthly_nr_of_bids);
			} elseif ($role == "business_owner") {
				$monthly_nr_of_projects = get_user_meta($uid, "projectTheme_monthly_nr_of_projects", true);
				echo sprintf(__("You have %s projects left.", "ProjectTheme"), $monthly_nr_of_projects);
			}

			echo "<br/>";
			echo sprintf(__("Membership Type: %s", "ProjectTheme"), $membership_type);

            // --- PULLING FROM USER META, WHICH WILL BE SET FROM NEW ADMIN OPTIONS ---
            $connect_credits = get_user_meta($uid, "pt_connect_credits", true);
            $zoom_invites = get_user_meta($uid, "projecttheme_monthly_zoom_invites", true);

            if ($connect_credits !== '' && $connect_credits !== false) {
                echo "<br/>";
                echo sprintf(__("CONNECTS Credits: %s", "ProjectTheme"), $connect_credits);
            }
            if ($zoom_invites !== '' && $zoom_invites !== false) {
                echo "<br/>";
                echo sprintf(__("ZOOM Invites: %s", "ProjectTheme"), $zoom_invites);
            }
            // --- END PULLING FROM USER META ---


            // --- NEW/FIXED LOGIC: Add upgrade option for free/trial plans ---
            $is_free_or_trial_plan = false;

            // 1. Check if the current membership type is explicitly "Trial Membership"
            if ($membership_type == __('Trial Membership', 'pt_mem')) {
                $is_free_or_trial_plan = true;
                error_log("DEBUG: Identified as 'Trial Membership'.");
            } else {
                // 2. If not a trial, and it's an investor, check if the current membership name corresponds to a free investor plan
                if ($role == 'investor') {
                    for ($k = 1; $k <= 6; $k++) {
                        $plan_name_monthly = get_option("pt_investor_membership_name_" . $k);
                        $plan_cost_monthly = get_option("pt_investor_membership_cost_" . $k);

                        $plan_name_yearly = get_option("pt_investor_membership_name_yearly_" . $k);
                        $plan_cost_yearly = get_option("pt_investor_membership_cost_yearly_" . $k);

                        error_log("DEBUG: Checking Investor Plan ID " . $k . ":");
                        error_log("DEBUG:   Monthly Name: '" . $plan_name_monthly . "', Cost: " . $plan_cost_monthly);
                        error_log("DEBUG:   Yearly Name: '" . $plan_name_yearly . "', Cost: " . $plan_cost_yearly);

                        // Check monthly version
                        if ($membership_type == $plan_name_monthly && $plan_cost_monthly == 0) {
                            $is_free_or_trial_plan = true;
                            error_log("DEBUG: Matched free monthly investor plan: '" . $plan_name_monthly . "'");
                            break;
                        }
                        // Check yearly version
                        if ($membership_type == $plan_name_yearly && $plan_cost_yearly == 0) {
                            $is_free_or_trial_plan = true;
                            error_log("DEBUG: Matched free yearly investor plan: '" . $plan_name_yearly . "'");
                            break;
                        }
                    }
                }
            }

            if ($is_free_or_trial_plan) {
                error_log("DEBUG: is_free_or_trial_plan is TRUE. Displaying upgrade link.");
                echo "<br/>";
                echo sprintf(
                    __('You are on a free/trial membership. <a href="%s">Upgrade your membership here</a>.', "ProjectTheme"),
                    $renewal_link // This links to the main investor membership page.
                );
            } else {
                error_log("DEBUG: is_free_or_trial_plan is FALSE. No upgrade link displayed.");
            }
            // --- END NEW/FIXED LOGIC ---

		}
		echo "</div>"; // Close alert div
	} else {
		error_log("DEBUG: Display conditions NOT met for user " . $uid . ". ProjectTheme_enable_membs: " . $ProjectTheme_enable_membs . ", current_user_can('edit_others_pages'): " . $can_edit_others_pages . ", show_it: " . $show_it . ")"); // For debugging
	}
}

/**
 * Displays CONNECTS and INVITE credits for all logged-in users on their dashboard.
 * This function is now part of the `link2investors-custom-features.php` plugin.
 * Keeping it here for reference but it's hooked in the other plugin.
 */
function pt_display_user_credits() {
    if (!is_user_logged_in() || current_user_can('edit_others_pages')) {
        return; // Only show for logged-in non-admin users on the frontend dashboard
    }

    $uid = get_current_user_id();
    $output_credits = false; // Flag to check if any credits are displayed

    echo '<div class="alert alert-info">'; // Use an alert box for consistent styling

    // Display CONNECTS Credits
    if (function_exists('pt_get_connect_credits')) {
        $connect_credits = pt_get_connect_credits($uid);
        echo sprintf(__("CONNECTS Credits: %s", "pt-custom"), $connect_credits);
        $output_credits = true;
    }

    // Display INVITE Credits (Zoom)
    if (function_exists('pt_get_invite_credits')) {
        if ($output_credits) {
            echo "<br/>"; // Add a line break if CONNECTS credits were already displayed
        }
        $invite_credits = pt_get_invite_credits($uid);
        echo sprintf(__("INVITE Credits (Zoom): %s", "pt-custom"), $invite_credits);
        $output_credits = true;
    }

    echo "</div>"; // Close alert div
}


function projecttheme_is_user_able_to_access($uid, $pid)
{
	if (!is_user_logged_in()) {
		return false;
	}
	$post = get_post($pid);

	if ($post->post_author != $uid) {
		$ProjectTheme_free_mode_freelancers = get_option("ProjectTheme_free_mode_freelancers");
		if ($ProjectTheme_free_mode_freelancers == "paid") {
			$membership_available = get_user_meta($uid, "membership_available", true);
			$tm = current_time("timestamp");
			if ($membership_available < $tm) {
				return false;
			}
		}
	}

	return true;
}

/********************************************************
 *
 *			function
 *
********************************************************/
// Filter for ProjectTheme_is_it_allowed_post_projects is already added above.

function ProjectTheme_is_it_allowed_post_projects_fn($al)
{
	$current_user = wp_get_current_user();
	$uid = $current_user->ID;

    // The restriction logic for actions remains here
    if (function_exists('l2i_is_restricted_member') && l2i_is_restricted_member($uid)) {
        return false; // Restricted members cannot post projects
    }

	$ProjectTheme_enable_membs = get_option("ProjectTheme_enable_membs");

	if ($ProjectTheme_enable_membs == "yes") {
		$ProjectTheme_free_mode_buyers = get_option("ProjectTheme_free_mode_buyers");
		$projectTheme_monthly_nr_of_projects = get_user_meta($uid, "projectTheme_monthly_nr_of_projects", true);
		$membership_available = get_user_meta($uid, "membership_available", true);

		$tm = current_time("timestamp");

		if ($ProjectTheme_free_mode_buyers != "free") {
			if ($membership_available < $tm or $projectTheme_monthly_nr_of_projects == 0) {
				return false;
			}
		}
	}

	return true;
}
/********************************************************
 *
 *			function
 *
********************************************************/
add_filter("ProjectTheme_post_project_not_allowed_message", "pt_post_projects_err");

function pt_post_projects_err()
{
	$lnk = get_bloginfo("siteurl") . "/?p_action=get_new_mem";
	echo '<div class="padd10"><div class="padd10">';
    // The restriction logic for messages remains here
    if (function_exists('l2i_is_restricted_member') && l2i_is_restricted_member(get_current_user_id())) {
        echo __('Your membership level does not allow you to post projects. Upgrade to unlock this feature.', 'link2investors');
    } else {
	    echo sprintf(
		    __(
			    'Your membership does not have anymore projects left. You need to renew your subscription. <a href="%s">Click here</a>.',
			    "pt_mem"
		    ),
		    $lnk
	    );
    }
	echo "</div></div>";
}

/********************************************************
 *
 *			function
 *
 ********************************************************/

function ProjectTheme_when_creating_auto_draft_ff()
{
	global $current_user;
	get_currentuserinfo();
	$uid = $current_user->ID;

    // The restriction logic for actions remains here
    if (function_exists('l2i_is_restricted_member') && l2i_is_restricted_member($uid)) {
        return; // Restricted members cannot create auto-drafts for projects
    }

	$projectTheme_monthly_nr_of_projects = get_user_meta($uid, "projectTheme_monthly_nr_of_projects", true);
	if (empty($projectTheme_monthly_nr_of_projects)) {
		$new = 0;
	} else {
		$new = $projectTheme_monthly_nr_of_projects - 1;
	}
	update_user_meta($uid, "projectTheme_monthly_nr_of_projects", $new);
}
/********************************************************
 *
 *			function
 *
********************************************************/

function ProjectTheme_display_bidding_panel_mms($pid)
{
	global $current_user;
	get_currentuserinfo();
	$uid = $current_user->ID;

    // The restriction logic for actions remains here
    if (function_exists('l2i_is_restricted_member') && l2i_is_restricted_member($uid)) {
        echo '<div class="alert alert-danger"> ';
        echo __('Your membership level does not allow you to place bids. Upgrade to unlock this feature.', 'link2investors');
        echo "</div> ";
        return;
    }

	$ProjectTheme_free_mode_freelancers = get_option("ProjectTheme_free_mode_freelancers");

	$projectTheme_monthly_nr_of_bids = get_user_meta($uid, "projectTheme_monthly_nr_of_bids", true);
	$ProjectTheme_enable_membs = get_option("ProjectTheme_enable_membs");

	if (
		$projectTheme_monthly_nr_of_bids <= 0 and
		$ProjectTheme_enable_membs == "yes" and
		$ProjectTheme_free_mode_freelancers == "paid"
	) {
		$lnk = get_bloginfo("siteurl") . "/?p_action=get_new_mem";
		echo '<div class="alert alert-danger"> ';
		echo sprintf(
			__(
				'Your membership does not have anymore bids left. You need to renew your subscription. <a href="%s">Click here</a>.',
				"pt_mem"
			),
			$lnk
		);
		echo "</div> ";
	}
}

function ProjectTheme_can_post_bids_anymore($pid = "")
{
	global $current_user;
	get_currentuserinfo();
	$uid = $current_user->ID;

    // The restriction logic for actions remains here
    if (function_exists('l2i_is_restricted_member') && l2i_is_restricted_member($uid)) {
        return "no"; // Restricted members cannot post bids
    }

	$ProjectTheme_free_mode_freelancers = get_option("ProjectTheme_free_mode_freelancers");

	$projectTheme_monthly_nr_of_bids = get_user_meta($uid, "projectTheme_monthly_nr_of_bids", true);
	$ProjectTheme_enable_membs = get_option("ProjectTheme_enable_membs");

	if (
		$projectTheme_monthly_nr_of_bids <= 0 and
		$ProjectTheme_enable_membs == "yes" and
		$ProjectTheme_free_mode_freelancers == "paid"
	) {
		return "no";
	}

	return "yes";
}
/********************************************************
 *
 *			function
 *
********************************************************/

function ProjectTheme_post_bid_ok_action_mem_fncs()
{
	global $current_user;
	get_currentuserinfo();
	$uid = $current_user->ID;

    // The restriction logic for actions remains here
    if (function_exists('l2i_is_restricted_member') && l2i_is_restricted_member($uid)) {
        return; // Restricted members cannot deduct bids
    }

	$projectTheme_monthly_nr_of_bids = get_user_meta($uid, "projectTheme_monthly_nr_of_bids", true);
	update_user_meta($uid, "projectTheme_monthly_nr_of_bids", $projectTheme_monthly_nr_of_bids - 1);
}
/********************************************************
 *
 *			function
 *
********************************************************/

function ProjectTheme_mems_get_current_user_role($uid)
{
	$user_data = get_userdata($uid);
	$user_roles = $user_data->roles;

	if (is_array($user_roles)) {
		$user_role = array_shift($user_roles);
	}

	return $user_role;
}
/********************************************************
 *
 *			function
 *
********************************************************/

// This function is intentionally left empty.
// Its logic has been moved to ProjectTheme_handle_paction_requests hooked to template_redirect.
function ProjectTheme_early_paction_handler() { }

/**
 * Handles custom 'p_action' requests by including the appropriate file.
 * Hooked to 'template_redirect' to ensure WordPress core is fully loaded.
 */
function ProjectTheme_handle_paction_requests() {
    if (isset($_GET["p_action"])) {
        $p_action = sanitize_text_field($_GET["p_action"]);

        // Define base paths for includes
        $purchase_base_path = plugin_dir_path(__FILE__) . "purchase/";
        $root_base_path = plugin_dir_path(__FILE__); // For files directly in plugin root

        $action_to_file_map = [
            "purchase_membership_skrill"            => ["path" => $purchase_base_path, "file" => "purchase_membership_skrill.php"],
            "purchase_membership_paypal"            => ["path" => $purchase_base_path, "file" => "purchase_membership_paypal.php"],
            "purchase_membership_service_provider"  => ["path" => $purchase_base_path, "file" => "purchase_membership_service_provider.php"],
            "purchase_membership_display"           => ["path" => $purchase_base_path, "file" => "purchase_membership_display.php"], // Unified display
            "purchase_membership_clover_unified"    => ["path" => $purchase_base_path, "file" => "purchase_membership_clover.php"],
            "clover_payment_success"                => ["path" => $purchase_base_path, "file" => "purchase_membership_clover.php"],
            "skrill_membership_payment_response"    => ["path" => $purchase_base_path, "file" => "skrill_membership_payment_response.php"],
            "purchase_membership_paypal_response"   => ["path" => $purchase_base_path, "file" => "paypal_membership_payment_response.php"],
            "paypal_membership_mem"                 => ["path" => $purchase_base_path, "file" => "paypal_membership_mem.php"],
            "mb_membership_mem"                     => ["path" => $purchase_base_path, "file" => "mb_membership_mem.php"],
            "mb_deposit_response_mem"               => ["path" => $purchase_base_path, "file" => "mb_deposit_response_mem.php"],
            "credits_listing_mem"                   => ["path" => $root_base_path, "file" => "credits_listing_mem.php"], // CRITICAL FIX: Point to plugin root directory
            "get_new_mem"                           => ["path" => $root_base_path, "file" => "get_new_mem.php"], // CRITICAL FIX: Point to plugin root directory
        ];

        // Handle redirects for old actions to unified pages
        // These redirects will now happen *after* WordPress is fully loaded.
        if ($p_action == "purchase_membership_investor") {
            $new_params = array_merge($_GET, ['mem_type' => 'investor', 'p_action' => 'purchase_membership_display']);
            if (!isset($new_params['plan_type'])) {
                $new_params['plan_type'] = 'monthly';
            }
            wp_redirect(add_query_arg($new_params, get_bloginfo("siteurl") . '/'));
            exit();
        }
        if ($p_action == "purchase_membership_buyer") {
            $new_params = array_merge($_GET, ['mem_type' => 'project_owner', 'p_action' => 'purchase_membership_display']);
            wp_redirect(add_query_arg($new_params, get_bloginfo("siteurl") . '/'));
            exit();
        }
        if ($p_action == "purchase_membership_clover_buyer") {
            $new_params = array_merge($_GET, ['mem_type' => 'project_owner', 'p_action' => 'purchase_membership_clover_unified']);
            wp_redirect(add_query_arg($new_params, get_bloginfo("siteurl") . '/'));
            exit();
        }
        if ($p_action == "purchase_membership_clover_investor") {
            $new_params = array_merge($_GET, ['mem_type' => 'investor', 'p_action' => 'purchase_membership_clover_unified']);
            if (!isset($new_params['plan_type'])) {
                $new_params['plan_type'] = 'monthly';
            }
            wp_redirect(add_query_arg($new_params, get_bloginfo("siteurl") . '/'));
            exit();
        }

        // Include the file if a valid p_action is found
        if (array_key_exists($p_action, $action_to_file_map)) {
            $file_info = $action_to_file_map[$p_action];
            $file_to_include = $file_info["path"] . $file_info["file"];

            if (file_exists($file_to_include)) {
                include $file_to_include;
                exit();
            } else {
                error_log("ProjectTheme_handle_paction_requests: File not found for p_action '{$p_action}': {$file_to_include}");
            }
        }
    }

    // Handle 'get_free_membership' separately
    if (isset($_GET["get_free_membership"])) {
        if (is_user_logged_in()) {
            $uid = get_current_user_id();

            if (function_exists('l2i_is_restricted_member') && l2i_is_restricted_member($uid)) {
                wp_redirect(get_permalink(get_option("ProjectTheme_my_account_page_id")) . "?error=restricted_membership");
                exit();
            }

            $i = isset($_GET["get_free_membership"]) ? intval($_GET["get_free_membership"]) : 0;
            $plan_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'monthly';

            if ($i <= 0 || $i > 6) {
                wp_redirect(get_permalink(get_option("ProjectTheme_my_account_page_id")) . "?error=invalid_membership_id");
                exit();
            }

            $user_role = ProjectTheme_mems_get_current_user_role($uid);
            $cost_option_name = '';
            $time_option_name = '';
            $items_option_name = ''; // For bids/projects
            $membership_name_option_name = '';
            $zoom_invites_option_name = ''; // For zoom invites
            $connect_credits_option_name = ''; // For connects credits


            switch ($user_role) {
                case 'service_provider':
                    $cost_option_name = 'pt_freelancer_membership_cost_';
                    $time_option_name = 'pt_freelancer_membership_time_';
                    $items_option_name = 'pt_freelancer_membership_bids_';
                    $membership_name_option_name = 'pt_freelancer_membership_name_';
                    $zoom_invites_option_name = 'pt_freelancer_membership_zoom_invites_'; // NEW
                    $connect_credits_option_name = 'pt_freelancer_membership_connects_'; // NEW
                    break;
                case 'business_owner':
                    $cost_option_name = 'pt_project_owner_membership_cost_';
                    $time_option_name = 'pt_project_owner_membership_time_';
                    $items_option_name = 'pt_project_owner_membership_projects_';
                    $membership_name_option_name = 'pt_project_owner_membership_name_';
                    $zoom_invites_option_name = 'pt_project_owner_membership_zoom_invites_'; // NEW
                    $connect_credits_option_name = 'pt_project_owner_membership_connects_'; // NEW
                    break;
                case 'investor':
                    if ($plan_type == 'yearly') {
                        $cost_option_name = 'pt_investor_membership_cost_yearly_';
                        $time_option_name = 'pt_investor_membership_time_yearly_';
                        $items_option_name = 'pt_investor_membership_bids_yearly_'; // Bids for investors
                        $membership_name_option_name = 'pt_investor_membership_name_yearly_';
                        $zoom_invites_option_name = 'pt_investor_membership_zoom_invites_yearly_'; // NEW
                        $connect_credits_option_name = 'pt_investor_membership_connects_yearly_'; // NEW
                    } else {
                        $cost_option_name = 'pt_investor_membership_cost_';
                        $time_option_name = 'pt_investor_membership_time_';
                        $items_option_name = 'pt_investor_membership_bids_'; // Bids for investors
                        $membership_name_option_name = 'pt_investor_membership_name_';
                        $zoom_invites_option_name = 'pt_investor_membership_zoom_invites_'; // NEW
                        $connect_credits_option_name = 'pt_investor_membership_connects_'; // NEW
                    }
                    break;
                default:
                    wp_redirect(get_permalink(get_option("ProjectTheme_my_account_page_id")) . "?error=unsupported_role");
                    exit();
            }

            $membership_cost = get_option($cost_option_name . $i);

            if ($membership_cost == 0) {
                $membership_time = get_option($time_option_name . $i);
                $membership_items = get_option($items_option_name . $i); // This is for bids/projects
                $membership_name = get_option($membership_name_option_name . $i);

                // --- PULLING ZOOM INVITES AND CONNECTS FROM NEW ADMIN OPTIONS ---
                $zoom_invites = ($zoom_invites_option_name && get_option($zoom_invites_option_name . $i) !== false) ? get_option($zoom_invites_option_name . $i) : 0;
                $connect_credits = ($connect_credits_option_name && get_option($connect_credits_option_name . $i) !== false) ? get_option($connect_credits_option_name . $i) : 0;
                // --- END PULLING FROM NEW ADMIN OPTIONS ---


                if ($user_role == 'investor' && $plan_type == 'yearly') {
                    $membership_time_in_months = $membership_time * 12; // Convert years to months for internal calculation
                } else {
                    $membership_time_in_months = $membership_time;
                }

                $tm = current_time("timestamp");
                update_user_meta($uid, "membership_available", $tm + ($membership_time_in_months * 30.5 * 24 * 3600));
                update_user_meta($uid, "mem_type", $membership_name);

                // --- Initial allocation of credits/invites ---
                if ($user_role == "business_owner") {
                    update_user_meta($uid, "projectTheme_monthly_nr_of_projects", $membership_items);
                    update_user_meta($uid, "projecttheme_monthly_zoom_invites", $zoom_invites);
                    update_user_meta($uid, "pt_connect_credits", $connect_credits);
                } else { // service_provider or investor
                    update_user_meta($uid, "projectTheme_monthly_nr_of_bids", $membership_items);
                    update_user_meta($uid, "projecttheme_monthly_zoom_invites", $zoom_invites);
                    update_user_meta($uid, "pt_connect_credits", $connect_credits);
                }
                // --- Set last allocation date for cron ---
                update_user_meta($uid, 'pt_last_credit_allocation', $tm);


                update_user_meta($uid, "free_membership_exhausted", "yes");
                wp_redirect(get_permalink(get_option("ProjectTheme_my_account_page_id")) . "?success=1");
                exit();
            } else {
                wp_redirect(get_permalink(get_option("ProjectTheme_my_account_page_id")) . "?error=not_a_free_membership");
                exit();
            }

        } else {
            wp_redirect(get_site_url() . "/wp-login.php");
            exit();
        }
    }

    // Handle 'activate_membership_trial' separately
    if (isset($_GET["p_action"]) && $_GET["p_action"] == "activate_membership_trial") {
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $user_role = ProjectTheme_mems_get_current_user_role($uid);

            if (function_exists('l2i_is_restricted_member') && l2i_is_restricted_member($uid)) {
                wp_redirect(get_permalink(get_option("ProjectTheme_my_account_payments_id")) . "?error=restricted_membership");
                exit();
            }

            $tm = current_time("timestamp", 0);

            update_user_meta($uid, "trial_used", "1");
            $trial_duration_months = get_option("projectTheme_monthly_trial_period");

            update_user_meta($uid, "membership_available", $tm + ($trial_duration_months * 30.5 * 24 * 3600));
            update_user_meta($uid, "mem_type", __('Trial Membership', 'pt_mem'));

            // For trial, default to 0 for all specific credits unless there are specific trial options for them
            $default_connects = 0;
            $default_zoom_invites = 0;
            $default_bids_projects = 0; // Default for bids/projects in trial

            if ($user_role == "service_provider") {
                $default_bids_projects = get_option("projectTheme_monthly_nr_of_bids");
                if (empty($default_bids_projects)) {
                    $default_bids_projects = 10;
                }
                update_user_meta($uid, "projectTheme_monthly_nr_of_bids", $default_bids_projects);
                update_user_meta($uid, "projecttheme_monthly_zoom_invites", $default_zoom_invites);
                update_user_meta($uid, "pt_connect_credits", $default_connects);
            } elseif ($user_role == "business_owner") {
                $default_bids_projects = get_option("projectTheme_monthly_nr_of_projects");
                if (empty($default_bids_projects)) {
                    $default_bids_projects = 10;
                }
                update_user_meta($uid, "projectTheme_monthly_nr_of_projects", $default_bids_projects);
                update_user_meta($uid, "projecttheme_monthly_zoom_invites", $default_zoom_invites);
                update_user_meta($uid, "pt_connect_credits", $default_connects);
            } elseif ($user_role == "investor") {
                $default_bids_projects = get_option("projectTheme_monthly_nr_of_bids");
                if (empty($default_bids_projects)) {
                    $default_bids_projects = 0;
                }
                update_user_meta($uid, "projectTheme_monthly_nr_of_bids", $default_bids_projects);
                update_user_meta($uid, "projecttheme_monthly_zoom_invites", $default_zoom_invites);
                update_user_meta($uid, "pt_connect_credits", $default_connects);
            }
            // --- Set last allocation date for cron (for trial, if it's a "yearly" type trial) ---
            update_user_meta($uid, 'pt_last_credit_allocation', $tm);


            wp_redirect(get_permalink(get_option("ProjectTheme_my_account_payments_id")));
            exit();
        } else {
            wp_redirect(get_site_url() . "/wp-login.php");
            exit();
        }
    }
}


/********************************************************
 *
 *			function
 *
********************************************************/

function ProjectTheme_general_settings_main_details_options_save_memms()
{
	update_option("ProjectTheme_enable_membs", $_POST["ProjectTheme_enable_membs"]);
	update_option("projectTheme_monthly_service_provider", $_POST["projectTheme_monthly_service_provider"]);
	update_option("projectTheme_monthly_service_contractor", $_POST["projectTheme_monthly_service_contractor"]);
	update_option("projectTheme_monthly_trial_period", $_POST["projectTheme_monthly_trial_period"]);

	update_option("projectTheme_monthly_nr_of_bids", $_POST["projectTheme_monthly_nr_of_bids"]);
	update_option("projectTheme_monthly_nr_of_projects", $_POST["projectTheme_monthly_nr_of_projects"]);
}

/********************************************************
 *
 *			function
 *
********************************************************/

add_filter("ProjectTheme_admin_menu_add_item", "ProjectTheme_admin_menu_add_item_memb");

function ProjectTheme_admin_menu_add_item_memb()
{
	$capability = 10;
	global $projecthememnupg;
	$advs = "add" . "_" . "menu" . "_" . "page";

	$projecthememnupg(
		"project_theme_mnu",
		__("Memberships", "ProjectTheme"),
		'<i class="fas fa-university"></i> ' . __("Memberships", "ProjectTheme"),
		$capability,
		"Memberships",
		"projectTheme_theme_memberships"
	);
	$projecthememnupg(
		"project_theme_mnu",
		__("Coupons", "ProjectTheme"),
		'<i class="fas fa-university"></i> ' . __("Coupons", "ProjectTheme"),
		$capability,
		"Coupons",
		"projectTheme_theme_membership_coupons"
	);
}

// COUPONS HERE

function projectTheme_theme_membership_coupons()
{
	$id_icon = "icon-options-general-layout";
	$ttl_of_stuff = "ProjectTheme - " . __("Coupons", "ProjectTheme");
	global $menu_admin_project_theme_bull;

	$arr = ["yes" => __("Yes", "ProjectTheme"), "no" => __("No", "ProjectTheme")];
	$arr3 = ["free" => __("FREE", "ProjectTheme"), "paid" => __("PAID", "ProjectTheme")];

	echo '<div class="wrap">';
	echo '<div class="icon32" id="' . $id_icon . '"><br/></div>';
	echo '<h2 class="my_title_class_sitemile">' . $ttl_of_stuff . "</h2>";

	global $wpdb;

	if (isset($_GET["deletecp"])) {
		$id = sanitize_text_field($_GET["deletecp"]);

		$s = "delete from " . $wpdb->prefix . "project_membership_coupons where id='$id'";
		$r = $wpdb->query($s);

		echo '<div class="saved_thing">Your coupon was deleted.</div>';
	}

	if (isset($_POST["add_coupon_mem"])) {
		//project_membership_coupons
		$s = "select * from " . $wpdb->prefix . "project_membership_coupons where coupon_code='$coupon_code'";
		$r = $wpdb->get_results($s);

		$coupon_code = sanitize_text_field($_POST["coupon_code"]);
		$discount_amount = sanitize_text_field($_POST["discount_amount"]);

		if (count($r) == 0) {
			$s =
				"insert into " .
				$wpdb->prefix .
				"project_membership_coupons (coupon_code, discount_amount) values('$coupon_code','$discount_amount')";
			$wpdb->query($s);
		}

		echo '<div class="saved_thing">Your coupon was added.</div>';
	}
	?>

<div id="usual2" class="usual">
	<ul>
		<li>
			<a href="#tabs1"><?php _e("Add new coupon", "ProjectTheme"); ?></a>
		</li>
		<li>
			<a href="#tabs2"><?php _e("Current Active Coupons", "ProjectTheme"); ?></a>
		</li>

	</ul>

	<div id="tabs1">
		<form method="post" action="<?php echo get_admin_url(); ?>admin.php?page=Coupons&active_tab=tabs1">
			<table width="100%" class="sitemile-table">

				<tr>
					<td width="160">Coupon Code:
					</td>
					<td><input required="required" type="text" size="26" name="coupon_code" /></td>
				</tr>

				<tr>
					<td>Discount Amount %:
					</td>
					<td><input required="required" type="number" size="26" name="discount_amount" /></td>
				</tr>

				<tr>
					<td></td>
					<td><input type="submit" size="26" name="add_coupon_mem" value="Add Coupon" /></td>
				</tr>

			</table>
		</form>
	</div>

	<div id="tabs2">
		<?php
		$s = "select * from " . $wpdb->prefix . "project_membership_coupons  ";
		$r = $wpdb->get_results($s);

		if (count($r) > 0) { ?>

		<style>
			.mytable td {
				border-bottom: 1px solid #ddd;
				padding: 10px;
				background-color: #fefefe;
			}
		</style>

		<table class="mytable" style="width: 50%">
			<thead>
				<tr>
					<td>
						<b>Coupon Code</b>
					</td>
					<td>
						Discount Amount
					</td>
					<td></td>

				</tr>
			</thead>

			<?php foreach ($r as $row) { ?>

			<tr>
				<td>
					<b><?php echo $row->coupon_code; ?></b>
				</td>
				<td>
					<?php echo $row->discount_amount; ?>%
				</td>
				<td>
					<a
						href="<?php echo admin_url(); ?>/admin.php?page=Coupons&active_tab=tabs1&deletecp=<?php echo $row->id; ?>">Delete
						Coupon</a>
				</td>
			</tr>
			<?php } ?>

		</table>

		<?php } else {echo "<p>No coupons added</p>";}
		?>

	</div>
</div>
<?php echo "</div>";
}

/********************************************************
 *
 *			My function
 *
********************************************************/

add_filter("ProjectTheme_on_success_registration", "ProjectTheme_on_success_registration_redirect");

function ProjectTheme_on_success_registration_redirect($user_login)
{
	$opt = get_option("projectTheme_admin_approves_each_user");
	//if ( $opt == "yes" ) return;

	$ProjectTheme_enable_membs = get_option("ProjectTheme_enable_membs");
	$ProjectTheme_redirect_mems = get_option("ProjectTheme_redirect_mems");
	$ProjectTheme_free_mode_buyers = get_option("ProjectTheme_free_mode_buyers");
	$ProjectTheme_free_mode_freelancers = get_option("ProjectTheme_free_mode_freelancers");

	$usr = get_user_by("login", $user_login);
	$uid = $usr->ID;

	$creds = [
		"user_login" => $user_login,
		"user_password" => $_POST["password"],
		"remember" => true,
	];

	wp_signon($creds, false);

	if ($ProjectTheme_redirect_mems == "yes" and $ProjectTheme_enable_membs == "yes") {
		if (pt_get_user_role_membership($uid) == "service_provider" and $ProjectTheme_free_mode_freelancers == "paid") {
			wp_redirect(get_permalink(get_option("ProjectTheme_page_to_redirect_mems_freelancer")));
			exit();
		}

		if (pt_get_user_role_membership($uid) == "business_owner" and $ProjectTheme_free_mode_buyers == "paid") {
			wp_redirect(get_permalink(get_option("ProjectTheme_page_to_redirect_mems_buyer")));
			exit();
		}
	}
}

/********************************************************
 *
 *			function
 *
********************************************************/

function pt_get_user_role_membership($uid)
{
	$user_data = get_userdata($uid);
	$user_roles = $user_data->roles;

	if (is_array($user_roles)) {
		$user_role = array_shift($user_roles);
	}

	return $user_role;
}

/********************************************************
 *
 *			function
 *
********************************************************/

function projectTheme_theme_memberships()
{
	$id_icon = "icon-options-general-layout";
	$ttl_of_stuff = "ProjectTheme - " . __("Memberships", "ProjectTheme");
	global $menu_admin_project_theme_bull;

	$arr = ["yes" => __("Yes", "ProjectTheme"), "no" => __("No", "ProjectTheme")];
	$arr3 = ["free" => __("FREE", "ProjectTheme"), "paid" => __("PAID", "ProjectTheme")];

	echo '<div class="wrap">';
	echo '<div class="icon32" id="' . $id_icon . '"><br/></div>';
	echo '<h2 class="my_title_class_sitemile">' . $ttl_of_stuff . "</h2>";

	if (isset($_POST["my_submit1"])) {
		update_option("ProjectTheme_enable_membs", $_POST["ProjectTheme_enable_membs"]);
		update_option("ProjectTheme_redirect_mems", $_POST["ProjectTheme_redirect_mems"]);
		update_option(
			"ProjectTheme_page_to_redirect_mems_freelancer",
			$_POST["ProjectTheme_page_to_redirect_mems_freelancer"]
		);
		update_option("ProjectTheme_page_to_redirect_mems_buyer", $_POST["ProjectTheme_page_to_redirect_mems_buyer"]);

		update_option("ProjectTheme_free_mode_buyers", $_POST["ProjectTheme_free_mode_buyers"]);
		update_option("ProjectTheme_free_mode_freelancers", $_POST["ProjectTheme_free_mode_freelancers"]);
		update_option("ProjectTheme_free_mode_investors", $_POST["ProjectTheme_free_mode_investors"]);

		echo '<div class="saved_thing">Settings were saved!</div>';
	}

	if (isset($_POST["my_submit2"])) {
		for ($i = 1; $i <= 6; $i++) {
			update_option("pt_freelancer_membership_name_" . $i, $_POST["pt_freelancer_membership_name_" . $i]);
			update_option("pt_freelancer_membership_cost_" . $i, $_POST["pt_freelancer_membership_cost_" . $i]);
			update_option("pt_freelancer_membership_time_" . $i, $_POST["pt_freelancer_membership_time_" . $i]);
			update_option("pt_freelancer_membership_bids_" . $i, $_POST["pt_freelancer_membership_bids_" . $i]);
            update_option("pt_freelancer_membership_connects_" . $i, $_POST["pt_freelancer_membership_connects_" . $i]); // NEW
            update_option("pt_freelancer_membership_zoom_invites_" . $i, $_POST["pt_freelancer_membership_zoom_invites_" . $i]); // NEW
		}

		echo '<div class="saved_thing">Settings were saved!</div>';
	}

	if (isset($_POST["my_submit4"])) {
		for ($i = 1; $i <= 6; $i++) {
			// Monthly options
			update_option("pt_investor_membership_name_" . $i, $_POST["pt_investor_membership_name_" . $i]);
			update_option("pt_investor_membership_cost_" . $i, $_POST["pt_investor_membership_cost_" . $i]);
			update_option("pt_investor_membership_time_" . $i, $_POST["pt_investor_membership_time_" . $i]);
			update_option("pt_investor_membership_bids_" . $i, $_POST["pt_investor_membership_bids_" . $i]);
            update_option("pt_investor_membership_connects_" . $i, $_POST["pt_investor_membership_connects_" . $i]); // NEW
            update_option("pt_investor_membership_zoom_invites_" . $i, $_POST["pt_investor_membership_zoom_invites_" . $i]); // NEW
            for ($f = 1; $f <= 5; $f++) {
                update_option("pt_investor_membership_feature" . $f . "_" . $i, $_POST["pt_investor_membership_feature" . $f . "_" . $i]);
            }

            // Yearly options
            update_option("pt_investor_membership_name_yearly_" . $i, $_POST["pt_investor_membership_name_yearly_" . $i]);
            update_option("pt_investor_membership_cost_yearly_" . $i, $_POST["pt_investor_membership_cost_yearly_" . $i]);
            update_option("pt_investor_membership_time_yearly_" . $i, $_POST["pt_investor_membership_time_yearly_" . $i]);
            update_option("pt_investor_membership_bids_yearly_" . $i, $_POST["pt_investor_membership_bids_yearly_" . $i]);
            update_option("pt_investor_membership_connects_yearly_" . $i, $_POST["pt_investor_membership_connects_yearly_" . $i]); // NEW
            update_option("pt_investor_membership_zoom_invites_yearly_" . $i, $_POST["pt_investor_membership_zoom_invites_yearly_" . $i]); // NEW
            for ($f = 1; $f <= 5; $f++) {
                update_option("pt_investor_membership_feature" . $f . "_yearly_" . $i, $_POST["pt_investor_membership_feature" . $f . "_yearly_" . $i]);
            }
		}

		echo '<div class="saved_thing">Settings were saved!</div>';
	}

	if (isset($_POST["my_submit3"])) {
		for ($i = 1; $i <= 6; $i++) {
			update_option("pt_project_owner_membership_name_" . $i, $_POST["pt_project_owner_membership_name_" . $i]);
			update_option("pt_project_owner_membership_cost_" . $i, $_POST["pt_project_owner_membership_cost_" . $i]);
			update_option("pt_project_owner_membership_time_" . $i, $_POST["pt_project_owner_membership_time_" . $i]);
			update_option(
				"pt_project_owner_membership_projects_" . $i,
				$_POST["pt_project_owner_membership_projects_" . $i]
			);
            update_option("pt_project_owner_membership_connects_" . $i, $_POST["pt_project_owner_membership_connects_" . $i]); // NEW
            update_option("pt_project_owner_membership_zoom_invites_" . $i, $_POST["pt_project_owner_membership_zoom_invites_" . $i]); // NEW
		}

		echo '<div class="saved_thing">Settings were saved!</div>';
	}
	?>

<div id="usual2" class="usual">
	<ul>
		<li>
			<a href="#tabs1"><?php _e("Options", "ProjectTheme"); ?></a>
		</li>
		<li>
			<a href="#tabs2"><?php _e("Professional Service Provide", "ProjectTheme"); ?></a>
		</li>
		<li>
			<a href="#tabs3"><?php _e("Entrepreneur", "ProjectTheme"); ?></a>
		</li>
		<li>
			<a href="#tabs4" <?php echo isset($_GET["activate_tab"]) && $_GET["activate_tab"] == "tabs4" ? "class='selected'" : ""; ?>><?php _e(
	"Investor",
	"ProjectTheme"
); ?></a>
		</li>

	</ul>

	<div id="tabs1">
		<form method="post" action="<?php echo get_admin_url(); ?>admin.php?page=Memberships&active_tab=tabs1">
			<table width="100%" class="sitemile-table">
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width="190">Enable Memberships:</td>
					<td><?php echo ProjectTheme_get_option_drop_down($arr, "ProjectTheme_enable_membs"); ?></td>
				</tr>
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width="190">Customers use the site:</td>
					<td><?php echo ProjectTheme_get_option_drop_down($arr3, "ProjectTheme_free_mode_buyers"); ?>
						- makes so the buyer/customers can use the site for free</td>
				</tr>
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width="190">Freelancers use the site:</td>
					<td><?php echo ProjectTheme_get_option_drop_down($arr3, "ProjectTheme_free_mode_freelancers"); ?>
						- makes so the freelancers can use the site for free</td>
				</tr>
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width="190">Investors use the site:</td>
					<td><?php echo ProjectTheme_get_option_drop_down($arr3, "ProjectTheme_free_mode_investors"); ?>
						- makes so the investors can use the site for free</td>
				</tr>
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width="190">Redirect on register:</td>
					<td><?php echo ProjectTheme_get_option_drop_down($arr, "ProjectTheme_redirect_mems"); ?>
						- redirect the user to membership page after register.</td>
				</tr>
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width="190">Page to redirect( freelancer ):</td>
					<td>
						<select name="ProjectTheme_page_to_redirect_mems_freelancer">
							<option value="">Select</option>
							<?php
		$args = [
			"post_type" => "page",

			"posts_per_page" => "-1",
			"orderby" => "name",
			"order" => "asc",
			"post_status" => "publish",
		];
		$pages = get_posts($args);

		$red = get_option("ProjectTheme_page_to_redirect_mems_freelancer");

		foreach ($pages as $page) {
			echo "<option " .
				($page->ID == $red ? "selected='selected'" : "") .
				' value="' .
				$page->ID .
				'">' .
				$page->post_title .
				"</option>";
    }
    ?>
						</select>
					</td>
				</tr>
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width="190">Page to redirect( buyer ):</td>
					<td>
						<select name="ProjectTheme_page_to_redirect_mems_buyer">
							<option value="">Select</option>
							<?php
    $args = [
    	"post_type" => "page",

    	"posts_per_page" => "-1",
    	"orderby" => "name",
    	"order" => "asc",
    	"post_status" => "publish",
    ];
    $pages = get_posts($args);

    $red = get_option("ProjectTheme_page_to_redirect_mems_buyer");

    foreach ($pages as $page) {
    	echo "<option " .
    		($page->ID == $red ? "selected='selected'" : "") .
    		' value="' .
    		$page->ID .
    		'">' .
    		$page->post_title .
    		"</option>";
    }
    ?>
						</select>
					</td>
				</tr>
				<tr>
					<td></td>
					<td></td>
					<td><input type="submit" class="button button-primary button-large" name="my_submit1"
							value="Save these Settings!"></td>
				</tr>

			</table>
		</form>

	</div>

	<div id="tabs3">
		<form method="post" action="<?php echo get_admin_url(); ?>admin.php?page=Memberships&active_tab=tabs3">
			<table width="100%" class="sitemile-table">

				<tr>
					<td colspan="3">Shortcode to use on a page to display the packages:
						<b>[pt_display_buyer_mem_packs]</b>
					</td>
				</tr>

				<tr>
					<td colspan="3">To set the membership FREE, set the price 0</td>
				</tr>

				<?php for ($i = 1; $i <= 6; $i++) { ?>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width='260'>
						Project Owner Membership #<?php echo $i; ?>
						Name:</td>
					<td><input type="text" name='pt_project_owner_membership_name_<?php echo $i; ?>' size="24"
							value="<?php echo get_option("pt_project_owner_membership_name_" . $i); ?>" />
					</td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Project Owner Membership #<?php echo $i; ?>
						Cost:</td>
					<td><input type="text" name='pt_project_owner_membership_cost_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_project_owner_membership_cost_" . $i); ?>" />
						<?php echo projecttheme_get_currency(); ?></td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Project Owner Membership #<?php echo $i; ?>
						Time:</td>
					<td><input type="text" name='pt_project_owner_membership_time_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_project_owner_membership_time_" . $i); ?>" />
						months</td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Project Owner Membership #<?php echo $i; ?>
						Projects:</td>
					<td><input type="text" name='pt_project_owner_membership_projects_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_project_owner_membership_projects_" . $i); ?>" />
						projects
					</td>
				</tr>

                <tr>
                    <td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
                    <td>
                        Project Owner Membership #<?php echo $i; ?>
                        Connects:</td>
                    <td><input type="number" name='pt_project_owner_membership_connects_<?php echo $i; ?>' size="4"
                            value="<?php echo get_option("pt_project_owner_membership_connects_" . $i, 0); ?>" />
                        connects
                    </td>
                </tr>

                <tr>
                    <td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
                    <td>
                        Project Owner Membership #<?php echo $i; ?>
                        Zoom Invites:</td>
                    <td><input type="number" name='pt_project_owner_membership_zoom_invites_<?php echo $i; ?>' size="4"
                            value="<?php echo get_option("pt_project_owner_membership_zoom_invites_" . $i, 0); ?>" />
                        invites
                    </td>
                </tr>

				<tr>
					<td colspan="3">&nbsp;
					</td>
				</tr>

				<?php } ?>

				<tr>
					<td></td>
					<td></td>
					<td><input type="submit" class="button button-primary button-large" name="my_submit3"
							value="Save these Settings!"></td>
				</tr>

			</table>
		</form>
	</div>

	<div id="tabs2">
		<form method="post" action="<?php echo get_admin_url(); ?>admin.php?page=Memberships&active_tab=tabs2">
			<table width="100%" class="sitemile-table">

				<tr>
					<td colspan="3">Shortcode to use on a page to display the packages:
						<b>[pt_display_freelancer_mem_packs]</b>
					</td>
				</tr>

				<tr>
					<td colspan="3">To set the membership FREE, set the price 0</td>
				</tr>

				<?php for ($i = 1; $i <= 6; $i++) { ?>
                <!-- CRITICAL FIX: Changed $i = 6 to $i <= 6 to prevent infinite loop -->

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width='240'>
						Freelancer Membership #<?php echo $i; ?>
						Name:</td>
					<td><input type="text" name='pt_freelancer_membership_name_<?php echo $i; ?>' size="24"
							value="<?php echo get_option("pt_freelancer_membership_name_" . $i); ?>" />
					</td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Freelancer Membership #<?php echo $i; ?>
						Cost:</td>
					<td><input type="text" name='pt_freelancer_membership_cost_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_freelancer_membership_cost_" . $i); ?>" />
						<?php echo projecttheme_get_currency(); ?></td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Freelancer Membership #<?php echo $i; ?>
						Time:</td>
					<td><input type="text" name='pt_freelancer_membership_time_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_freelancer_membership_time_" . $i); ?>" />
						months</td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Freelancer Membership #<?php echo $i; ?>
						Bids:</td>
					<td><input type="text" name='pt_freelancer_membership_bids_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_freelancer_membership_bids_" . $i); ?>" />
						bids</td>
				</tr>

                <tr>
                    <td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
                    <td>
                        Freelancer Membership #<?php echo $i; ?>
                        Connects:</td>
                    <td><input type="number" name='pt_freelancer_membership_connects_<?php echo $i; ?>' size="4"
                            value="<?php echo get_option("pt_freelancer_membership_connects_" . $i, 0); ?>" />
                        connects
                    </td>
                </tr>

                <tr>
                    <td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
                    <td>
                        Freelancer Membership #<?php echo $i; ?>
                        Zoom Invites:</td>
                    <td><input type="number" name='pt_freelancer_membership_zoom_invites_<?php echo $i; ?>' size="4"
                            value="<?php echo get_option("pt_freelancer_membership_zoom_invites_" . $i, 0); ?>" />
                        invites
                    </td>
                </tr>

				<tr>
					<td colspan="3">&nbsp;
					</td>
				</tr>

				<?php } ?>

				<tr>
					<td></td>
					<td></td>
					<td><input type="submit" class="button button-primary button-large" name="my_submit2"
							value="Save these Settings!"></td>
				</tr>

			</table>
		</form>
	</div>

	<div id="tabs4">
		<form method="post" action="<?php echo get_admin_url(); ?>admin.php?page=Memberships&active_tab=tabs4">
			<table width="100%" class="sitemile-table">

				<tr>
					<td colspan="3">Shortcode to use on a page to display the packages:
						<b>[pt_display_investor_mem_packs]</b>
					</td>
				</tr>
				<tr>
					<td colspan="3">To set the membership FREE, set the price 0</td>
				</tr>

				<?php for ($i = 1; $i <= 6; $i++) { ?>

				<!-- Monthly Options -->
				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td width='240'>
						Investor Membership #<?php echo $i; ?> (Monthly)
						Name:</td>
					<td><input type="text" name='pt_investor_membership_name_<?php echo $i; ?>' size="24"
							value="<?php echo get_option("pt_investor_membership_name_" . $i); ?>" />
					</td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Investor Membership #<?php echo $i; ?> (Monthly)
						Cost:</td>
					<td><input type="text" name='pt_investor_membership_cost_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_investor_membership_cost_" . $i); ?>" />
						<?php echo projecttheme_get_currency(); ?></td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Investor Membership #<?php echo $i; ?> (Monthly)
						Time:</td>
					<td><input type="text" name='pt_investor_membership_time_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_investor_membership_time_" . $i); ?>" />
						months</td>
				</tr>

				<tr>
					<td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
					<td>
						Investor Membership #<?php echo $i; ?> (Monthly)
						Bids:</td>
					<td><input type="text" name='pt_investor_membership_bids_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_investor_membership_bids_" . $i); ?>" />
						bids</td>
				</tr>

                <tr>
                    <td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
                    <td>
                        Investor Membership #<?php echo $i; ?> (Monthly)
                        Connects:</td>
                    <td><input type="number" name='pt_investor_membership_connects_<?php echo $i; ?>' size="4"
                            value="<?php echo get_option("pt_investor_membership_connects_" . $i, 0); ?>" />
                        connects
                    </td>
                </tr>

                <tr>
                    <td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
                    <td>
                        Investor Membership #<?php echo $i; ?> (Monthly)
                        Zoom Invites:</td>
                    <td><input type="number" name='pt_investor_membership_zoom_invites_<?php echo $i; ?>' size="4"
                            value="<?php echo get_option("pt_investor_membership_zoom_invites_" . $i, 0); ?>" />
                        invites
                    </td>
                </tr>

                <?php for ($f = 1; $f <= 5; $f++) { ?>
                <tr>
                    <td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
                    <td>
                        Investor Membership #<?php echo $i; ?> (Monthly)
                        Feature <?php echo $f; ?>:</td>
                    <td><input type="text" name='pt_investor_membership_feature<?php echo $f; ?>_<?php echo $i; ?>' size="40"
                            value="<?php echo get_option("pt_investor_membership_feature" . $f . "_" . $i); ?>" />
                        <br/><span class="description">e.g., "30-minute Coaching Session" or "Unlimited Investor Connections"</span>
                    </td>
                </tr>
                <?php } ?>

				<tr>
					<td colspan="3">&nbsp;
					</td>
				</tr>

                <!-- Yearly Options -->
                <tr>
                    <td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
                    <td width='240'>
                        Investor Membership #<?php echo $i; ?> (Yearly)
                        Name:</td>
                    <td><input type="text" name='pt_investor_membership_name_yearly_<?php echo $i; ?>' size="24"
                            value="<?php echo get_option("pt_investor_membership_name_yearly_" . $i); ?>" />
                    </td>
                </tr>

                <tr>
                    <td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
                    <td>
                        Investor Membership #<?php echo $i; ?> (Yearly)
                        Cost:</td>
                    <td><input type="text" name='pt_investor_membership_cost_yearly_<?php echo $i; ?>' size="4"
                            value="<?php echo get_option("pt_investor_membership_cost_yearly_" . $i); ?>" />
                        <?php echo projecttheme_get_currency(); ?></td>
                </tr>

                <tr>
                    <td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
                    <td>
                        Investor Membership #<?php echo $i; ?> (Yearly)
                        Time:</td>
                    <td><input type="text" name='pt_investor_membership_time_yearly_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_investor_membership_time_yearly_" . $i); ?>" />
                        years</td>
                </tr>

                <tr>
                    <td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
                    <td>
                        Investor Membership #<?php echo $i; ?> (Yearly)
                        Bids:</td>
                    <td><input type="text" name='pt_investor_membership_bids_yearly_<?php echo $i; ?>' size="4"
							value="<?php echo get_option("pt_investor_membership_bids_yearly_" . $i); ?>" />
                        bids</td>
                </tr>

                <tr>
                    <td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
                    <td>
                        Investor Membership #<?php echo $i; ?> (Yearly)
                        Connects:</td>
                    <td><input type="number" name='pt_investor_membership_connects_yearly_<?php echo $i; ?>' size="4"
                            value="<?php echo get_option("pt_investor_membership_connects_yearly_" . $i, 0); ?>" />
                        connects
                    </td>
                </tr>

                <tr>
                    <td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
                    <td>
                        Investor Membership #<?php echo $i; ?> (Yearly)
                        Zoom Invites:</td>
                    <td><input type="number" name='pt_investor_membership_zoom_invites_yearly_<?php echo $i; ?>' size="4"
                            value="<?php echo get_option("pt_investor_membership_zoom_invites_yearly_" . $i, 0); ?>" />
                        invites
                    </td>
                </tr>

                <?php for ($f = 1; $f <= 5; $f++) { ?>
                <tr>
                    <td valign="top" width="22"><?php echo $menu_admin_project_theme_bull; ?></td>
                    <td>
                        Investor Membership #<?php echo $i; ?> (Yearly)
                        Feature <?php echo $f; ?>:</td>
                    <td><input type="text" name='pt_investor_membership_feature<?php echo $f; ?>_yearly_<?php echo $i; ?>' size="40"
							value="<?php echo get_option("pt_investor_membership_feature" . $f . "_yearly_" . $i); ?>" />
                        <br/><span class="description">e.g., "Exclusive Annual Report" or "Priority Support"</span>
                    </td>
                </tr>
                <?php } ?>

				<tr>
					<td colspan="3">&nbsp;
					</td>
				</tr>

				<?php } ?>

				<tr>
					<td></td>
					<td></td>
					<td><input type="submit" class="button button-primary button-large" name="my_submit4"
							value="Save these Settings!"></td>
				</tr>

			</table>
		</form>
	</div>

</div>

<?php
}

// End output buffering and flush
ob_end_flush();
?>
