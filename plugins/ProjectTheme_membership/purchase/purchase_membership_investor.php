<?php
// Redirect to login if user is not logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit();
}

// Get membership ID and plan type from GET parameters
$pack_id = isset($_GET["pack_id"]) ? intval($_GET["pack_id"]) : (isset($_GET["id"]) ? intval($_GET["id"]) : 0);
$plan_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'monthly'; // 'monthly' or 'yearly'

// Validate pack_id
if ($pack_id == 0) {
    wp_redirect(get_bloginfo("siteurl") . "/?p_action=get_new_mem");
    exit();
}

// Redirect to the unified display page for investor
wp_redirect(get_bloginfo("siteurl") . "/?p_action=purchase_membership_display&mem_type=investor&id=" . $pack_id . "&plan_type=" . $plan_type);
exit();
?>
