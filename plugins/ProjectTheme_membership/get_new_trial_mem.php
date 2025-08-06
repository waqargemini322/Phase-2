<?php
/***************************************************************************
*
*	ProjectTheme - copyright ( c ) - sitemile.com
*	The only project theme for wordpress on the world wide web.
*
*	Coder: Andrei Dragos Saioc
*	Email: sitemile[at]sitemile.com | andreisaioc[at]gmail.com
*	More info about the theme here: http://sitemile.com/products/wordpress-project-freelancer-theme/
*	since v1.2.5.3
*
***************************************************************************/

global $current_user, $wp_query;
$pid 	 =  $wp_query->query_vars['pid'];

function ProjectTheme_filter_ttl( $title ) {
    return __( "Activate Free Membership", 'ProjectTheme' )." - ";
}
add_filter( 'wp_title', 'ProjectTheme_filter_ttl', 10, 3 );

if ( !is_user_logged_in() ) {
    wp_redirect( get_bloginfo( 'siteurl' )."/wp-login.php" );
    exit;
}

get_currentuserinfo(); // Corrected function call

$uid 	 = $current_user->ID;
$cid 	 = $current_user->ID;

//-------------------------------------

get_header();
?>
    <div class="page_heading_me">
        <div class="page_heading_me_inner">
            <div class="mm_inn">
                <?php _e( "Activate Free Membership/Subscription", "ProjectTheme" );
?>
            </div>
        </div>
    </div>
    <!-- ########## -->
    <div id="main" class="wrapper">
        <div class="account-main-area col-xs-12 col-sm-8 col-md-8 col-lg-9">
            <div class="my_box3">
                <div class="padd10">
                    <?php

// The $cost variable is not used for free trial activation, so it's removed for clarity.
// $role = ProjectTheme_mems_get_current_user_role( $uid );
// if ( $role == "service_provider" ) $cost = get_option( 'projectTheme_monthly_service_provider' );
// else $cost = get_option( 'projectTheme_monthly_service_contractor' );

echo '<div class="monthly_mem2">'.sprintf( __( 'You are about to activate your trial membership.', 'ProjectTheme' ) ).'</div>';

?>
                        <div class="monthly_mem">
                            <?php

echo '<a href="'.get_bloginfo( 'siteurl' ).'/?p_action=activate_membership_trial" class="green_btn">'.__( 'Confirm Activation', 'ProjectTheme' ).'</a>';

?> </div>
                </div>
            </div>
        </div>
        <?php ProjectTheme_get_users_links();
?>
    </div>
    <?php get_footer();
?>
