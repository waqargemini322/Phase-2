<?php
// Admin functions for ProjectTheme Memberships

// Add custom fields to user profile for membership credits
add_action( 'show_user_profile', 'pt_add_custom_user_profile_fields' );
add_action( 'edit_user_profile', 'pt_add_custom_user_profile_fields' );

function pt_add_custom_user_profile_fields( $user ) {
    ?>
    <h3><?php _e("Link2Investors Credits", "ProjectTheme"); ?></h3>

    <table class="form-table">
        <tr>
            <th><label for="pt_connect_credits"><?php _e("CONNECTS Credits (Messaging)", "ProjectTheme"); ?></label></th>
            <td>
                <input type="number" name="pt_connect_credits" id="pt_connect_credits" value="<?php echo esc_attr( get_user_meta( $user->ID, 'pt_connect_credits', true ) ); ?>" class="regular-text" /><br />
                <span class="description"><?php _e("Number of credits for sending messages/connection requests.", "ProjectTheme"); ?></span>
            </td>
        </tr>
        <tr>
            <th><label for="projecttheme_monthly_zoom_invites"><?php _e("INVITE Credits (Zoom Calls)", "ProjectTheme"); ?></label></th>
            <td>
                <input type="number" name="projecttheme_monthly_zoom_invites" id="projecttheme_monthly_zoom_invites" value="<?php echo esc_attr( get_user_meta( $user->ID, 'projecttheme_monthly_zoom_invites', true ) ); ?>" class="regular-text" /><br />
                <span class="description"><?php _e("Number of credits for initiating Zoom calls.", "ProjectTheme"); ?></span>
            </td>
        </tr>
        <tr>
            <th><label for="pt_last_credit_allocation"><?php _e("Last Credit Allocation (Timestamp)", "ProjectTheme"); ?></label></th>
            <td>
                <input type="text" name="pt_last_credit_allocation" id="pt_last_credit_allocation" value="<?php echo esc_attr( get_user_meta( $user->ID, 'pt_last_credit_allocation', true ) ); ?>" class="regular-text" /><br />
                <span class="description"><?php _e("Timestamp of the last monthly credit allocation for yearly plans. (For debugging)", "ProjectTheme"); ?></span>
            </td>
        </tr>
    </table>
    <?php
}

// Save custom user profile fields
add_action( 'personal_options_update', 'pt_save_custom_user_profile_fields' );
add_action( 'edit_user_profile_update', 'pt_save_custom_user_profile_fields' );

function pt_save_custom_user_profile_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }

    if ( isset( $_POST['pt_connect_credits'] ) ) {
        update_user_meta( $user_id, 'pt_connect_credits', sanitize_text_field( $_POST['pt_connect_credits'] ) );
    }
    if ( isset( $_POST['projecttheme_monthly_zoom_invites'] ) ) {
        update_user_meta( $user_id, 'projecttheme_monthly_zoom_invites', sanitize_text_field( $_POST['projecttheme_monthly_zoom_invites'] ) );
    }
    if ( isset( $_POST['pt_last_credit_allocation'] ) ) {
        update_user_meta( $user_id, 'pt_last_credit_allocation', sanitize_text_field( $_POST['pt_last_credit_allocation'] ) );
    }
}

// Initialize user meta for new users upon registration
add_action('user_register', 'pt_initialize_new_user_membership_meta');
function pt_initialize_new_user_membership_meta($user_id) {
    // Check if meta already exists (e.g., from a previous membership assignment)
    // If not, set default to 0. This prevents arbitrary values.
    if (get_user_meta($user_id, 'pt_connect_credits', true) === '') {
        update_user_meta($user_id, 'pt_connect_credits', 0);
    }
    if (get_user_meta($user_id, 'projecttheme_monthly_zoom_invites', true) === '') {
        update_user_meta($user_id, 'projecttheme_monthly_zoom_invites', 0);
    }
    // Also ensure 'projectTheme_monthly_nr_of_bids' is initialized for consistency
    if (get_user_meta($user_id, 'projectTheme_monthly_nr_of_bids', true) === '') {
        update_user_meta($user_id, 'projectTheme_monthly_nr_of_bids', 0);
    }
    // Initialize last credit allocation for new users
    if (get_user_meta($user_id, 'pt_last_credit_allocation', true) === '') {
        update_user_meta($user_id, 'pt_last_credit_allocation', 0); // Will be set to current time on first purchase/activation
    }
}

// NOTE: projecttheme_get_currency() and projectTheme_get_show_price() are assumed to be
// defined in the theme's functions.php or another core plugin.
// Their definitions have been removed from here to prevent redeclaration errors.

?>
