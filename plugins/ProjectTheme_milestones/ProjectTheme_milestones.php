<?php
/*
Plugin Name: ProjectTheme Milestones Feature
Plugin URI: http://sitemile.com/
Description: Adds the a milestones feature for your Project Bidding Theme from sitemile
Author: SiteMile.com
Author URI: http://sitemile.com/
Version: 1.4
Text Domain: pt_milestones
*/

include 'my_account/milestones.php';

//-----------------

add_filter('template_redirect',							'ProjectTheme_template_redirect_milestones');
add_filter('ProjectTheme_financial_buttons_main',    	'ProjectTheme_add_milestones_main_btn');
add_action('the_content',								'ProjectTheme_display_my_account_milestones');


//**************************************************************

add_action('wp_footer',								'pt_milestones_footer', 9999);
function pt_milestones_footer()
{
	?>


	<script>


jQuery(document).ready(function() {
 jQuery('#completion_date').datepicker( );});

</script>


	<?php
}

function projectTheme_release_milestone_link($id)
{
	$opt = get_option('ProjectTheme_my_account_milestones_id');
	$perm = ProjectTheme_using_permalinks();

	if($perm == true)
	{
		return get_permalink($opt). "?release_id2=" . $id;
	}
	else
	{
		return get_permalink($opt). "&release_id2=" . $id;	;
	}
}

//**************************************************************

function ProjectTheme_template_redirect_milestones()
{
	$mlls 	= "PT_milestone_payments_installed_1_aaa";
	$opt 	= get_option($mlls);

	if(isset($_POST['submits1no_me_thing_ok'])) { wp_redirect(get_permalink(get_option('ProjectTheme_my_account_milestones_id'))); exit; }
	if(isset($_POST['submits1yes_me_ok_p'])) {

		global $wpdb;
		$release_id = $_POST['release_id'];
		$s = "select * from ".$wpdb->prefix."project_milestone where id='$release_id'";
		$r = $wpdb->get_results($s);
		$row = $r[0];
		global $current_user;
		get_currentuserinfo();

		$post_me = get_post($row->pid);
		//-------------------------
		$cr = projectTheme_get_credits($current_user->ID);

		if($row->released == 0 and $cr >= $row->amount)
		{
			$amount = $row->amount;

			$projectTheme_fee_after_paid = get_option('projectTheme_fee_after_paid');
			if(!empty($projectTheme_fee_after_paid)):
				$deducted = $amount*($projectTheme_fee_after_paid * 0.01);
			else:
				$deducted = 0;
			endif;

			$cr = projectTheme_get_credits($row->uid);
			projectTheme_update_credits($row->uid, $cr + $amount - $deducted);

			$reason = sprintf(__('Milestone payment received from %s for the project <b>%s</b>','ProjectTheme'), $current_user->user_login, $post_me->post_title);
			projectTheme_add_history_log('1', $reason, $amount, $row->uid, $current_user->ID);


			if($deducted > 0)
			{
				$reason = sprintf(__('Payment fee taken for milestone payment for the project <b>%s</b>','ProjectTheme'), $post_me->post_title);
				projectTheme_add_history_log('0', $reason, $deducted, $row->uid);
			}

			$wpdb->query("update ".$wpdb->prefix."project_milestone set released='1' where id='$release_id'");

			//-----------------------
			$usr_dt = get_userdata($row->uid);
			$cr = projectTheme_get_credits($current_user->ID);
			projectTheme_update_credits($current_user->ID, $cr - $amount);

			$reason = sprintf(__('Milestone payment sent to %s for the project <b>%s</b>','ProjectTheme'), $usr_dt->user_login, $post_me->post_title);
			projectTheme_add_history_log('0', $reason, $amount, $current_user->ID, $row->uid);


		}

		//-------------------------
	}

	//---------------------------------

	if(empty($opt))
	{
		ProjectTheme_insert_pages('ProjectTheme_my_account_milestones_id', 	'Milestone Payments', 	'[project_theme_my_account_milestones]', 	get_option('ProjectTheme_my_account_payments_id') );
		update_option($mlls,'DONE');

		global $wpdb;
		$ss = "CREATE TABLE `".$wpdb->prefix."project_milestone` (
					`id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
					`owner` INT NOT NULL ,
					`pid` INT NOT NULL ,
					`uid` INT NOT NULL ,
					`description_content` TEXT NOT NULL ,
					`datemade` BIGINT NOT NULL DEFAULT '0',
					`completion_date` BIGINT NOT NULL DEFAULT '0' ,
					`date_released` BIGINT NOT NULL DEFAULT '0' ,
					`amount` VARCHAR( 255 ) NOT NULL

					) ENGINE = MYISAM ;
					";
		$wpdb->query($ss);

		$ss = "ALTER TABLE `".$wpdb->prefix."project_milestone` ADD  `released` TINYINT NOT NULL DEFAULT '0';";
		$wpdb->query($ss);

	}
}


//**************************************************************

function ProjectTheme_add_milestones_main_btn()
{
	?>

       <li>    <a href="<?php echo get_permalink(get_option('ProjectTheme_my_account_milestones_id')); ?>"  class="btn btn-dark btn-sm" role="button"><?php _e('Milestone Payments','ProjectTheme'); ?></a>    </li>


    <?php
}


//**************************************************************

function ProjectTheme_display_my_account_milestones( $content = '' )
{
	if ( preg_match( "/\[project_theme_my_account_milestones\]/", $content ) )
	{
		ob_start();
		ProjectTheme_my_account_milestones_area_function();
		$output = ob_get_contents();
		ob_end_clean();
		$output = str_replace( '$', '\$', $output );
		return preg_replace( "/(<p>)*\[project_theme_my_account_milestones\](<\/p>)*/", $output, $content );

	}
	else {
		return $content;
	}
}




?>
