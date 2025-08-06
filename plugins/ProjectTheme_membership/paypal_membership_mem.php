<?php
/**
 * ProjectTheme - PayPal Membership Payment Gateway
 * This file handles the redirection to PayPal for membership payments
 * and processes the success/IPN response.
 *
 * v1.1: Updated to support 'investor' membership type with monthly/yearly plans,
 * and corrected the credit assignment logic for Bids, Connects, and Zoom Invites.
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

// Ensure the PayPal class is available
$paypal_class_path = dirname(__FILE__) . '/../paypal.class.php';
if (file_exists($paypal_class_path)) {
    include_once $paypal_class_path;
} else {
    die('PayPal class file not found.');
}

global $wp_query, $wpdb, $current_user;
get_currentuserinfo();
$uid = $current_user->ID;

$action = isset($_GET["action"]) ? sanitize_text_field($_GET["action"]) : "process";

// These parameters are for the initial 'process' action when the user clicks "Pay by PayPal"
$mem_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$mem_type = isset($_POST['tp']) ? sanitize_text_field($_POST['tp']) : ''; // 'freelancer', 'project_owner', 'investor'
$plan_type = isset($_POST['plan_type']) ? sanitize_text_field($_POST['plan_type']) : 'monthly'; // 'monthly' or 'yearly'

// Get PayPal business email from settings
$business = trim(get_option("ProjectTheme_paypal_email"));
if (empty($business) && $action == "process") {
    die("Error. PayPal email is not configured in the admin settings.");
}

$p = new paypal_class(); // Initiate an instance of the class

// Determine PayPal URL (sandbox or live)
if (get_option("ProjectTheme_paypal_enable_sdbx") == "yes") {
    $p->paypal_url = "https://www.sandbox.paypal.com/cgi-bin/webscr";
} else {
    $p->paypal_url = "https://www.paypal.com/cgi-bin/webscr";
}

// The URL of this script for PayPal to send notifications to
$this_script = get_bloginfo("siteurl") . "/?p_action=paypal_membership_mem";

switch ($action) {
    case "process": // Process an order and redirect user to PayPal
        
        if ($mem_id < 1 || !in_array($mem_type, array('freelancer', 'project_owner', 'investor'))) {
            die('Invalid membership selection or type.');
        }

        $cost = 0;
        $membership_name_for_paypal = '';

        // Determine cost and name based on membership type and plan
        switch ($mem_type) {
            case 'freelancer':
                $cost = get_option('pt_freelancer_membership_cost_' . $mem_id);
                $membership_name_for_paypal = get_option('pt_freelancer_membership_name_' . $mem_id);
                break;
            case 'project_owner':
                $cost = get_option('pt_project_owner_membership_cost_' . $mem_id);
                $membership_name_for_paypal = get_option('pt_project_owner_membership_name_' . $mem_id);
                break;
            case 'investor':
                if ($plan_type == 'yearly') {
                    $cost = get_option('pt_investor_membership_cost_yearly_' . $mem_id);
                    $membership_name_for_paypal = get_option('pt_investor_membership_name_yearly_' . $mem_id);
                } else { // monthly
                    $cost = get_option('pt_investor_membership_cost_' . $mem_id);
                    $membership_name_for_paypal = get_option('pt_investor_membership_name_' . $mem_id);
                }
                break;
        }

        if ($cost === false || $cost <= 0) {
            die('Error: Membership cost is not set or is zero. Cannot process payment for free plans via PayPal.');
        }

        $p->add_field("business", $business);
        $p->add_field("currency_code", get_option("ProjectTheme_currency"));
        $p->add_field("return", get_permalink(get_option("ProjectTheme_my_account_page_id")) . "?payment_status=success");
        $p->add_field("cancel_return", get_permalink(get_option("ProjectTheme_my_account_page_id")) . "?payment_status=cancelled");
        $p->add_field("notify_url", $this_script . "&action=ipn");
        $p->add_field("item_name", $membership_name_for_paypal . " Membership");
        
        // --- CORRECTED CUSTOM FIELD ---
        // Pass all necessary data to the IPN handler.
        $p->add_field("custom", $mem_id . "|" . $uid . "|" . current_time("timestamp", 0) . "|" . $mem_type . "|" . $plan_type);
        $p->add_field("amount", ProjectTheme_formats_special($cost, 2));

        $p->submit_paypal_post(); // Submit the form to PayPal
        break;

    case "success": // User is redirected here from PayPal after payment.
    case "ipn":     // PayPal sends a server-to-server notification here.

        // IPN is the most reliable method. We process the payment here.
        if (isset($_POST["custom"])) {
            $cust = sanitize_text_field($_POST["custom"]);
            $cust_parts = explode("|", $cust);

            // Extract data from custom field
            $received_mem_id    = isset($cust_parts[0]) ? intval($cust_parts[0]) : 0;
            $received_uid       = isset($cust_parts[1]) ? intval($cust_parts[1]) : 0;
            $received_timestamp = isset($cust_parts[2]) ? intval($cust_parts[2]) : 0;
            $received_mem_type  = isset($cust_parts[3]) ? sanitize_text_field($cust_parts[3]) : '';
            $received_plan_type = isset($cust_parts[4]) ? sanitize_text_field($cust_parts[4]) : 'monthly';

            if ($received_mem_id > 0 && $received_uid > 0 && !empty($received_mem_type)) {
                
                // Prevent duplicate processing
                $processed_flag_meta_key = 'paypal_membership_processed_' . $received_mem_id . '_' . $received_timestamp;
                if (get_user_meta($received_uid, $processed_flag_meta_key, true) === 'yes') {
                    if ($action == "success") {
                        wp_redirect(get_permalink(get_option("ProjectTheme_my_account_page_id")) . "?payment_status=success");
                        exit();
                    }
                    echo 'OK'; exit();
                }

                // --- CORRECTED CREDIT ASSIGNMENT LOGIC ---
                $membership_duration = 0; $membership_items = 0; $membership_name = '';
                $membership_connects = 0; $membership_zoom_invites = 0;

                // Retrieve membership details based on the received type and plan
                switch ($received_mem_type) {
                    case 'freelancer':
                        $membership_duration = get_option('pt_freelancer_membership_time_' . $received_mem_id);
                        $membership_items = get_option('pt_freelancer_membership_bids_' . $received_mem_id);
                        $membership_name = get_option('pt_freelancer_membership_name_' . $received_mem_id);
                        $membership_connects = get_option('pt_freelancer_membership_connects_' . $received_mem_id);
                        $membership_zoom_invites = get_option('pt_freelancer_membership_zoom_invites_' . $received_mem_id);
                        break;
                    case 'project_owner':
                        $membership_duration = get_option('pt_project_owner_membership_time_' . $received_mem_id);
                        $membership_items = get_option('pt_project_owner_membership_projects_' . $received_mem_id);
                        $membership_name = get_option('pt_project_owner_membership_name_' . $received_mem_id);
                        $membership_connects = get_option('pt_project_owner_membership_connects_' . $received_mem_id);
                        $membership_zoom_invites = get_option('pt_project_owner_membership_zoom_invites_' . $received_mem_id);
                        break;
                    case 'investor':
                        if ($received_plan_type == 'yearly') {
                            $membership_duration = get_option('pt_investor_membership_time_yearly_' . $received_mem_id);
                            $membership_items = get_option('pt_investor_membership_bids_yearly_' . $received_mem_id);
                            $membership_name = get_option('pt_investor_membership_name_yearly_' . $received_mem_id);
                            $membership_connects = get_option('pt_investor_membership_connects_yearly_' . $received_mem_id);
                            $membership_zoom_invites = get_option('pt_investor_membership_zoom_invites_yearly_' . $received_mem_id);
                        } else { // monthly
                            $membership_duration = get_option('pt_investor_membership_time_' . $received_mem_id);
                            $membership_items = get_option('pt_investor_membership_bids_' . $received_mem_id);
                            $membership_name = get_option('pt_investor_membership_name_' . $received_mem_id);
                            $membership_connects = get_option('pt_investor_membership_connects_' . $received_mem_id);
                            $membership_zoom_invites = get_option('pt_investor_membership_zoom_invites_' . $received_mem_id);
                        }
                        break;
                }

                if ($membership_name) {
                    // Calculate expiry date
                    $tm = current_time('timestamp');
                    $new_expiry_time = $tm;
                    $unit = ($received_mem_type == 'investor' && $received_plan_type == 'yearly') ? 'years' : 'months';
                    $new_expiry_time = strtotime("+" . $membership_duration . " " . $unit, $tm);

                    // Update all user meta fields correctly
                    update_user_meta($received_uid, 'membership_available', $new_expiry_time);
                    update_user_meta($received_uid, 'mem_type', $membership_name);
                    update_user_meta($received_uid, 'pt_connect_credits', $membership_connects);
                    update_user_meta($received_uid, 'projecttheme_monthly_zoom_invites', $membership_zoom_invites);

                    if ($received_mem_type == 'project_owner') {
                        update_user_meta($received_uid, 'projectTheme_monthly_nr_of_projects', $membership_items);
                    } else { // freelancer or investor
                        update_user_meta($received_uid, 'projectTheme_monthly_nr_of_bids', $membership_items);
                    }
                    
                    // Mark as processed
                    update_user_meta($received_uid, $processed_flag_meta_key, 'yes');
                }
                // --- END OF CORRECTED LOGIC ---
            }
        }

        if ($action == "success") {
            wp_redirect(get_permalink(get_option("ProjectTheme_my_account_page_id")) . "?payment_status=success");
            exit();
        } else {
            // For IPN, respond with 200 OK
            echo 'OK';
            exit();
        }
        break;

    case "cancel": // Order was canceled...
        wp_redirect(get_permalink(get_option("ProjectTheme_my_account_page_id")) . "?payment_status=cancelled");
        exit();
        break;
}
?>