<?php
/*
Plugin Name: ProjectTheme Dispute Feature
Plugin URI: http://sitemile.com/
Description: Adds the dispute feature for your Project Bidding Theme from sitemile
Author: SiteMile.com
Author URI: http://sitemile.com/
Version: 2.6.4
Text Domain: pt_disputes
*/

include 'my_account/disputes.php';
include 'my_account/view_dispute.php';
include 'my_account/disputes_raise.php';
include 'first_run.php';


add_filter('pt_on_buyer_payment_status','pt_on_buyer_payment_status_dispute');
add_filter('pt_on_freelancer_payment_status','pt_on_freelancer_status_dispute');

function pt_on_freelancer_status_dispute($row)
{
			global $wpdb;

			$ids = $row->id;
			$s = "select * from ".$wpdb->prefix."project_disputes where oid='$ids'";
			$r = $wpdb->get_results($s);

			if(count($r) == 0)
			{
	?>

			<div class="w-100 mb-3"><a href="<?php echo get_permalink(get_option('ProjectTheme_my_account_create_disp_id')) ?>?oid=<?php echo $row->id ?>" class="btn btn-outline-danger btn-sm"><?php _e('Open Dispute','pt_disputes') ?></a></div>

	<?php
}
	else {
		$row = $r[0];


		//solution 0 - open, 1 - buyer agreed to close, 2 - freelancer accepted as liability , 3 - sorted by admin, 4 - closed on accepting offer


		if($row->solution == 0)
		{

		?>

					<div class="alert alert-warning"><?php echo sprintf(__('Dispute created and open. <a href="%s">Click here</a> to see details.','disputes'), projecttheme_view_dispute_thing_link($row->id) ) ?></div>

		<?php

		}
		else {



			if($row->solution == 1)
			{
				// code...?>
								<div class="alert alert-secondary"><?php echo sprintf(__('Dispute closed by initiator. <a href="%s">Click here</a> to see details.','disputes'), projecttheme_view_dispute_thing_link($row->id) ) ?></div>
				<?php

			}
			else {


			// code...?>
							<div class="alert alert-secondary"><?php echo sprintf(__('Dispute created and closed. <a href="%s">Click here</a> to see details.','disputes'), projecttheme_view_dispute_thing_link($row->id) ) ?></div>
			<?php
		}
	}
	}



}


function pt_on_buyer_payment_status_dispute($row)
{
			global $wpdb;

			$ids = $row->id;
			$s = "select * from ".$wpdb->prefix."project_disputes where oid='$ids'";
			$r = $wpdb->get_results($s);

			if(count($r) == 0)
			{
	?>

			<div class="w-100 mb-3"><a href="<?php echo get_permalink(get_option('ProjectTheme_my_account_create_disp_id')) ?>?oid=<?php echo $row->id ?>" class="btn btn-outline-danger btn-sm"><?php _e('Open Dispute','pt_disputes') ?></a></div>

	<?php
}
	else {
		$row = $r[0];


		//solution 0 - open, 1 - buyer agreed to close, 2 - freelancer accepted as liability , 3 - sorted by admin, 4 - closed on accepting offer


		if($row->solution == 0)
		{

		?>

					<div class="alert alert-warning"><?php echo sprintf(__('Dispute created and open. <a href="%s">Click here</a> to see details.','disputes'), projecttheme_view_dispute_thing_link($row->id) ) ?></div>

		<?php

		}
		else {

			if($row->solution == 1)
			{
			 ?>
							<div class="alert alert-warning"><?php echo sprintf(__('Dispute was closed by the initiator. <a href="%s">Click here</a> to see details.','disputes'), projecttheme_view_dispute_thing_link($row->id) ) ?></div>
			<?php
			}
			else {


			// code...?>
							<div class="alert alert-warning"><?php echo sprintf(__('Dispute created and closed. <a href="%s">Click here</a> to see details.','disputes'), projecttheme_view_dispute_thing_link($row->id) ) ?></div>
			<?php
		}
	}
	}



}

add_filter('ProjectTheme_awaiting_completion_button_place','ProjectTheme_awaiting_completion_button_place_dsp');
function ProjectTheme_awaiting_completion_button_place_dsp($pid)
{
		$expected_delivery = get_post_meta($pid, 'expected_delivery', true);
		$ctm = current_time('timestamp');

		if($ctm > $expected_delivery)
		{

		}

}


function ProjectTheme_awaiting_completion_info_details_raise_dispute($pid)
{

	$expected_delivery = get_post_meta($pid, 'expected_delivery', true);
	$ctm = current_time('timestamp');

	if($ctm > $expected_delivery)
	{

			if(ProjectTheme_using_permalinks()) $pane_lnk = get_permalink(get_option('ProjectTheme_my_account_create_disp_id')) . '?pid=' . $pid;
			else $pane_lnk = get_permalink(get_option('ProjectTheme_my_account_create_disp_id')) . '&pid=' . $pid;


			if(projecttheme_raisen_dispute_pid($pid) == true)
			echo '<br/><br/> <a class="btn btn-danger" href="'.get_permalink(get_option('ProjectTheme_my_account_disputes_id')).'">'.__('Dispute Started - See Details','ProjectTheme').'</a>';
			else
			echo ' <a class="btn btn-danger" href="'.$pane_lnk.'">'.__('Raise Dispute','ProjectTheme').'</a> ';

		}

}

/*************************************************************
*
*	ProjectTheme (c) sitemile.com - function
*
**************************************************************/

function ProjectTheme_get_open_disputes_nr($uid)
{

		global $wpdb;
		$s = "select count(id) totaldisputes from ".$wpdb->prefix."project_disputes where (uid1='$uid' or uid2='$uid' ) and solution='0'"; // means the dispute is open still
		$r = $wpdb->get_results($s);

		$row = $r[0];
		return $row->totaldisputes;
}
/*************************************************************
*
*	ProjectTheme (c) sitemile.com - function
*
**************************************************************/



function ProjectTheme_dispute_myplugin_activate()
{
	global $wpdb;

	$ss = " CREATE TABLE `".$wpdb->prefix."project_disputes` (
			`id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`initiator` INT,
			`pid` BIGINT ,
			`datemade` BIGINT ,
			`solution` TINYINT NOT NULL DEFAULT '0',
			`winner` TINYINT NOT NULL DEFAULT '0',
			`closedon` BIGINT,
			`comment` TEXT NOT NULL

	) ENGINE = MYISAM ";

	//solution 0 - open, 1 - buyer agreed to close, 2 - freelancer accepted as liability , 3 - sorted by admin, 4 - closed on accepting offer


		$wpdb->query($ss);

		$sql_option_my = "ALTER TABLE  `".$wpdb->prefix."project_disputes` ADD  `uid1` BIGINT NOT NULL DEFAULT '0' ;";
		$wpdb->query($sql_option_my);

	$sql_option_my = "ALTER TABLE  `".$wpdb->prefix."project_disputes` ADD  `uid2` BIGINT NOT NULL DEFAULT '0' ;";
	$wpdb->query($sql_option_my);

	$sql_option_my = "ALTER TABLE  `".$wpdb->prefix."project_disputes` ADD  `oid` BIGINT NOT NULL DEFAULT '0' ;";
	$wpdb->query($sql_option_my);


	$sql_option_my = "ALTER TABLE  `".$wpdb->prefix."project_disputes` ADD  `reason` BIGINT NOT NULL DEFAULT '0' ;";
	$wpdb->query($sql_option_my);



	$ss = "CREATE TABLE `".$wpdb->prefix."project_disputes_messages` (
																					`id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
																					`uid` INT NOT NULL DEFAULT '0',
																					`receiver` INT NOT NULL DEFAULT '0',
																					`description` TEXT NOT NULL ,
																					`disputeid` INT NOT NULL DEFAULT '0' ,
																					`rd` TINYINT NOT NULL DEFAULT '0',
																					`pid` INT NOT NULL DEFAULT '0' ,
																					`datemade` INT NOT NULL DEFAULT '0',
																					`readdate` INT NOT NULL DEFAULT '0',
																					`file_attached` INT NOT NULL DEFAULT '0'
			) ENGINE = MYISAM ;
			";
	$wpdb->query($ss);


	$ss = "CREATE TABLE `".$wpdb->prefix."project_disputes_offers` (
																					`id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
																					`disputeid` INT NOT NULL DEFAULT '0',
																					`sender` INT NOT NULL DEFAULT '0',
																					`receiver` INT NOT NULL DEFAULT '0',
																					`description` TEXT NOT NULL ,
																					`answer` TINYINT NOT NULL DEFAULT '0',
																					`rd` TINYINT NOT NULL DEFAULT '0',
																					`datemade` INT NOT NULL DEFAULT '0',
																					`readdate` INT NOT NULL DEFAULT '0',
																					`amount` VARCHAR(40) NULL DEFAULT '0'
			) ENGINE = MYISAM ;
			";

				$wpdb->query($ss);

	//---------------------------

	if(function_exists('ProjectTheme_insert_pages'))
	{
		ProjectTheme_insert_pages_account('ProjectTheme_my_account_disputes_id', 			'Disputes', 				'[project_theme_my_account_disputes]', 			get_option('ProjectTheme_my_account_page_id') );
		ProjectTheme_insert_pages_account('ProjectTheme_my_account_create_disp_id', 			'Raise Dispute', 				'[project_theme_my_account_raise_dispute]', 			get_option('ProjectTheme_my_account_page_id') );
		ProjectTheme_insert_pages_account('ProjectTheme_my_account_view_disp_id', 			'View Dispute', 				'[project_theme_my_account_view_disp]', 			get_option('ProjectTheme_my_account_page_id') );

		ProjectTheme_insert_pages_account('ProjectTheme_my_account_send_dispute_offer_id', 			'Send Offer', 				'[project_theme_my_account_send_disp_offer]', 			get_option('ProjectTheme_my_account_page_id') );
		//ProjectTheme_insert_pages_account('ProjectTheme_my_account_view_disp_id', 			'View Dispute', 				'[project_theme_my_account_view_disp]', 			get_option('ProjectTheme_my_account_page_id') );
	}
}

add_shortcode('project_theme_my_account_view_disp','ProjectTheme_my_account_view_disp_area_function');

add_shortcode('project_theme_my_account_disputes',							'ProjectTheme_my_account_disputes_area_function');
add_shortcode('project_theme_my_account_raise_dispute',							'ProjectTheme_my_account_disputes_raise_area_function');


add_action('ProjectTheme_my_account_main_menu',		'ProjectTheme_add_dispute_user_menu');
register_activation_hook(  __FILE__, 'ProjectTheme_dispute_myplugin_activate' );


add_filter('ProjectTheme_awaiting_completion_button_place','ProjectTheme_awaiting_completion_info_details_raise_dispute');
add_filter('wp_head','ProjectTheme_disputes_css');

/*************************************************************
*
*	ProjectTheme (c) sitemile.com - function
*
**************************************************************/

function ProjectTheme_disputes_css()
{
	?>

	<style>
	a.dispute_link:link,a.dispute_link:visited
	{
			padding:5px;
			border-radius: 5px;
			color:white;
			background:rgb(246, 149, 132)
	}
	a.dispute_link:hover{
		text-decoration: none;
		background:rgba(241, 66, 19, 0.91)
	}


	a.dispute_link2:link,a.dispute_link2:visited
	{
			font-style: italic;
	}
	a.dispute_link2:hover{

	}


	</style>
	<?php

}

/*************************************************************
*
*	ProjectTheme (c) sitemile.com - function
*
**************************************************************/



function projecttheme_view_dispute_thing_link($id)
{

	return get_permalink(get_option('ProjectTheme_my_account_view_disp_id')) . "?disp_id=" . $id;
}

/*************************************************************
*
*	ProjectTheme (c) sitemile.com - function
*
**************************************************************/

function projecttheme_raisen_dispute_pid($pid)
{
	global $wpdb, $current_user;

	$s = "select * from ".$wpdb->prefix."project_disputes where pid='$pid'";
	$r = $wpdb->get_results($s);

	if(count($r) > 0) return true;
	return false;

}


/*************************************************************
*
*	ProjectTheme (c) sitemile.com - function
*
**************************************************************/


function ProjectTheme_add_dispute_user_menu()
{
		global $wpdb, $current_user;
		get_currentuserinfo();
		$uid = $current_user->ID;


		$t = ProjectTheme_get_open_disputes_nr($uid);
		if($t > 0) $t = "<span class='badge badge-primary'>" . $t . '</span>';
		else $t = '';
?>

<li class="sidebar-item"> <a class="sidebar-link" href="<?php echo get_permalink(get_option('ProjectTheme_my_account_disputes_id')); ?>" aria-expanded="false">
	<span class="hide-menu"><i class="fas fa-gavel"></i> <?php printf(__("Disputes %s",'ProjectTheme'), $t); ?></span></a>
</li>




<?php
}




/*************************************************************
*
*	ProjectTheme (c) sitemile.com - function
*
**************************************************************/

add_filter('ProjectTheme_admin_menu_add_item','ProjectTheme_admin_menu_add_item_disputes');


function ProjectTheme_admin_menu_add_item_disputes()
{
				 $capability = 10;
				 global $projecthememnupg;
				 $advs = 'add'.'_' . 'menu' . '_'. 'page';

			$projecthememnupg('project_theme_mnu', __('Disputes','ProjectTheme'), '<i class="fas fa-gavel"></i> '.__('Disputes','ProjectTheme'),$capability, 'disputes', 'projectTheme_disputes_screen');
}


/******************************************************************
*
*	Admin Menu - New Function - sitemile[at]sitemile.com
*	developed by Andrei Saioc - andreisaioc[at]gmail.com
*
*******************************************************************/
function projectTheme_disputes_screen()
{
	global $menu_admin_project_theme_bull;
	echo '<div class="wrap">';
	echo '<div class="icon32" id="icon-options-general-arb"><br/></div>';
	echo '<h2 class="my_title_class_sitemile">ProjectTheme Disputes</h2>';


	global $wpdb;

	if(!empty($_GET['view_dispute']))
	{


		$disp_id = $_GET['view_dispute'];

		$s 			= "select * from ".$wpdb->prefix."project_disputes where id='$disp_id'";
		$r 			= $wpdb->get_results($s);
		$row 		= $r[0];
		$pid 		= $row->pid;
		$oid 		= $row->oid;

		$post = get_post($pid);

					?>
					<!-- CSS only -->
					<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">


<?php

if(isset($_GET['awardtoinitiator']))
{
			if(isset($_GET['awardtoinitiator']))
			{

									$s = "update ".$wpdb->prefix."project_disputes set solution='3', winner='{$row->initiator}' where id='$disp_id'";
									$wpdb->query($s);

							?>

										<div class="alert alert-success">Dispute was awarded to the initiator.</div>


							<?php

							$s 			= "select * from ".$wpdb->prefix."project_disputes where id='$disp_id'";
							$r 			= $wpdb->get_results($s);
							$row 		= $r[0];
							$pid 		= $row->pid;

							$post = get_post($pid);
							$tm = current_time('timestamp');

							$s = "update ".$wpdb->prefix."project_orders set order_status='3', cancelled_date='$tm' where id='$oid'";
							$wpdb->query($s);


			}
			else
			{

	?>
					<div class="alert alert-success">

									Are you sure you want to set the initiator as the winner ? <br/><br/>

									<a href="<?php echo get_admin_url() ?>admin.php?page=disputes&view_dispute=<?php echo $disp_id ?>&awardtoinitiator=1&yes=yes" class="btn btn-sm btn-success">Yes confirm</a>


					</div>
	<?php
}
}


if(isset($_GET['awardtootherparty']))
{
			if(isset($_GET['awardtootherparty']))
			{

									$s = "update ".$wpdb->prefix."project_disputes set solution='3', winner='{$row->uid2}' where id='$disp_id'";
									$wpdb->query($s);

							?>

										<div class="alert alert-success">Dispute was awarded to the other party.</div>


							<?php

							$s 			= "select * from ".$wpdb->prefix."project_disputes where id='$disp_id'";
							$r 			= $wpdb->get_results($s);
							$row 		= $r[0];
							$pid 		= $row->pid;

							$post = get_post($pid);
							$tm = current_time('timestamp');

							$s = "update ".$wpdb->prefix."project_orders set order_status='3', cancelled_date='$tm' where id='$oid'";
							$wpdb->query($s);


			}
			else
			{

	?>
					<div class="alert alert-success">

									Are you sure you want to set the other party as the winner ? <br/><br/>

									<a href="<?php echo get_admin_url() ?>admin.php?page=disputes&view_dispute=<?php echo $disp_id ?>&awardtoinitiator=1&yes=yes" class="btn btn-sm btn-success">Yes confirm</a>


					</div>
	<?php
}
}





 ?>

<h3>Dispute - #<?php echo $_GET['view_dispute'] ?> - <?php echo $post->post_title ?></h3>

<div class="card p-3">
<p><a href="<?php echo get_admin_url() ?>admin.php?page=disputes" class="btn btn-sm btn-primary btn-block">Return to dispute list</a> </p>
<?php

				if($row->solution == 0)
				{

 ?>
<p><a href="<?php echo get_admin_url() ?>admin.php?page=disputes&view_dispute=<?php echo $disp_id ?>&awardtoinitiator=1" class="btn btn-sm btn-primary btn-block">Award to Initiator</a> </p>
 <p><a href="<?php echo get_admin_url() ?>admin.php?page=disputes&view_dispute=<?php echo $disp_id ?>&awardtootherparty=1" class="btn btn-sm btn-primary btn-block">Award to the other party</a> </p>
<?php } ?>

</div>

<div class="card p-3">
	<ul class="list-group">
		<li class="list-group-item"> <i class="fas fa-folder"></i> <?php printf(__('%s Project Name: %s','ProjectTheme'), '<span class="spn_bold">',  '</span><a href="'.get_permalink($pid).'">'.$post->post_title.'</a>') ?></li>
		<li class="list-group-item"> <i class="far fa-calendar-alt"></i> <?php printf(__('%s Dispute Started On: %s','ProjectTheme'), '<span class="spn_bold">',  '</span>'.date_i18n('d-m-Y H:i:s',$row->datemade)) ?></li>
		<li class="list-group-item"> <i class="fas fa-user-tag"></i>  <?php printf(__('%s Dispute Started By: %s','ProjectTheme'), '<span class="spn_bold">',  '</span>'.'<a href="'.ProjectTheme_get_user_profile_link($row->uid1).'">'.project_theme_get_name_of_user($row->uid1) .'</a>') ?></li>
		<li class="list-group-item"> <i class="fas fa-user-cog"></i>  <?php printf(__('%s Dispute Other Party: %s','ProjectTheme'),  '<span class="spn_bold">', '</span>'.'<a href="'.ProjectTheme_get_user_profile_link($row->uid2).'">'.project_theme_get_name_of_user($row->uid2) .'</a>') ?></li>
	<?php

		$projectTheme_get_winner_bid = projectTheme_get_winner_bid($pid);
		$pt_get_workspace_of_project = pt_get_workspace_of_project($pid);

	?>

	<li class="list-group-item"><i class="far fa-money-bill-alt"></i><?php printf(__('%s Winner Bid: %s','ProjectTheme'),  '<span class="spn_bold">', '</span>'.projecttheme_get_show_price($projectTheme_get_winner_bid->bid) ) ?></li>

	<?php if($pt_get_workspace_of_project != false) { ?>
	<li class="list-group-item"><i class="far fa-money-bill-alt"></i><?php printf(__('%s Project Workspace: %s','ProjectTheme'),  '<span class="spn_bold">', '</span><a href="'.site_url().'?p_action=workspaces&pid='.$pt_get_workspace_of_project.'">'.__('See Workspace','ProjectTheme') . '</a>') ?></li>

	<?php } ?>



	</ul>


	<div class="w-100 mt-3">
	<?php

	$viewdispute_id = get_permalink(get_option('ProjectTheme_my_account_view_disp_id'));
	if(ProjectTheme_using_permalinks()) $viewdispute_id = $viewdispute_id ."?disp_id=" . $disp_id;
	else $viewdispute_id = $viewdispute_id ."&disp_id=" . $disp_id;


	if($row_general->solution == 0)
	{

		if($cvs_uid == $row->initiator)
		{

	?>

		 <a href="#" class="btn btn-primary" data-toggle="modal" data-target="#exampleModal"><?php _e('Withdraw & Close Dispute','ProjectTheme') ?></a>





		<?php } else{


					$send_offer_link = get_permalink(get_option('ProjectTheme_my_account_send_dispute_offer_id'));
					if(ProjectTheme_using_permalinks()) $send_offer_link = $send_offer_link ."?disp_id=" . $disp_id;
					else $send_offer_link = $send_offer_link ."&disp_id=" . $disp_id;


			?>




			<?php
		}} else {

					?>
						<div class="alert alert-warning">
							<?php _e('This dispute is closed, you cannot submit anymore messages or do any other actions.','disputes') ?>
						</div>

					<?php

		} ?>


</div>	</div>




<div class="clear5"></div>

<div class="card mgr_btm <?php echo $class1 ?>">
						<div class="row p-3">
								<div class="text-center col col-xs-12 col-md-3 col-lg-2">
										<img width="70" class="avatar-image" height="90" border="0" src="<?php echo ProjectTheme_get_avatar($row->initiator,90,90); ?>" /> <br/>
										<?php

													$user_se = get_userdata($row->initiator);
													echo '<a href="'.ProjectTheme_get_user_profile_link($user_se->ID).'">' . project_theme_get_name_of_user($user_se->ID) . '</a>';
										 ?>
								</div>

						<div class="col col-xs-12 col-md-9 col-lg-10">
						<?php echo stripslashes($row->comment); ?>
	<br/>

	<?php

	if(!empty($row->file_attached))
	{
echo sprintf(__('File Attached: %s','ProjectTheme') , '<a href="'.wp_get_attachment_url($row->file_attached).'">'.wp_get_attachment_url($row->file_attached)."</a>") ;
echo '<br/>';
}

	echo '<p><small><em>' . date_i18n('d-M-Y H:i:s', $row->datemade) .'</em></small></p>';
?>



						</div></div>
						</div>




						<?php


									$s = "select * from ".$wpdb->prefix."project_disputes_messages where disputeid='{$row->id}' order by id asc";
									$r = $wpdb->get_results($s);

									if(count($r) > 0)
									foreach($r as $row1)
									{
												if($row1->user == $current_user->ID)
												{
														$wpdb->query("update ".$wpdb->prefix."project_disputes_messages set rd='1' where id='{$row1->id}'");

												}


												if($row1->uid  == $current_user->ID) $class1 = 'my_bk1_class';
												else $class1 = '';


											?>
													<div class="clear5"></div>

													<div class="card mgr_btm <?php echo $class1.' ' ?>">
														<div class="row p-3">
																<div class=" text-center col col-xs-12 col-md-3 col-lg-2">
																		<img width="70" class="avatar-image" height="90" border="0" src="<?php echo ProjectTheme_get_avatar($row1->uid,90,90); ?>" /> <br/>
																		<?php

																					$user_se = get_userdata($row1->uid);
																					echo '<a href="'.ProjectTheme_get_user_profile_link($user_se->ID).'">' . project_theme_get_name_of_user($user_se->ID).'</a>';
																		 ?>
																</div>

														<div class="col col-xs-12 col-md-9 col-lg-10">
														<?php echo stripslashes($row1->description); ?>


													<br/>

													<?php

													if(!empty($row1->file_attached))
													{
													echo sprintf(__('File Attached: %s','ProjectTheme') , '<a href="'.wp_get_attachment_url($row1->file_attached).'">'.wp_get_attachment_url($row1->file_attached)."</a>") ;
													echo '<br/><br/>';
												}

													 echo '<p><small><em>'.date_i18n('d-M-Y H:i:s', $row1->datemade).'</em></small></p>';

													?>



														</div></div>
														</div>

											<?php
									}





												$s = "select * from ".$wpdb->prefix."project_disputes_messages where disputeid='{$row->id}' order by id asc";
												$r = $wpdb->get_results($s);

												if(count($r) > 0)
												foreach($r as $row1)
												{
															if($row1->user == $current_user->ID)
															{
																	$wpdb->query("update ".$wpdb->prefix."project_disputes_messages set rd='1' where id='{$row1->id}'");

															}


															if($row1->uid  == $current_user->ID) $class1 = 'my_bk1_class';
															else $class1 = '';


														?>
																<div class="clear5"></div>

																<div class="card mgr_btm <?php echo $class1.' ' ?>">
																	<div class="row p-3">
																			<div class=" text-center col col-xs-12 col-md-3 col-lg-2">
																					<img width="70" class="avatar-image" height="90" border="0" src="<?php echo ProjectTheme_get_avatar($row1->uid,90,90); ?>" /> <br/>
																					<?php

																								$user_se = get_userdata($row1->uid);
																								echo '<a href="'.ProjectTheme_get_user_profile_link($user_se->ID).'">' . project_theme_get_name_of_user($user_se->ID).'</a>';
																					 ?>
																			</div>

																	<div class="col col-xs-12 col-md-9 col-lg-10">
																	<?php echo stripslashes($row1->description); ?>


																<br/>

																<?php

																if(!empty($row1->file_attached))
																{
																echo sprintf(__('File Attached: %s','ProjectTheme') , '<a href="'.wp_get_attachment_url($row1->file_attached).'">'.wp_get_attachment_url($row1->file_attached)."</a>") ;
																echo '<br/><br/>';
															}

																 echo '<p><small><em>'.date_i18n('d-M-Y H:i:s', $row1->datemade).'</em></small></p>';

																?>



																	</div></div>
																	</div>

														<?php
												}
	}
	else {


				if(isset($_POST['shipme_sv_dispute_options']))
				{

						for($i = 1; $i<=10; $i++)
						{
									update_option('pt_disp_reason_' . $i, $_POST['pt_disp_reason_' . $i]);
						}
				}

	?>

        <div id="usual2" class="usual">
  <ul>
    <li><a href="#tabs1" class="selected">Current Open Disputes</a></li>
    <li><a href="#tabs2">Closed Disputes</a></li>
	    <li><a href="#tabs3">Dispute Options</a></li>
  </ul>


	  <div id="tabs3" >


			<form method="post" action="<?php echo get_admin_url(); ?>admin.php?page=disputes&active_tab=tabs3">
				<table width="100%" class="sitemile-table">

				<?php


							for($i = 1; $i<=10; $i++)
							{

				 ?>
								<tr>
								<td valign=top width="22"> </td>
								<td width="200">Dispute Reason: #<?php echo $i ?></td>
								<td><input type="text" size="30" name="pt_disp_reason_<?php echo $i ?>" id="" value="<?php echo get_option('pt_disp_reason_' . $i); ?>" /></td>
								</tr>


							<?php } ?>

								<tr>
								<td ></td>
								<td ></td>
								<td><input type="submit" name="shipme_sv_dispute_options" value="<?php _e('Save Options','ProjectTheme'); ?>"/></td>
								</tr>

				</table>
				</form>



		</div>


  <div id="tabs1" style="display: block; ">

		<?php

		global $wpdb;

			$s = "select * from ".$wpdb->prefix."project_disputes where  solution='0'";
			$r = $wpdb->get_results($s);

			if(count($r) == 0)	_e('There are no open disputes.','ProjectTheme');
			else {



				?>

<table class="wp-list-table widefat fixed striped posts">
	<thead>
				<tr>
					<th>Project Name</th>
					<th>Amount</th>
					<th>Opened By</th>
					<th>Opened On</th>
					<th></th>
				</tr>
	</thead>


<tbody>
				<?php


					foreach($r as $row)
					{
								$pst = get_post($row->pid);
								$bid = projectTheme_get_winner_bid($row->pid);
								$initiator = get_userdata($row->initiator);
					?>

								<tr>
										<th><?php echo $pst->post_title ?></th>
										<th><?php echo projectTheme_get_show_price($bid->bid) ?></th>
										<th><?php echo $initiator->user_login ?></th>
										<th><?php echo date_i18n('d-m-Y H:i:s', $row->datemade) ?></th>
										<th><a href="<?php echo get_admin_url() ?>/admin.php?page=disputes&view_dispute=<?php echo $row->id ?>" class="button button-primary button-large" />View</a></th>

								</tr>


					<?php
			}
			?>

			<tbody>

</table>


			<?php

		}
		 ?>



          </div>
          <div id="tabs2" style="display: none; ">

						<?php

						global $wpdb;

							$s = "select * from ".$wpdb->prefix."project_disputes where  solution!='0'";
							$r = $wpdb->get_results($s);

							if(count($r) == 0)	_e('There are no closed disputes.','ProjectTheme');
							else {



								?>

						<table class="wp-list-table widefat fixed striped posts">
						<thead>
								<tr>
									<th>Project Name</th>
									<th>Amount</th>
									<th>Opened By</th>
									<th>Winner</th>
									<th>Opened On</th>
									<th></th>
								</tr>
						</thead>


						<tbody>
								<?php


									foreach($r as $row)
									{
												$pst = get_post($row->pid);
												$bid = projectTheme_get_winner_bid($row->pid);
												$initiator = get_userdata($row->initiator);
												$winner = get_userdata($row->winner);
									?>

												<tr>
														<th><?php echo $pst->post_title ?></th>
														<th><?php echo projectTheme_get_show_price($bid->bid) ?></th>
														<th><?php echo $initiator->user_login ?></th>
														<th><?php echo $winner->user_login ?></th>
														<th><?php echo date_i18n('d-m-Y H:i:s', $row->datemade) ?></th>
														<th><a href="<?php echo get_admin_url() ?>/admin.php?page=disputes&view_dispute=<?php echo $row->id ?>" class="button button-primary button-large">View</a></th>

												</tr>


									<?php
							}
							?>

							<tbody>

						</table>


							<?php

						}
						 ?>


					</div>
        </div>


    <?php }

	echo '</div>';
}
//----------------------------



?>
