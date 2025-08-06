<?php
/*
Plugin Name: Link2Investors Membership Restrictions
Description: Centralized management for membership-based feature restrictions on Link2Investors. This version dynamically restricts users based on membership price.
Version: 1.1.0
Author: Link2Investors Development
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Central helper function to check if a user is on a restricted (free) tier.
 * This version dynamically checks if the user's investor membership plan costs $0.
 * It includes a bypass for essential account and payment pages to allow users to upgrade.
 *
 * @param int|null $user_id The ID of the user to check. Defaults to current user.
 * @return bool True if the user is on a free/restricted tier, false otherwise.
 */
function l2i_is_restricted_member($user_id = null) {
    if (empty($user_id)) {
        $user_id = get_current_user_id();
    }

    if (empty($user_id)) {
        return false; // Not logged in, not restricted.
    }

    // --- Bypass for Essential Pages ---
    // Allows users on free tiers to access pages needed to upgrade their membership.
    $current_url = home_url(add_query_arg(null, null));
    $site_url = get_bloginfo("siteurl");
    $allowed_pages_patterns = [
        get_permalink(get_option("ProjectTheme_my_account_page_id")),
        $site_url . '/entrepreneur-membership/',
        $site_url . '/investor-membership/',
        $site_url . '/?p_action=purchase_membership_display', //
        $site_url . '/?p_action=credits_listing_mem',        //
        $site_url . '/?p_action=paypal_membership_mem',      //
        $site_url . '/?p_action=get_new_mem',                 //
    ];
    foreach ($allowed_pages_patterns as $pattern) {
        if (strpos($current_url, $pattern) !== false) {
            return false; // NOT restricted on these pages.
        }
    }

    $user_role = '';
    // Use the theme's function to get the primary user role.
    if (function_exists('ProjectTheme_mems_get_current_user_role')) {
        $user_role = ProjectTheme_mems_get_current_user_role($user_id);
    } else {
        $user_data = get_userdata($user_id);
        if ($user_data && !empty($user_data->roles)) {
            $user_role = array_shift($user_data->roles);
        }
    }

    // --- DYNAMIC RESTRICTION LOGIC FOR INVESTORS ---
    if ($user_role === 'investor') {
        // Get the name of the user's current membership plan from their user meta.
        $user_mem_type_name = get_user_meta($user_id, 'mem_type', true);

        if (empty($user_mem_type_name)) {
            return false; // User has no membership type, so they are not restricted by this logic.
        }

        // Loop through all 6 possible investor membership slots to find the user's plan and check its price.
        for ($i = 1; $i <= 6; $i++) {
            // Check monthly investor plans
            $monthly_plan_name = get_option("pt_investor_membership_name_" . $i);
            if ($monthly_plan_name === $user_mem_type_name) {
                $plan_cost = get_option("pt_investor_membership_cost_" . $i, 0);
                if ((float)$plan_cost == 0) {
                    return true; // The user is on a free monthly plan, so they ARE restricted.
                }
            }

            // Check yearly investor plans
            $yearly_plan_name = get_option("pt_investor_membership_name_yearly_" . $i);
            if ($yearly_plan_name === $user_mem_type_name) {
                $plan_cost = get_option("pt_investor_membership_cost_yearly_" . $i, 0);
                if ((float)$plan_cost == 0) {
                    return true; // The user is on a free yearly plan, so they ARE restricted.
                }
            }
        }
    }
    
    // You can add similar `if ($user_role === '...'){}` blocks for other roles here in the future.

    return false; // If we've reached this point, the user is not on a restricted tier.
}


/**
 * Enqueue scripts to localize the restriction flag for frontend JavaScript use.
 */
add_action('wp_enqueue_scripts', 'l2i_restrictions_enqueue_scripts', 20);
function l2i_restrictions_enqueue_scripts() {
    if (is_user_logged_in()) {
        // This makes the result of l2i_is_restricted_member() available in JavaScript.
        wp_localize_script('pt-custom-script', 'l2i_restrictions_obj', array(
            'is_restricted_member' => l2i_is_restricted_member(get_current_user_id()),
            'restriction_message'  => __('Your current membership level does not allow this action. Please upgrade your plan.', 'link2investors'),
        ));
    }
}


// --- HOOKS TO APPLY RESTRICTIONS ---
// The following hooks use the central l2i_is_restricted_member() function to block various actions.

// Restrict creating Zoom meetings.
add_action('wp_ajax_link2investors_create_zoom_meeting', 'l2i_restrict_action_callback', 1);

// Restrict sending connection requests.
add_action('wp_ajax_pt_send_connection_request', 'l2i_restrict_action_callback', 1);

// Restrict sending chat messages.
add_action('wp_ajax_send_regular_chat_message', 'l2i_restrict_action_callback', 1);

/**
 * A single callback function for restricting multiple AJAX actions.
 */
function l2i_restrict_action_callback() {
    if (l2i_is_restricted_member()) {
        wp_send_json_error(
            __('Your current membership level does not allow this action. Please upgrade your plan.', 'link2investors'),
            403
        );
        wp_die();
    }
}

/**
 * Restrict placing bids on projects.
 * This hooks into the ProjectTheme's bidding allowance check.
 */
add_filter('ProjectTheme_is_it_allowed_place_bids', 'l2i_restrict_bidding_and_posting', 10);
/**
 * Restrict posting new projects.
 * This hooks into the ProjectTheme's posting allowance check.
 */
add_filter('ProjectTheme_is_it_allowed_post_projects', 'l2i_restrict_bidding_and_posting', 10);

function l2i_restrict_bidding_and_posting($allowed) {
    if (l2i_is_restricted_member()) {
        return false; // If restricted, not allowed.
    }
    return $allowed; // Otherwise, respect the original allowance.
}