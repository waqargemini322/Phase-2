<?php
/**
 * ProjectTheme - Get New Membership Page
 * This file handles redirects for users who need to select a new membership plan,
 * typically after a transient expires or if they are directed here from elsewhere.
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

// Log GET parameters for debugging
error_log("DEBUG: get_new_mem.php received GET parameters: " . print_r($_GET, true));

// Determine the correct membership page based on user role or a generic membership page
global $current_user;
get_currentuserinfo();
$uid = $current_user->ID;
$user_role = ProjectTheme_mems_get_current_user_role($uid); // Assuming this function is available in ProjectTheme_membership.php

$redirect_url = get_bloginfo("siteurl") . '/membership/'; // Default generic membership page

if ($user_role == 'investor') {
    $redirect_url = get_bloginfo("siteurl") . '/investor-membership/';
} elseif ($user_role == 'business_owner') {
    $redirect_url = get_bloginfo("siteurl") . '/entrepreneur-membership/';
} elseif ($user_role == 'service_provider') {
    $redirect_url = get_bloginfo("siteurl") . '/freelancer-membership/';
}

// Add any error messages back to the URL if present
if (isset($_GET['error'])) {
    $redirect_url = add_query_arg('error', sanitize_text_field($_GET['error']), $redirect_url);
}

// Redirect to the appropriate membership selection page
wp_redirect($redirect_url);
exit();

?>
