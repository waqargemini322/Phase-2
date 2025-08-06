<?php
/***************************************************************************
*
*	ProjectTheme - copyright (c) - sitemile.com
*	The only project theme for wordpress on the world wide web.
*
*	Coder: Andrei Dragos Saioc
*	Email: sitemile[at]sitemile.com | andreisaioc[at]gmail.com
*	More info about the theme here: http://sitemile.com/products/wordpress-project-freelancer-theme/
*	since v1.2.5.3
*
***************************************************************************/


add_filter('pt_escrow_screen_thing','projecttheme_milestone_payments');
function projecttheme_milestone_payments()
{
	?>

	<h3 class="my-account-headline-1"><?php _e("Create Milestone Payment", "ProjectTheme"); ?></h3>
			<div class="card">



					<div class="box_content">
				<?php

		if(isset($_POST['submit_milestone']))
		{
			$nok = 0;

			$error1 = array();

			$projectss 					= $_POST['projectss'];
			$amount_text 				= trim($_POST['amount_text']);
			$completion_date 		= strtotime($_POST['completion_date']);
			$completion_date2 	= $_POST['completion_date'];
			$tm 								= current_time('timestamp',0);
			$description 				= nl2br($_POST['description']);
			$pid 								= $projectss;

			//----------------------------------------

			if(empty($projectss)) { $nok = 1; $error1[] = __('You need to select a project for your payment.','ProjectTheme'); }
			if(empty($amount_text) or !is_numeric($amount_text)) { $nok = 1; $error1[] = __('Make sure you type in a payment amount for your milestone, and its numeric.','ProjectTheme'); }
			if(empty($description) ) { $nok = 1; $error1[] = __('Please provide a description for your milestone payment.','ProjectTheme'); }
			if($completion_date < $tm) { $nok = 1; $error1[] = __('The completion date must be a date in the future.','ProjectTheme'); }

			if($nok == 0)
			{
				$projectTheme_get_winner_bid 	= projectTheme_get_winner_bid($pid);
				$uid_of_winner 					= $projectTheme_get_winner_bid->uid;

				$credits = projectTheme_get_credits(get_current_user_id());

				if($credits < $amount_text)
				{

					echo '<div class="alert alert-danger">';
						echo sprintf(__('You do not have enough balance in your account to create this milestone. <a href="%s" class="">Click here</a> to deposit more.','ProjectTheme'), ProjectTheme_get_payments_page_url('deposit'));
					echo '</div> <div class="clear10"></div>';

				}
				else {


							$s1 = "select * from ".$wpdb->prefix."project_milestone where pid='$pid' and uid='$uid_of_winner' AND completion_date='$completion_date' and released='0' ";
							$r1 = $wpdb->get_results($s1);


							if(count($r1) == 0)
							{

								$s1 = "insert into ".$wpdb->prefix."project_milestone (owner, pid, uid, description_content, datemade, completion_date, amount, released)
								values('$uid','$projectss','$uid_of_winner','$description','$tm', '$completion_date', '$amount_text','0')";
								$wpdb->query($s1);

								$credits = $credits - $amount_text;
								projectTheme_update_credits($uid, $credits);


							}

							echo '<div class="alert alert-success">'.__('Your milestone payment has been created.','ProjectTheme').'</div>';

							$amount_text = '';
							$completion_date2 = '';
							$description = '';
				}
			}
			else
			{
				echo '<div class="alert alert-danger">';
					foreach($error1 as $ee) echo $ee.'<br/> ';
				echo '</div> <div class="clear10"></div>';
			}

		}
		$poid = $_GET['poid'];
		$pst = get_post($poid);
	?>






	<form method="post" action="<?php echo get_permalink(get_option('ProjectTheme_my_account_milestones_id')) ?>">

		<div class="form-group">
				<label for="exampleInputEmail1"><?php _e('Project Name:','ProjectTheme'); ?></label>
				<input type="hidden" value="<?php echo $poid ?>" name="projectss" /> <?php echo $pst->post_title ?>
			</div>



			<div class="form-group">
	<label for="exampleInputPassword1"><?php _e('Amount:','ProjectTheme'); ?></label>
	<input type="text" value="<?php echo isset($amount_text) ? $amount_text : ''; ?>" class="form-control" id="amount_text" name="amount_text" placeholder="<?php echo projecttheme_get_currency() ?>">
	</div>



	<div class="form-group">
	<label for="exampleInputPassword1"><?php _e('Description:','ProjectTheme'); ?></label>
	<textarea rows="5" cols="40" class="form-control" name="description" id="description"><?php echo str_replace("<br />", "", $description); ?></textarea>
	</div>



	<div class="form-group">
	<label for="exampleInputPassword1"><?php _e('Completion date:','ProjectTheme'); ?></label>
	<input type="text" size="25" id="completion_date" name="completion_date" autocomplete="off" class="form-control"  value="<?php echo isset($completion_date2) ? $completion_date2 : ''; ?>" />
	</div>


	<input type="submit" class="btn btn-primary" id="submit_milestone" value="<?php _e('Create Milestone','ProjectTheme') ?>" name="submit_milestone"  />

	</form>

					</div>
					</div>


	<?php
}

function ProjectTheme_my_account_milestones_area_function()
{
		global $current_user, $wpdb, $wp_query;
		get_currentuserinfo();
		$uid = $current_user->ID;

		do_action('pt_for_demo_work_3_0');

		pt_account_main_menu_new();



?>
<div class="row">

      <?php ProjectTheme_get_users_links(); ?>
    	<div  class="account-main-area col-xs-12 col-sm-8 col-md-8 col-lg-8">
        	<?php

				if(ProjectTheme_is_user_business($uid) == true):
				if(isset($_GET['release_id2'])):
				?>


						<h3 class="my-account-headline-1"><?php _e("Releasing Milestone Payment", "ProjectTheme"); ?></h3>






                		<?php

										global $wpdb;

							$release_id2 = $_GET['release_id2'];
							$s = "select * from ".$wpdb->prefix."project_milestone where id='$release_id2'";
							$r = $wpdb->get_results($s);

							$row = $r[0];

							if(count($r) > 0 and !isset($_POST['submits1yes_me_ok_p']))
							{



								$am = projecttheme_get_show_price($row->amount);
								$prj = get_post($row->pid);
								$prj = $prj->post_title;

								$serv = get_userdata($row->uid);
								$serv = $serv->user_login;

								?>
										<div class="alert alert-primary p-3">
                                <form method="post">
                                <input type="hidden" value="<?php echo $_GET['release_id2'] ?>" name="release_id2" />

                                    <?php printf(__('Are you sure you want to release the payment of <b>%s</b> for the project <b>%s</b> to the service provider <b>%s</b> ?','ProjectTheme'), $am, $prj, $serv); ?>
                                	<br/><br/>





                                    <input type="submit" name="submits1yes_me_ok_p" class="btn btn-primary" value="<?php _e('Yes, Release','ProjectTheme') ?>" value="yes" />
                                    <input type="submit" name="submits1no_me_thing_ok" class="btn btn-primary"  value="<?php _e('No, do Not release','ProjectTheme') ?>" value="no" />


                                </form></div>

                                <?php

							}
							elseif(isset($_POST['submits1yes_me_ok_p']))
							{

								echo '<div class="alert alert-success p-3">';
								echo _e('Your payment was released.','ProjectTheme');


								if($row->released == 0)
								{

										$pst  = get_post();
										$amount = $row->amount;

										$reason = sprintf(__("Milestone payment for project %s from user %s","shipme"), $prj, $current_user->user_login);
										projecttheme_add_history_log('1', $reason, $amount, $row->uid, get_current_user_id());

										$xcfreelancer = get_userdata($row->uid);
										$cr = projectTheme_get_credits($row->uid) + $row->amount;

									 	projectTheme_update_credits($row->uid, $cr);

										$reason = sprintf(__("Milestone payment for project %s for user %s","shipme"), $prj, $xcfreelancer->user_login);
										projecttheme_add_history_log('0', $reason, $amount,  get_current_user_id());

										$wpdb->query("update ".$wpdb->prefix."project_milestone set released='1' where id='".$row->id."'");


								}

								echo '</div>';


							}
							else echo 'my_err_00';

						?>






                <?php

				endif;

				?>

				<h3 class="my-account-headline-1"><?php _e("Create Milestone Payment", "ProjectTheme"); ?></h3>
            <div class="card">



                <div class="box_content">
            	<?php

					if(isset($_POST['submit_milestone']))
					{
						$nok = 0;

						$error1 = array();

						$projectss 					= $_POST['projectss'];
						$amount_text 				= trim($_POST['amount_text']);
						$completion_date 		= strtotime($_POST['completion_date']);
						$completion_date2 	= $_POST['completion_date'];
						$tm 								= current_time('timestamp',0);
						$description 				= nl2br($_POST['description']);
						$pid 								= $projectss;

						//----------------------------------------

						if(empty($projectss)) { $nok = 1; $error1[] = __('You need to select a project for your payment.','ProjectTheme'); }
						if(empty($amount_text) or !is_numeric($amount_text)) { $nok = 1; $error1[] = __('Make sure you type in a payment amount for your milestone, and its numeric.','ProjectTheme'); }
						if(empty($description) ) { $nok = 1; $error1[] = __('Please provide a description for your milestone payment.','ProjectTheme'); }
						if($completion_date < $tm) { $nok = 1; $error1[] = __('The completion date must be a date in the future.','ProjectTheme'); }

						if($nok == 0)
						{
							$projectTheme_get_winner_bid 	= projectTheme_get_winner_bid($pid);
							$uid_of_winner 					= $projectTheme_get_winner_bid->uid;

							$credits = projectTheme_get_credits(get_current_user_id());

							if($credits < $amount_text)
							{

								echo '<div class="alert alert-danger">';
									echo sprintf(__('You do not have enough balance in your account to create this milestone. <a href="%s" class="">Click here</a> to deposit more.','ProjectTheme'), ProjectTheme_get_payments_page_url('deposit'));
								echo '</div> <div class="clear10"></div>';

							}
							else {


										$s1 = "select * from ".$wpdb->prefix."project_milestone where pid='$pid' and uid='$uid_of_winner' AND completion_date='$completion_date' and released='0' ";
										$r1 = $wpdb->get_results($s1);


										if(count($r1) == 0)
										{

											$s1 = "insert into ".$wpdb->prefix."project_milestone (owner, pid, uid, description_content, datemade, completion_date, amount, released)
											values('$uid','$projectss','$uid_of_winner','$description','$tm', '$completion_date', '$amount_text','0')";
											$wpdb->query($s1);

											$credits = $credits - $amount_text;
											projectTheme_update_credits($uid, $credits);


										}

										echo '<div class="alert alert-success">'.__('Your milestone payment has been created.','ProjectTheme').'</div>';

										$amount_text = '';
										$completion_date2 = '';
										$description = '';
							}
						}
						else
						{
							echo '<div class="alert alert-danger">';
								foreach($error1 as $ee) echo $ee.'<br/> ';
							echo '</div> <div class="clear10"></div>';
						}

					}


				?>






        <form method="post" action="<?php echo get_permalink(get_option('ProjectTheme_my_account_milestones_id')) ?>">

					<div class="form-group">
					    <label for="exampleInputEmail1"><?php _e('Select Project:','ProjectTheme'); ?></label>
							<?php $xx = ProjectTheme_get_my_awarded_projects3($uid); echo $xx == false ? _e('There are no projects in progress.','ProjectTheme') : $xx; ?>
					  </div>



						<div class="form-group">
    <label for="exampleInputPassword1"><?php _e('Amount:','ProjectTheme'); ?></label>
    <input type="text" value="<?php echo isset($amount_text) ? $amount_text : ''; ?>" class="form-control" id="amount_text" name="amount_text" placeholder="<?php echo projecttheme_get_currency() ?>">
  </div>



			<div class="form-group">
		<label for="exampleInputPassword1"><?php _e('Description:','ProjectTheme'); ?></label>
		<textarea rows="5" cols="40" class="form-control" name="description" id="description"><?php echo str_replace("<br />", "", $description); ?></textarea>
		</div>



		<div class="form-group">
		<label for="exampleInputPassword1"><?php _e('Completion date:','ProjectTheme'); ?></label>
		<input type="text" size="25" id="completion_date" name="completion_date" autocomplete="off" class="form-control"  value="<?php echo isset($completion_date2) ? $completion_date2 : ''; ?>" />
		</div>


		<input type="submit" class="btn btn-primary" id="submit_milestone" value="<?php _e('Create Milestone','ProjectTheme') ?>" name="submit_milestone"  />

 </form>

                </div>
                </div>


                <?php endif; ?>



<h3 class="my-account-headline-1"><?php _e("Outgoing Milestone Payments", "ProjectTheme"); ?></h3>
            <div class="card">


                <div class="table-responsive">

                <?php

					$s = "select * from ".$wpdb->prefix."project_milestone where owner='$uid' AND released='0' order by datemade desc";
					$r = $wpdb->get_results($s);

					if(count($r) > 0)
					{
						?>
                        <table class="table table-hover table-outline table-vcenter   card-table"  >
                        	<thead><tr>
                            	<th><?php _e('Project','ProjectTheme'); ?></th>
                                <th><?php _e('Service Provider','ProjectTheme'); ?></th>
                                <th><?php _e('Amount','ProjectTheme'); ?></th>
                                <th><?php _e('Description','ProjectTheme'); ?></th>
                                <th><?php _e('Due Date','ProjectTheme'); ?></th>
                                <th><?php _e('Options','ProjectTheme'); ?></th>
                            </tr> </thead><tbody>


                        <?php
							foreach($r as $row):

							$post_p 						= get_post($row->pid);
							$project_title 			= $post_p->post_title;
							$user_of_milestone 	= get_userdata($row->uid);
						?>
                				<tr>
                                	<td><?php echo '<a href="'.get_permalink($row->pid).'">'.$project_title.'</a>' ?></td>
                                    <td><?php echo '<a href="'.ProjectTheme_get_user_profile_link($user_of_milestone->ID).'">'.$user_of_milestone->user_login.'</a>' ?></td>
                                    <td><?php echo projecttheme_get_show_price($row->amount) ?></td>
                                    <td><?php echo $row->description_content ?></td>
                                    <td><?php echo date_i18n('d-M-Y',$row->completion_date) ?></td>
                                	<td><a href="<?php echo projectTheme_release_milestone_link($row->id) ?>" class="btn btn-primary btn-sm"><?php _e('Release Payment','ProjectTheme') ?></a></td>
                                </tr>

                		<?php endforeach; ?>

									</tbody>  </table>

                <?php } else { echo '<div class="p-3">'; _e('There are no outgoing payments.','ProjectTheme'); echo '</div>'; } ?>

                </div>
                </div>





<h3 class="my-account-headline-1"><?php _e("Incoming Milestone Payments", "ProjectTheme"); ?></h3>
                  <div class="card">



                <div class="table-responsive">



                <?php

					$s = "select * from ".$wpdb->prefix."project_milestone where uid='$uid' AND released='0' order by datemade desc";
					$r = $wpdb->get_results($s);

					if(count($r) > 0)
					{
						?>
                          <table class="table table-hover table-outline table-vcenter   card-table"  ><thead>
                        	<tr>
                            	<th ><?php _e('Project','ProjectTheme'); ?></th>
                                <th><?php _e('Service Provider','ProjectTheme'); ?></th>
                                <th><?php _e('Amount','ProjectTheme'); ?></th>
                                <th><?php _e('Description','ProjectTheme'); ?></th>
                                <th><?php _e('Due Date','ProjectTheme'); ?></th>

																<th><?php _e('Options','ProjectTheme'); ?></th>

                            </tr></thead><tbody>


                        <?php
							foreach($r as $row):

							$post_p = get_post($row->pid);
							$project_title = $post_p->post_title;
							$user_of_milestone = get_userdata($row->uid);
						?>
                				<tr>
                                	<td><?php echo '<a href="'.get_permalink($row->pid).'">'.$project_title.'</a>' ?></td>
                                    <td><?php echo '<a href="'.ProjectTheme_get_user_profile_link($user_of_milestone->ID).'">'.$user_of_milestone->user_login.'</a>' ?></td>
                                    <td><?php echo projecttheme_get_show_price($row->amount) ?></td>
                                    <td><?php echo $row->description_content ?></td>
                                    <td><?php echo date_i18n('d-M-Y',$row->completion_date) ?></td>

																		  <td><?php echo date_i18n('d-M-Y',$row->completion_date) ?></td>

                                </tr>

                		<?php endforeach; ?>

									</tbody>  </table>

                <?php } else { echo '<div class="p-3">'; _e('There are no incoming payments.','ProjectTheme'); echo '</div>'; } ?>


                </div>
                </div>


                </div>  </div>
<?php


}



function projecttheme_milestone_nr($pid)
{
	global $wpdb;
	$s = "select * from ".$wpdb->prefix."project_milestone where pid='$pid' AND released='0' order by datemade desc";
	$r = $wpdb->get_results($s);

	return count($r);

}

?>
