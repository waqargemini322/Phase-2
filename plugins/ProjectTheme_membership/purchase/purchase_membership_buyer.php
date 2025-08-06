<?php
// Redirect to login if user is not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url() . '/wp-login.php?action=register');
    exit();
}

// Get membership ID from GET parameter
$memid = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate memid (assuming 1 to 6 are valid IDs)
if ($memid == 0 || $memid < 1 || $memid > 6) {
    wp_redirect(get_bloginfo("siteurl") . "/?p_action=get_new_mem"); // Redirect to membership selection if invalid ID
    exit();
}

// Redirect to the unified display page for project_owner (entrepreneur)
wp_redirect(get_bloginfo("siteurl") . "/?p_action=purchase_membership_display&mem_type=project_owner&id=" . $memid);
exit();
?>
