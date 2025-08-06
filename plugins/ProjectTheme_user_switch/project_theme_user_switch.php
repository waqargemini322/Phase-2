<?php
/*
Plugin Name: ProjectTheme User Switch
Plugin URI: http://sitemile.com/
Description: Lets your users switch roles and become buyer or seller when they want from their account.
Author: SiteMile.com
Author URI: http://sitemile.com/
Version: 1.2
Text Domain: ProjectTheme_switch
*/

add_filter('PT_below_balance_acc_area','PT_below_balance_acc_area_swi');
add_filter('template_redirect','pw_sw_temp_redir');

function pw_sw_temp_redir()
{
	if($_GET['switch_role'] == 1)
	{
		$uid = get_current_user_id();
		$gg = ProjectTheme_is_user_provider($uid);

		if($gg == true)
		{

			if( current_user_can('editor') || current_user_can('administrator') ) {

				}
					else
					{
						// do the stuff here
						$u = new WP_User( $uid );
						// Remove role
						$u->set_role('business_owner');


					}
		}
		else
			{

				if( current_user_can('editor') || current_user_can('administrator') ) {

				}
					else
					{
						// do the stuff here
						$u = new WP_User( $uid );
						// Remove role

						$u->set_role('service_provider');


					}

			}

		wp_redirect(get_permalink(get_option('ProjectTheme_my_account_page_id')));
		exit;
	}

}

function PT_below_balance_acc_area_swi()
{

	$uid = get_current_user_id();
	$u = get_userdata($uid);
	$user_role = $u->roles[0]; 
	if(!current_user_can('edit_others_pages'))
	{
	if(!ProjectTheme_is_user_provider($uid))
	{	
	?>

	<div class="button-sw-lw mt-2" <?php if ($user_role =='investor') { echo 'style="display: none;"';}?>>
	<button onclick="window.location.href='<?php echo site_url() ?>/?switch_role=1'" class="btn btn-primary"><?php _e('Switch to Freelancer','ProjectTheme_switch'); ?></button>
	</div>

	<?php


	}
	else
	{
	?>

	<div class="button-sw-lw mt-2">
	<button onclick="window.location.href='<?php echo site_url() ?>/?switch_role=1'" class="btn btn-primary"><?php _e('Switch to Entrepreneur','ProjectTheme_switch'); ?></button>
	</div>

	<?php

	}	}
}

?>
