<?php
/**
 * Plugin Name: Link2Investors Custom Features
 * Plugin URI:  https://link2investors.com/
 * Description: Custom functionalities for Link2Investors, including connection requests, and a credit-based Zoom invite system.
 * Version:     1.6.0 (Cloudways Compatible)
 * Author:      Link2Investors Development
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pt-custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// --- Activation Hook to create custom table and add Zoom link columns ---
register_activation_hook( __FILE__, 'pt_custom_activate_plugin' );
function pt_custom_activate_plugin() {
    global $wpdb;
    
    // Create connections requests table
    $table_name = $wpdb->prefix . 'pt_connections_requests';
    $charset_collate = $wpdb->get_charset_collate();

    $sql_connections = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        sender_id BIGINT(20) UNSIGNED NOT NULL,
        receiver_id BIGINT(20) UNSIGNED NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        date_created DATETIME NOT NULL,
        date_updated DATETIME NULL,
        PRIMARY KEY (`id`), 
        KEY `sender_id` (`sender_id`), 
        KEY `receiver_id` (`receiver_id`)
    ) $charset_collate;";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_connections );

    // Ensure database schema is up to date
    pt_custom_ensure_database_schema();
}

/**
 * Ensure the database schema is up to date with required columns.
 */
function pt_custom_ensure_database_schema() {
    global $wpdb;
    
    // Add zoom_link_url and zoom_link_timestamp to project_pm_threads table if they don't exist
    $threads_table_name = $wpdb->prefix . 'project_pm_threads';
    
    $column_exists_url = $wpdb->query("SHOW COLUMNS FROM `$threads_table_name` LIKE 'zoom_link_url'");
    $column_exists_timestamp = $wpdb->query("SHOW COLUMNS FROM `$threads_table_name` LIKE 'zoom_link_timestamp'");

    if (!$column_exists_url) {
        $wpdb->query("ALTER TABLE `$threads_table_name` ADD COLUMN `zoom_link_url` TEXT NULL");
    }
    if (!$column_exists_timestamp) {
        $wpdb->query("ALTER TABLE `$threads_table_name` ADD COLUMN `zoom_link_timestamp` BIGINT(20) NULL");
    }
    
    // Add attached_files_json to project_pm table if it doesn't exist
    $pm_table_name = $wpdb->prefix . 'project_pm';
    $column_exists_json = $wpdb->query("SHOW COLUMNS FROM `$pm_table_name` LIKE 'attached_files_json'");
    
    if (!$column_exists_json) {
        $wpdb->query("ALTER TABLE `$pm_table_name` ADD COLUMN `attached_files_json` TEXT NULL");
    }
}

// --- Enqueue Scripts and Styles ---
add_action( 'wp_enqueue_scripts', 'pt_custom_enqueue_scripts' );
function pt_custom_enqueue_scripts() {
    // Only load on relevant pages to reduce conflicts
    if (is_page() && (has_shortcode(get_post()->post_content, 'project_theme_my_account_livechat') || 
                      has_shortcode(get_post()->post_content, 'pt_connection_button'))) {
        
        wp_enqueue_script( 'pt-custom-script', plugin_dir_url( __FILE__ ) . 'assets/js/pt-custom-script.js', array( 'jquery' ), '1.6.0', true );
        wp_localize_script( 'pt-custom-script', 'pt_ajax_obj', array(
            'ajax_url'                => admin_url( 'admin-ajax.php' ),
            'send_connection_nonce'   => wp_create_nonce( 'pt_send_connection_nonce' ),
            'connection_action_nonce' => wp_create_nonce( 'pt_connection_action_nonce' ),
            'zoom_meeting_nonce'      => wp_create_nonce( 'link2investors_zoom_nonce' ),
            'all_credits_nonce'       => wp_create_nonce( 'l2i_all_credits_nonce' ),
        ) );
        wp_enqueue_style( 'pt-custom-style', plugin_dir_url( __FILE__ ) . 'assets/css/pt-custom-style.css', array(), '1.6.0', 'all', 999 );
    }
}

//======================================================================
// 1. CREDIT MANAGEMENT HELPER FUNCTIONS
//======================================================================

/**
 * Get connect credits for a user.
 */
function pt_get_connect_credits( $user_id ) {
    if (!$user_id) return 0;
    clean_user_cache( $user_id );
    return (int) get_user_meta( $user_id, 'pt_connect_credits', true );
}

/**
 * Deduct connect credits from a user.
 */
function pt_deduct_connect_credits( $user_id, $amount = 1 ) {
    if (!$user_id || $amount <= 0) return false;
    
    $current_credits = pt_get_connect_credits( $user_id );
    if ( $current_credits >= $amount ) {
        $new_credits = $current_credits - $amount;
        update_user_meta( $user_id, 'pt_connect_credits', $new_credits );
        clean_user_cache( $user_id );
        return true;
    }
    return false;
}

/**
 * Get invite credits for a user.
 */
function pt_get_invite_credits( $user_id ) {
    if (!$user_id) return 0;
    clean_user_cache( $user_id );
    return (int) get_user_meta( $user_id, 'projecttheme_monthly_zoom_invites', true );
}

/**
 * Deduct invite credits from a user.
 */
function pt_deduct_invite_credits( $user_id, $amount = 1 ) {
    if (!$user_id || $amount <= 0) return false;
    
    $current_credits = pt_get_invite_credits( $user_id );
    if ( $current_credits >= $amount ) {
        $new_credits = $current_credits - $amount;
        update_user_meta( $user_id, 'projecttheme_monthly_zoom_invites', $new_credits );
        clean_user_cache( $user_id );
        return true;
    }
    return false;
}

/**
 * Get bid credits for a user.
 */
function pt_get_bid_credits( $user_id ) {
    if (!$user_id) return 0;
    clean_user_cache( $user_id );
    return (int) get_user_meta( $user_id, 'projecttheme_monthly_bids', true );
}

//======================================================================
// 2. AJAX HANDLERS
//======================================================================

/**
 * AJAX handler to get zoom invites count.
 */
add_action( 'wp_ajax_get_zoom_invites', 'l2i_ajax_get_zoom_invites_callback' );
function l2i_ajax_get_zoom_invites_callback() {
    if ( ! wp_verify_nonce( $_POST['nonce'], 'link2investors_zoom_nonce' ) ) {
        wp_send_json_error( 'Security check failed.' );
    }
    
    $user_id = get_current_user_id();
    $credits = pt_get_invite_credits( $user_id );
    wp_send_json_success( $credits );
}

/**
 * AJAX handler to get all credits for a user.
 */
add_action( 'wp_ajax_get_all_credits', 'l2i_ajax_get_all_credits_callback' );
function l2i_ajax_get_all_credits_callback() {
    if ( ! wp_verify_nonce( $_POST['nonce'], 'l2i_all_credits_nonce' ) ) {
        wp_send_json_error( 'Security check failed.' );
    }
    
    $user_id = get_current_user_id();
    $credits = array(
        'connect' => pt_get_connect_credits( $user_id ),
        'invite'  => pt_get_invite_credits( $user_id ),
        'bid'     => pt_get_bid_credits( $user_id )
    );
    wp_send_json_success( $credits );
}

/**
 * AJAX handler to create Zoom meeting.
 */
add_action( 'wp_ajax_create_zoom_meeting', 'link2investors_create_zoom_meeting_callback' );
function link2investors_create_zoom_meeting_callback() {
    if ( ! wp_verify_nonce( $_POST['nonce'], 'link2investors_zoom_nonce' ) ) {
        wp_send_json_error( 'Security check failed.' );
    }
    
    $user_id = get_current_user_id();
    $thid = isset( $_POST['thid'] ) ? (int) $_POST['thid'] : 0;
    
    if ( ! $user_id || ! $thid ) {
        wp_send_json_error( 'Invalid parameters.' );
    }
    
    // Check if user has invite credits
    $current_credits = pt_get_invite_credits( $user_id );
    if ( $current_credits <= 0 ) {
        wp_send_json_error( 'Insufficient zoom invite credits.' );
    }
    
    // Check if user is an investor
    $user_role = function_exists('ProjectTheme_mems_get_current_user_role') ? 
                 ProjectTheme_mems_get_current_user_role($user_id) : '';
    
    if ( $user_role !== 'investor' ) {
        wp_send_json_error( 'Only investors can create zoom meetings.' );
    }
    
    // Create Zoom meeting URL (simplified for now)
    $zoom_url = 'https://zoom.us/j/' . rand(100000000, 999999999);
    $timestamp = current_time( 'timestamp' );
    
    // Update thread with zoom link
    global $wpdb;
    $result = $wpdb->update(
        $wpdb->prefix . 'project_pm_threads',
        array(
            'zoom_link_url' => $zoom_url,
            'zoom_link_timestamp' => $timestamp
        ),
        array( 'id' => $thid ),
        array( '%s', '%d' ),
        array( '%d' )
    );
    
    if ( $result !== false ) {
        // Deduct one invite credit
        pt_deduct_invite_credits( $user_id, 1 );
        
        wp_send_json_success( array(
            'zoom_url' => $zoom_url,
            'timestamp' => $timestamp,
            'remaining_credits' => pt_get_invite_credits( $user_id )
        ) );
    } else {
        wp_send_json_error( 'Failed to create zoom meeting.' );
    }
}

/**
 * AJAX handler to send connection request.
 */
add_action( 'wp_ajax_send_connection_request', 'pt_ajax_send_connection_request' );
function pt_ajax_send_connection_request() {
    if ( ! wp_verify_nonce( $_POST['nonce'], 'pt_send_connection_nonce' ) ) {
        wp_send_json_error( 'Security check failed.' );
    }
    
    $sender_id = get_current_user_id();
    $receiver_id = isset( $_POST['receiver_id'] ) ? (int) $_POST['receiver_id'] : 0;
    
    if ( ! $sender_id || ! $receiver_id || $sender_id === $receiver_id ) {
        wp_send_json_error( 'Invalid parameters.' );
    }
    
    // Check if request already exists
    global $wpdb;
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}pt_connections_requests 
         WHERE sender_id = %d AND receiver_id = %d AND status = 'pending'",
        $sender_id, $receiver_id
    ) );
    
    if ( $existing ) {
        wp_send_json_error( 'Connection request already sent.' );
    }
    
    // Insert new request
    $result = $wpdb->insert(
        $wpdb->prefix . 'pt_connections_requests',
        array(
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'status' => 'pending',
            'date_created' => current_time( 'mysql' )
        ),
        array( '%d', '%d', '%s', '%s' )
    );
    
    if ( $result !== false ) {
        wp_send_json_success( 'Connection request sent successfully.' );
    } else {
        wp_send_json_error( 'Failed to send connection request.' );
    }
}

//======================================================================
// 3. SHORTCODES
//======================================================================

/**
 * Shortcode for connection button.
 */
add_shortcode( 'pt_connection_button', 'pt_connection_button_shortcode' );
function pt_connection_button_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'user_id' => 0,
        'text' => 'Connect'
    ), $atts );
    
    $user_id = (int) $atts['user_id'];
    $current_user_id = get_current_user_id();
    
    if ( ! $user_id || ! $current_user_id || $user_id === $current_user_id ) {
        return '';
    }
    
    // Check if request already exists
    global $wpdb;
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT status FROM {$wpdb->prefix}pt_connections_requests 
         WHERE sender_id = %d AND receiver_id = %d",
        $current_user_id, $user_id
    ) );
    
    $button_text = $atts['text'];
    $button_class = 'pt-connect-btn';
    $disabled = '';
    
    if ( $existing ) {
        if ( $existing === 'pending' ) {
            $button_text = 'Request Sent';
            $button_class .= ' pending';
            $disabled = 'disabled';
        } elseif ( $existing === 'accepted' ) {
            $button_text = 'Connected';
            $button_class .= ' connected';
            $disabled = 'disabled';
        }
    }
    
    return sprintf(
        '<button class="%s" data-user-id="%d" %s>%s</button>',
        esc_attr( $button_class ),
        $user_id,
        $disabled,
        esc_html( $button_text )
    );
}

//======================================================================
// 4. USER PROFILE FIELDS
//======================================================================

/**
 * Add custom fields to user profile.
 */
add_action( 'show_user_profile', 'pt_custom_user_profile_fields' );
add_action( 'edit_user_profile', 'pt_custom_user_profile_fields' );
function pt_custom_user_profile_fields( $user ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    ?>
    <h3>Link2Investors Credits</h3>
    <table class="form-table">
        <tr>
            <th><label for="pt_connect_credits">Connect Credits</label></th>
            <td>
                <input type="number" name="pt_connect_credits" id="pt_connect_credits" 
                       value="<?php echo esc_attr( get_user_meta( $user->ID, 'pt_connect_credits', true ) ); ?>" 
                       class="regular-text" min="0" />
                <p class="description">Number of connection credits available.</p>
            </td>
        </tr>
        <tr>
            <th><label for="projecttheme_monthly_zoom_invites">Zoom Invite Credits</label></th>
            <td>
                <input type="number" name="projecttheme_monthly_zoom_invites" id="projecttheme_monthly_zoom_invites" 
                       value="<?php echo esc_attr( get_user_meta( $user->ID, 'projecttheme_monthly_zoom_invites', true ) ); ?>" 
                       class="regular-text" min="0" />
                <p class="description">Number of zoom invite credits available.</p>
            </td>
        </tr>
        <tr>
            <th><label for="projecttheme_monthly_bids">Bid Credits</label></th>
            <td>
                <input type="number" name="projecttheme_monthly_bids" id="projecttheme_monthly_bids" 
                       value="<?php echo esc_attr( get_user_meta( $user->ID, 'projecttheme_monthly_bids', true ) ); ?>" 
                       class="regular-text" min="0" />
                <p class="description">Number of bid credits available.</p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Save custom user profile fields.
 */
add_action( 'personal_options_update', 'pt_custom_save_user_profile_fields' );
add_action( 'edit_user_profile_update', 'pt_custom_save_user_profile_fields' );
function pt_custom_save_user_profile_fields( $user_id ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return false;
    }
    
    if ( isset( $_POST['pt_connect_credits'] ) ) {
        update_user_meta( $user_id, 'pt_connect_credits', (int) $_POST['pt_connect_credits'] );
    }
    
    if ( isset( $_POST['projecttheme_monthly_zoom_invites'] ) ) {
        update_user_meta( $user_id, 'projecttheme_monthly_zoom_invites', (int) $_POST['projecttheme_monthly_zoom_invites'] );
    }
    
    if ( isset( $_POST['projecttheme_monthly_bids'] ) ) {
        update_user_meta( $user_id, 'projecttheme_monthly_bids', (int) $_POST['projecttheme_monthly_bids'] );
    }
    
    clean_user_cache( $user_id );
}

//======================================================================
// 5. CRON JOB FOR CREDIT RENEWAL
//======================================================================

/**
 * Schedule credit renewal cron job.
 */
add_action( 'wp', 'pt_custom_schedule_credit_renewal' );
function pt_custom_schedule_credit_renewal() {
    if ( ! wp_next_scheduled( 'pt_credit_renewal_hook' ) ) {
        wp_schedule_event( time(), 'daily', 'pt_credit_renewal_hook' );
    }
}

/**
 * Credit renewal function.
 */
add_action( 'pt_credit_renewal_hook', 'pt_custom_credit_renewal' );
function pt_custom_credit_renewal() {
    // Get all users with membership roles
    $users = get_users( array(
        'role__in' => array( 'investor', 'freelancer', 'professional' ),
        'fields' => 'ID'
    ) );
    
    foreach ( $users as $user_id ) {
        // Get user's membership tier and set credits accordingly
        $user_role = function_exists('ProjectTheme_mems_get_current_user_role') ? 
                     ProjectTheme_mems_get_current_user_role($user_id) : '';
        
        // Set default credits based on role (you can customize this)
        switch ( $user_role ) {
            case 'investor':
                update_user_meta( $user_id, 'pt_connect_credits', 10 );
                update_user_meta( $user_id, 'projecttheme_monthly_zoom_invites', 5 );
                update_user_meta( $user_id, 'projecttheme_monthly_bids', 20 );
                break;
            case 'freelancer':
                update_user_meta( $user_id, 'pt_connect_credits', 15 );
                update_user_meta( $user_id, 'projecttheme_monthly_zoom_invites', 3 );
                update_user_meta( $user_id, 'projecttheme_monthly_bids', 30 );
                break;
            case 'professional':
                update_user_meta( $user_id, 'pt_connect_credits', 20 );
                update_user_meta( $user_id, 'projecttheme_monthly_zoom_invites', 5 );
                update_user_meta( $user_id, 'projecttheme_monthly_bids', 50 );
                break;
        }
        
        clean_user_cache( $user_id );
    }
}

// Cleanup on deactivation
register_deactivation_hook( __FILE__, 'pt_custom_deactivate_plugin' );
function pt_custom_deactivate_plugin() {
    wp_clear_scheduled_hook( 'pt_credit_renewal_hook' );
}