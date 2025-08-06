<?php

function ProjectTheme_my_account_view_disp_area_function()
{
		 ob_start();

		global $current_user, $wpdb, $wp_query;
		$current_user = wp_get_current_user();
		$uid = $current_user->ID; $cvs_uid = $uid;

		$disp_id 	= $_GET['disp_id'];
		$s 			= "select * from ".$wpdb->prefix."project_disputes where id='$disp_id'";
		$r 			= $wpdb->get_results($s);
		$row 		= $r[0];
		$dispute_object = $row;

		$pid 		= $row->pid;

		if($row->uid1 != $uid and $uid != $row->uid2) {   }
		else {

		$the_receiver_is = $uid;
		if($row->initiator == $uid) $the_receiver_is = $row->receiver; else $the_receiver_is = $row->uid2;



		if(isset($_POST['withdraw_and_close']))
		{
					// this is submitted by the intiator of the dispute (usually the buyer)
					//solution 0 - open, 1 - buyer agreed to close, 2 - freelancer accepted as liability , 3 - sorted by admin, 4 - closed on accepting offer
					$tm = current_time('timestamp');

					$s = "update ".$wpdb->prefix."project_disputes set solution='1', closedon='$tm' where id='$disp_id'";
					$r = $wpdb->query($s);

					$message = __('The dispute was closed by the initiator. Project continues.','disputes');


					//---------

					$s = "insert into ".$wpdb->prefix."project_disputes_messages
					(receiver, uid, description, datemade, pid, disputeid, file_attached)
					values('$the_receiver_is','$cvs_uid','$message','$tm','$pid','$disp_id', '$attach_id' )";

					$r = $wpdb->query($s);

					//---------

					$s 			= "select * from ".$wpdb->prefix."project_disputes where id='$disp_id'";
					$r 			= $wpdb->get_results($s);
					$row 		= $r[0];



					$disputeIsClosed = 1;
		}


		$post = get_post($row->pid);
		$row_general = $row;



			get_template_part ( 'lib/my_account/aside-menu'  );

			do_action('pt_for_demo_work_3_0');




		?>

		<div class="page-wrapper" style="display:block">
			<div class="container-fluid"  >


						<div class="container">

								<h5 class="my-account-headline-1"><?php printf(__("Dispute on project: %s","ProjectTheme"), $post->post_title); ?></h5>



								<div class="row">

		    	<div class=" col-xs-12 col-sm-12 col-md-12 col-lg-12">

						<?php


									if($disputeIsClosed == 1)
									{
										?>

														<div class="alert alert-success"><?php _e('Your answer was submitted, the dispute is closed now.','disputes') ?></div>

										<?php
									}


						 ?>


							<div class="card p-3">
								<ul class="list-group">

									<?php
													if($row->solution != 0)
													{

																	$winner = $row->winner;
																	$winnerusr = get_userdata($winner);

																	if($row->solution == 1) $winner_string = __('Closed by initiator','disputes');

																	else $winner_string = $winnerusr->user_login;
									 ?>

									<li class="list-group-item"> <i class="fas fa-folder"></i> <?php printf(__('Dispute Winner: %s','ProjectTheme'),  $winner_string ) ?></li>

								<?php } ?>


							<?php

										$reason = get_option('pt_disp_reason_' . $row->reason);

							 ?>

									<li class="list-group-item"> <i class="fas fa-folder"></i> <?php printf(__('%s Reason for dispute: %s','ProjectTheme'), '<span class="spn_bold">',  '</span>' . $reason) ?></li>
																		<li class="list-group-item"> <i class="fas fa-folder"></i> <?php printf(__('%s Project Name: %s','ProjectTheme'), '<span class="spn_bold">',  '</span><a href="'.get_permalink($pid).'">'.$post->post_title.'</a>') ?></li>
									<li class="list-group-item"> <i class="far fa-calendar-alt"></i> <?php printf(__('%s Dispute Started On: %s','ProjectTheme'), '<span class="spn_bold">',  '</span>'.date_i18n('d-m-Y H:i:s',$row->datemade)) ?></li>
									<li class="list-group-item"> <i class="fas fa-user-tag"></i>  <?php printf(__('%s Dispute Started By: %s','ProjectTheme'), '<span class="spn_bold">',  '</span>'.'<a href="'.ProjectTheme_get_user_profile_link($row->initiator).'">'.project_theme_get_name_of_user($row->initiator) .'</a>') ?></li>
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

								$receiver = $row->uid1;


								if($row_general->solution == 0)
								{

									if($cvs_uid == $row->initiator)
									{

										if(isset($_GET['noaccept']))
										{
											$xid = $_GET['noaccept'];

											$s = "select * from ".$wpdb->prefix."project_disputes_offers where disputeid='".$xid."'";
											$r = $wpdb->get_results($s);
											$r123 = $r[0];

											$s = "update ".$wpdb->prefix."project_disputes_offers set answer='2' where id='".$xid."'" ;
											$wpdb->query($s);

										}

										if(isset($_GET['yesaccept']))
										{
											$xid = $_GET['yesaccept'];
											$s = "select * from ".$wpdb->prefix."project_disputes_offers where disputeid='".$xid."'";
											$r = $wpdb->get_results($s);
											$r123 = $r[0];

											$s = "select * from ".$wpdb->prefix."project_disputes where id='".$r123->disputeid."'";
											$r = $wpdb->get_results($s);
											$r456 = $r[0];
											$oid = $r456->oid;

											$order = new project_orders($oid);

											$s = "update ".$wpdb->prefix."project_disputes_offers set answer='1' where id='".$xid."'" ;
											$wpdb->query($s);
										}



										$s = "select * from ".$wpdb->prefix."project_disputes_offers where disputeid='$disp_id'";
										$r = $wpdb->get_results($s);


										if(count($r) > 0)
										{
												$we_have_offer = 1;
												$row_s = $r[0];


												if($row_s->answer == 0)
												{
												?>

												<div class="alert alert-success">
																<p><?php echo __('You have received an offer on this dispute from the other party.','disputes') ?></p>
																<p><?php echo sprintf(__('Amount: %s','disputes'), projectTheme_get_show_price($row_s->amount)); ?></p>
																<p><?php echo sprintf(__('Message: %s','disputes'), $row_s->description); ?></p>

																<p><a href="<?php echo $viewdispute_id."&yesaccept=" . $row_s->id; ?>" class="btn btn-sm btn-primary"><?php echo sprintf(__('Yes, accept','disputes')) ?></a> <a class="btn btn-sm btn-primary" href="<?php echo $viewdispute_id."&noaccept=" . $row_s->id; ?>"><?php echo sprintf(__('No, reject','disputes')) ?></a></p>
												</div>


												<?php
											}elseif($row_s->answer == 2) {
												?>

															<div class="alert alert-secondary">
																	<p><?php echo sprintf(__('There was an offer of %s and you have rejected it.','disputes'),  projectTheme_get_show_price($row_s->amount)) ?></p>
															</div>

												<?php
											}
											elseif($row_s->answer == 1) {
												?>

															<div class="alert alert-success">
																	<p><?php echo sprintf(__('There was an offer of %s and you have accepted it.','disputes'),  projectTheme_get_show_price($row_s->amount)) ?></p>
															</div>

												<?php
											}
										}




								?>

									 <a href="#" class="btn btn-primary" data-toggle="modal" data-target="#exampleModal"><?php _e('Withdraw & Cancel Dispute','ProjectTheme') ?></a>




									 <!-- accept full refund box -->

									<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
									 <div class="modal-dialog" role="document">
										 <div class="modal-content">
											 <form method="post" action="<?php echo $viewdispute_id . "&withdraw_and_close=1" ?>" > <input type="hidden" value='1' name='withdraw_and_close' />
											 <div class="modal-header">
												 <h5 class="modal-title" id="exampleModalLabel"><?php _e('Cancel dispute','disputes') ?></h5>
												 <button type="button" class="close" data-dismiss="modal" aria-label="Close">
													 <span aria-hidden="true">&times;</span>
												 </button>
											 </div>
											 <div class="modal-body">
												<?php


															 echo '<p>';
																_e('I want to cancel the dispute and continue the project if still open.','disputes');
																echo '</p>';



												 ?>
											 </div>
											 <div class="modal-footer">
												 <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php _e('Close','disputes') ?></button>
												 <button type="submit" class="btn btn-primary"><?php _e('Submit','disputes') ?></button>
											 </div> </form>
										 </div>
									 </div>
								 </div>

								 <!-- ### -->



									<?php } else{


												$send_offer_link = get_permalink(get_option('ProjectTheme_my_account_send_dispute_offer_id'));
												if(ProjectTheme_using_permalinks()) $send_offer_link = $send_offer_link ."?disp_id=" . $disp_id;
												else $send_offer_link = $send_offer_link ."&disp_id=" . $disp_id;

												global $wpdb;




												if(isset($_POST['send_offer']))
												{
														$message 	= $_POST['message'];
														$amount 	= $_POST['amount'];
														$tm 			= current_time('timestamp');

														$order = new project_orders($dispute_object->oid);
														$order_obj = $order->get_order();

														$isIt = $order->has_escrow_deposited();

														if($isIt != false)
														{
																$order_total = $order_obj->order_total_amount;
																if($amount > $order_total)
																{
																		?>
																						<div class="alert alert-danger"><?php _e('Your offer must be lower or equat to the total amount.','disputes'); ?></div>
																		<?php
																}
																else {

																		$sender = get_current_user_id();

																		$s = "select id from ".$wpdb->prefix."project_disputes_offers where disputeid='$disp_id'";
																		$r = $wpdb->get_results($s);

																		if(count($r) == 0)
																		{

																			$s = "insert into ".$wpdb->prefix."project_disputes_offers (disputeid, sender, receiver, description, datemade, amount) values('$disp_id','$sender','$receiver','$message', '$tm', '$amount')";
																			$wpdb->query($s);

																		}

															}
													}
													else {
														// code...
														?>
																		<div class="alert alert-danger"><?php _e('You cant submit an offer, because the escrow hasnt been deposited.','disputes'); ?></div>
														<?php
													}
												}


												$s = "select * from ".$wpdb->prefix."project_disputes_offers where disputeid='$disp_id'";
												$r = $wpdb->get_results($s);



												if(count($r) > 0)
												{
														$we_have_offer = 1;
														$row_s = $r[0];

														if($row_s->answer == 0)
														{
														?>

														<div class="alert alert-success">
																		<p><?php echo __('You have sent an offer to the other party. They will be notified and accept or reject this.','disputes') ?></p>
																		<p><?php printf(__('Message: %s','disputes'), $row_s->description) ?></p>
																		<p><?php printf(__('Amount: %s','disputes'), projectTheme_get_show_price($row_s->amount) ) ?></p>
														</div>

														<?php
													}
														elseif($row_s->answer == 2) {
															?>

																		<div class="alert alert-secondary">
																				<p><?php echo sprintf(__('You have sent an offer of %s and the other party has rejected it.','disputes'),  projectTheme_get_show_price($row_s->amount)) ?></p>
																		</div>

															<?php
														}
														elseif($row_s->answer == 1) {
															?>

																		<div class="alert alert-success">
																				<p><?php echo sprintf(__('You have sent an offer of %s and the other party has accepted it.','disputes'),  projectTheme_get_show_price($row_s->amount)) ?></p>
																		</div>

															<?php
														}



												}




										?>


									 <a href="" class="btn btn-primary" data-toggle="modal" data-target="#exampleModal"><?php _e('Accept full refund & Close Dispute','disputes') ?></a>
								<?php

										$enable_this = get_option('projecttheme_enable_send_offer_disputes');

								if($we_have_offer != 1 and $enable_this == "yes" ) { ?>	 <a href="<?php echo $send_offer_link ?>" class="btn btn-primary" data-toggle="modal" data-target="#send-offer-modal"><?php _e('Send Offer','disputes') ?></a> <?php } ?>


<!-- send offer box -->


<div class="modal fade" id="send-offer-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
 <div class="modal-dialog" role="document">
	 <div class="modal-content">
		 <form method="post" action="<?php echo $viewdispute_id . "&send_offer=1" ?>" > <input type="hidden" value='1' name='send_offer' />
		 <div class="modal-header">
			 <h5 class="modal-title" id="exampleModalLabel"><?php _e('Send Offer','disputes') ?></h5>
			 <button type="button" class="close" data-dismiss="modal" aria-label="Close">
				 <span aria-hidden="true">&times;</span>
			 </button>
		 </div>
		 <div class="modal-body">

			 <div class="form-group">
     <label for="exampleFormControlInput1"><?php _e('Your message','disputes') ?></label>
     <textarea name="message" required class="form-control" rows="3"></textarea>
   </div>


	 <div class="form-group">
 <label for="exampleFormControlInput1"><?php _e('Amount to offer','disputes') ?></label>

 <div class="input-group mb-3">
   <div class="input-group-prepend">
     <span class="input-group-text"><?php echo projecttheme_currency() ?></span>
   </div>
   <input type="text" class="form-control" name="amount" require />

 </div>
</div>



		 </div>
		 <div class="modal-footer">
			 <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php _e('Close','disputes') ?></button>
			 <button type="submit" class="btn btn-primary"><?php _e('Submit','disputes') ?></button>
		 </div> </form>
	 </div>
 </div>
</div>



										<!-- accept full refund box -->

									 <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
									  <div class="modal-dialog" role="document">
									    <div class="modal-content">
												<form method="post" action="<?php echo $viewdispute_id . "&accept_full_refund=1" ?>" >
									      <div class="modal-header">
									        <h5 class="modal-title" id="exampleModalLabel"><?php _e('Cancel dispute and full refund','disputes') ?></h5>
									        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
									          <span aria-hidden="true">&times;</span>
									        </button>
									      </div>
									      <div class="modal-body">
									       <?php


												 				echo '<p>';
																 _e('I want to close the dispute and offer a full refund to the buyer.','disputes');
																 echo '</p>';

																 	 echo '<p>';
																 _e('Note that the project will get automatically closed if not already.','disputes');
																 echo '</p>';

												  ?>
									      </div>
									      <div class="modal-footer">
									        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php _e('Close','disputes') ?></button>
									        <button type="submit" class="btn btn-primary"><?php _e('Submit','disputes') ?></button>
									      </div> </form>
									    </div>
									  </div>
									</div>

									<!-- ### -->


										<?php
									}} else {

												?>
													<div class="alert alert-warning">
														<?php _e('This dispute is closed, you cannot submit anymore messages or do any other actions.','disputes') ?>
													</div>

												<?php

									} ?>


							</div>	</div>


							<div class="card">
            	<div class="box_title">
				<?php echo $row->title; ?>
				</div>
				</div>




							<?php

																	if(isset($_POST['send_a']))
																	{

																		require_once(ABSPATH . "wp-admin" . '/includes/file.php');
																		require_once(ABSPATH . "wp-admin" . '/includes/image.php');


																		if(!empty($_FILES['file_instant']['name'])):

																			$pids = 0;
																			$upload_overrides 	= array( 'test_form' => false );
																			$uploaded_file 		= wp_handle_upload($_FILES['file_instant'], $upload_overrides);

																			$file_name_and_location = $uploaded_file['file'];
																			$file_title_for_media_library = $_FILES['file_instant']['name'];

																			$arr_file_type 		= wp_check_filetype(basename($_FILES['file_instant']['name']));
																			$uploaded_file_type = $arr_file_type['type'];



																			if($uploaded_file_type == "application/zip" or $uploaded_file_type == "application/pdf" or $uploaded_file_type == "application/msword" or $uploaded_file_type == "application/msexcel" or
																			$uploaded_file_type == "application/doc" or $uploaded_file_type == "application/docx" or
																			$uploaded_file_type == "application/xls" or $uploaded_file_type == "application/xlsx" or $uploaded_file_type == "application/csv" or $uploaded_file_type == "application/ppt" or
																			$uploaded_file_type == "application/pptx" or $uploaded_file_type == "application/vnd.ms-excel"
																			or $uploaded_file_type == "application/vnd.ms-powerpoint" or $uploaded_file_type == "application/vnd.openxmlformats-officedocument.presentationml.presentation"

																			or $uploaded_file_type == "application/octet-stream"
																			or $uploaded_file_type == "image/png"
																			or $uploaded_file_type == "image/jpg"  or $uploaded_file_type == "image/jpeg"

																				or $uploaded_file_type == "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
																				or $uploaded_file_type == "application/vnd.openxmlformats-officedocument.wordprocessingml.document"  )
																			{



																				$attachment = array(
																								'post_mime_type' => $uploaded_file_type,
																								'post_title' => addslashes($file_title_for_media_library),
																								'post_content' => '',
																								'post_status' => 'inherit',
																								'post_parent' =>  0,

																								'post_author' => $uid,
																							);

																				$attach_id 		= wp_insert_attachment( $attachment, $file_name_and_location, $pids );
																				$attach_data 	= wp_generate_attachment_metadata( $attach_id, $file_name_and_location );
																				wp_update_attachment_metadata($attach_id,  $attach_data);




																			} else $error_mm = '1';

																		endif;



																		$message 	= projecttheme_sanitize_string($_POST['message_a']);


																		if(strlen($message) < 2) $error_mm = 1;

																		if( $error_mm != "1"):



																		//*********************************************

																		$current_user = wp_get_current_user();
																		$tm = projecttheme_sanitize_string($_POST['tm']); //current_time('timestamp',0);

																		$sr = "select * from ".$wpdb->prefix."project_disputes_messages where uid='$cvs_uid' and datemade='$tm'";
																		$rr = $wpdb->get_results($sr);

																		if(count($rr) == 0)
																		{

																			$s = "insert into ".$wpdb->prefix."project_disputes_messages
																			(receiver, uid, description, datemade, pid, disputeid, file_attached)
																			values('$the_receiver_is','$cvs_uid','$message','$tm','$pid','$disp_id', '$attach_id' )";

																			$wpdb->query($s);


																			//------------------------------

																			if($ProjectTheme_moderate_private_messages == false)
																				ProjectTheme_send_email_on_priv_mess_received($myuid, $uids);
																			else
																			{
																				//send message to admin to moderate

																			}


																		}

																	//-----------------------
																		?>


																					<div class="alert alert-success mb-3">
																						 <?php

																		 if($ProjectTheme_moderate_private_messages == false)
																			_e('Your message has been sent.','ProjectTheme');
																		 else
																				_e('Your message has been sent but the receiver will receive it only after moderation.','ProjectTheme')

																			?>
																						</div>


																						<?php

																		else:

																			if($error_mm == "1") {

																				if(strlen($message) < 2) echo '<div class="error">'.__('You need to type in a message.','ProjectTheme') . '</div>';
																				else echo '<div class="error">'. sprintf(__('Wrong File format: %s','ProjectTheme'), $uploaded_file_type) . '</div>';

																			}

																		endif;


																	}

																	 ?>




		<div class="clear5"></div>
							<?php

							if($row->initiator  == $current_user->ID) $class1 = 'my_bk1_class';
							else $class1 = '';

								?>

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
					<div class="clear5"></div>

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



										if($row_general->solution == 0)
										{


								 ?>



				<div class="card">



										<form method="post" enctype="multipart/form-data" action="<?php echo  (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>">
											<input name="send_to" type="hidden" value="<?php echo $row->user; ?>" />
												<input name="parent" type="hidden" value="<?php echo $row->id; ?>" />

										<input type="hidden" name="tm" value="<?php echo current_time('timestamp',0); ?>" />
										<table class="table">


										 <script>

					jQuery(document).ready(function(){
					tinyMCE.init({
							mode : "specific_textareas",
							theme : "modern",
							/*plugins : "autolink, lists, spellchecker, style, layer, table, advhr, advimage, advlink, emotions, iespell, inlinepopups, insertdatetime, preview, media, searchreplace, print, contextmenu, paste, directionality, fullscreen, noneditable, visualchars, nonbreaking, xhtmlxtras, template",*/
							editor_selector :"tinymce-enabled"
						});
					});

					</script>

										<tr>

										<td><textarea name="message_a" required class="form-control" placeholder="<?php _e("Message", "ProjectTheme"); ?>" rows="6" ></textarea></td>
										</tr>


										<tr>
										<td><input type="file" name="file_instant" class="" /> <?php _e('Only PDF, ZIP, Office files and Images.','ProjectTheme'); ?></td>
										</tr>




										 <tr>

										<td><input name="send_a" class="btn btn-primary" type="submit" value="<?php _e("Send Message",'ProjectTheme'); ?>" /></td>
										</tr>

										</table>
								</form>

							</div> <?php } else { ?>



								<div  class="alert alert-warning"><?php _e('Dispute is closed','disputes') ?></div>


								<?php

							}

							?>


		 </div>	 </div></div></div></div>
		<?php

	}
	
	 $output = ob_get_contents();
	 ob_end_clean();

	 return $output;
}


?>
