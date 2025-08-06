<?php
/**
 * ProjectTheme - Moneybookers (Skrill) Deposit Response Handler
 * This file processes the IPN/callback from Moneybookers (Skrill) after a membership payment.
 *
 * v1.1: Updated to correctly handle 'investor' membership type with monthly/yearly plans,
 * and corrected the credit assignment logic for Bids, Connects, and Zoom Invites.
 */

// Ensure WordPress environment is loaded
if(!defined('ABSPATH')) {
    $wp_load_path = dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
    } else {
        error_log('Skrill IPN Error: WordPress environment not loaded.');
        exit;
    }
}

// Skrill sends a POST request with the payment status.
if (!isset($_POST['status']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Skrill IPN Error: Invalid request or missing status field.');
    exit;
}

// A status of '2' from Skrill indicates a successful, processed payment.
if ((int)$_POST['status'] == 2) {
    // The 'field1' is our custom field where we passed all the necessary user and membership data.
    if (isset($_POST["field1"])) {
        $custom_data = sanitize_text_field($_POST["field1"]);
        $parts = explode("|", $custom_data);

        // Expected format: mem_id|uid|timestamp|mem_type|plan_type
        $received_mem_id    = isset($parts[0]) ? intval($parts[0]) : 0;
        $received_uid       = isset($parts[1]) ? intval($parts[1]) : 0;
        $received_timestamp = isset($parts[2]) ? intval($parts[2]) : 0;
        $received_mem_type  = isset($parts[3]) ? sanitize_text_field($parts[3]) : '';
        $received_plan_type = isset($parts[4]) ? sanitize_text_field($parts[4]) : 'monthly';

        // Validate extracted data
        if ($received_mem_id > 0 && $received_uid > 0 && !empty($received_mem_type)) {

            // Prevent duplicate processing using a unique transaction flag.
            $processed_flag_meta_key = 'skrill_membership_processed_' . $received_mem_id . '_' . $received_timestamp;
            if (get_user_meta($received_uid, $processed_flag_meta_key, true) === 'yes') {
                 echo 'OK'; exit(); // Already processed
            }

            // --- CORRECTED CREDIT ASSIGNMENT LOGIC ---
            $membership_duration = 0; $membership_items = 0; $membership_name = '';
            $membership_connects = 0; $membership_zoom_invites = 0;

            // Retrieve all membership details based on the received type and plan
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
                default:
                    error_log("Skrill IPN Error: Unknown membership type '{$received_mem_type}' for user {$received_uid}.");
                    exit;
            }

            if ($membership_name) {
                // Calculate expiry date
                $tm = current_time('timestamp');
                $new_expiry_time = $tm;
                $unit = ($received_mem_type == 'investor' && $received_plan_type == 'yearly') ? 'years' : 'months';
                $new_expiry_time = strtotime("+" . $membership_duration . " " . $unit, $tm);

                // Update all user meta fields with the correct values
                update_user_meta($received_uid, 'membership_available', $new_expiry_time);
                update_user_meta($received_uid, 'mem_type', $membership_name);
                update_user_meta($received_uid, 'pt_connect_credits', $membership_connects);
                update_user_meta($received_uid, 'projecttheme_monthly_zoom_invites', $membership_zoom_invites);

                if ($received_mem_type == 'project_owner') {
                    update_user_meta($received_uid, 'projectTheme_monthly_nr_of_projects', $membership_items);
                } else { // freelancer or investor
                    update_user_meta($received_uid, 'projectTheme_monthly_nr_of_bids', $membership_items);
                }
                
                // Set last allocation date for the cron job
                update_user_meta($received_uid, 'pt_last_credit_allocation', $tm);

                // Mark as processed to prevent re-processing
                update_user_meta($received_uid, $processed_flag_meta_key, 'yes');

                error_log("Skrill IPN Success: Membership updated for user {$received_uid}, type {$received_mem_type}.");
            }
            // --- END OF CORRECTED LOGIC ---

        } else {
            error_log("Skrill IPN Error: Invalid custom data received in field1: " . $custom_data);
        }
    } else {
        error_log("Skrill IPN Error: Missing 'field1' in POST data.");
    }
} else {
    // Log non-successful payment status
    $status = isset($_POST['status']) ? $_POST['status'] : 'N/A';
    $transaction_id = isset($_POST['mb_transaction_id']) ? $_POST['mb_transaction_id'] : 'N/A';
    error_log("Skrill IPN: Received non-successful status. Status: {$status}, Transaction ID: {$transaction_id}");
}

// For IPN, respond with 200 OK to Skrill's server.
echo 'OK';
exit;
?>