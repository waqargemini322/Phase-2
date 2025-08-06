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

function ProjectTheme_my_account_disputes_area_function()
{

	ob_start();

		global $current_user, $wpdb, $wp_query;
		$current_user = wp_get_current_user();
		$uid = $current_user->ID;



					get_template_part ( 'lib/my_account/aside-menu'  );

		do_action('pt_for_demo_work_3_0');



?>

<div class="page-wrapper" style="display:block">
	<div class="container-fluid"  >




				<div class="container">



						<h5 class="my-account-headline-1"><?php echo __('Open Disputes','pt_affiliates'); ?></h5>


            <div class="card">



                <div class="table-responsive">


									<?php

										$s = "select * from ".$wpdb->prefix."project_disputes where (uid1='$uid' or uid2='$uid') AND solution='0'";
										$r = $wpdb->get_results($s);

										if(count($r) == 0) {  echo '<div class="p-3">';	_e('There are no open disputes.','ProjectTheme');  echo '</div>';  }
										else {

											?>

											<table class="table table-hover table-outline table-vcenter   card-table">

											<thead><thead>
													<th><?php _e('Project Name','ProjectTheme') ?></th>
													<th><?php _e('Amount','ProjectTheme') ?></th>
													<th><?php _e('Opened By','ProjectTheme') ?></th>
													<th><?php _e('Opened On','ProjectTheme') ?></th>
													<th><?php _e('Details','ProjectTheme') ?></th>

											</tr></thead><tbody>

											<?php


												foreach($r as $row)
												{
															$pst = get_post($row->pid);
															$bid = projectTheme_get_winner_bid($row->pid);
															$initiator = get_userdata($row->initiator);
												?>

															<tr>
																	<td><?php echo $pst->post_title ?></td>
																	<td><?php echo projectTheme_get_show_price($bid->bid) ?></td>
																	<td><?php echo $initiator->user_login ?></td>
																	<td><?php echo date_i18n('d-m-Y H:i:s', $row->datemade) ?></td>
																	<td><a href="<?php echo projecttheme_view_dispute_thing_link($row->id) ?>" class="btn btn-primary"><?php _e('View','ProjectTheme') ?></a></td>

															</tr>


												<?php
										}

										echo '</table>';

									}
									 ?>


                </div>
                </div>





													<h3 class="my-account-headline-1"><?php _e("Closed Disputes", "ProjectTheme"); ?></h3>
            <div class="card p-2">




									<?php
										global $wpdb;
										$s = "select * from ".$wpdb->prefix."project_disputes where (uid1='$uid' or uid2='$uid') AND solution!='0'";
										$r = $wpdb->get_results($s);

										$wpdb->print_error();

										if(count($r) == 0) {  	echo '<div class="p-3">'; _e('There are no closed disputes.','ProjectTheme'); echo '</div>';  }
										else {

												?>

												<table class="table table-hover table-outline table-vcenter   card-table">

												<thead><thead>
														<th><?php _e('Project Name','ProjectTheme') ?></th>
														<th><?php _e('Amount','ProjectTheme') ?></th>
														<th><?php _e('Opened By','ProjectTheme') ?></th>
														<th><?php _e('Closed On','ProjectTheme') ?></th>
														<th><?php _e('Details','ProjectTheme') ?></th>

												</tr></thead><tbody>


													<?php


														foreach($r as $row)
														{
																	$pst = get_post($row->pid);
																	$bid = projectTheme_get_winner_bid($row->pid);
																	$initiator = get_userdata($row->initiator);
														?>

																	<tr>
																			<td><?php echo $pst->post_title ?></td>
																			<td><?php echo projectTheme_get_show_price($bid->bid) ?></td>
																			<td><?php echo $initiator->user_login ?></td>
																			<td><?php echo date_i18n('d-m-Y H:i:s', $row->closedon) ?></td>
																			<td><a href="<?php echo projecttheme_view_dispute_thing_link($row->id) ?>" class="btn btn-primary"><?php _e('View','ProjectTheme') ?></a></td>

																	</tr>


														<?php
												}

												echo '</table>';



										}
									 ?>



                </div>



                </div>  </div> </div>
<?php

$page = ob_get_contents();
	ob_end_clean();

	return $page;


}

?>
