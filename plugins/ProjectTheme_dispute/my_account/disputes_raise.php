<?php

function ProjectTheme_my_account_disputes_raise_area_function()
{
ob_start();

  global $current_user, $wpdb, $wp_query;
  get_currentuserinfo();
  $uid = $current_user->ID;

  $x = '#' . $_GET['oid'];
  $oid = $_GET['oid'];

  //-----------

  $orderObject  = new project_orders($oid);
  $order = $orderObject->get_order();
  $pid    = $order->pid;

  //------

  $pst = get_post($pid);
  $bid = projectTheme_get_winner_bid($pid);

  $winner_user = get_userdata($bid->uid);



  					get_template_part ( 'lib/my_account/aside-menu'  );

  		do_action('pt_for_demo_work_3_0');

?>


<div class="page-wrapper" style="display:block">
	<div class="container-fluid"  >

    <div class="container">






<div class="row">


   <div class="account-main-area col-xs-12 col-sm-12 col-md-12 col-lg-12">

     <h3 class="my-account-headline-1"><?php printf(__("Raise Dispute for project %s", "ProjectTheme"), $x); ?></h3>

          <div class="card">



              <div class="box_content">

                <?php


                    if(isset($_POST['subdisp']))
                    {
                          $prid       = $_POST['prid'];
                          $issue      = strip_tags($_POST['prid']);
                          $tm         = current_time( 'timestamp'); // $gmt = 0 )
                          $initiator  = get_current_user_id();
                          $oid        = $_POST['oid'];
                          $issue      = strip_tags($_POST['issue']);
                          $reason      =  ($_POST['reason']);


                             $uid1 = get_current_user_id(); //$pst->post_author;

                             if($bid->uid == get_current_user_id()) $uid2 = $pst->post_author;
                             else $uid2 = $bid->uid;


                             $pst = get_post($prid);


                          //--------

                          global $wpdb;

                          $s = "select * from ".$wpdb->prefix."project_disputes where oid='$oid' and initiator='$uid'";
                          $r = $wpdb->get_results($s);

                          if(count($r) == 0)
                          {
                            $s = "insert into ".$wpdb->prefix."project_disputes (oid, initiator, pid, datemade, comment, uid1, uid2, reason) values('$oid','$initiator','$prid','$tm','$issue','$uid1','$uid2', '$reason')";
                            $wpdb->query($s);

                            $lastid = $wpdb->insert_id;

                            $user_thing = get_userdata($uid2);
                            $user_init  = get_userdata($uid1);

                            $viewdispute_id = get_permalink(get_option('ProjectTheme_my_account_view_disp_id'));
                            if(ProjectTheme_using_permalinks()) $viewdispute_id = $viewdispute_id ."?disp_id=" . $lastid;
                            else $viewdispute_id = $viewdispute_id ."&disp_id=" . $lastid;

                            // sending email ---------------

                            $title = "Dispute was initiated for the project: " . $pst->post_title;
                            $text = "Hello, ".$user_thing->user_login.'<br/>'.
                            "The user: ".$user_init->user_login." started a dispute on the project: ".$pst->post_title ."<br/>".
                            "Go to: ".$viewdispute_id." and check the details.";

                            projecttheme_send_email($user_thing->user_email, $title, $text);

                            //----------- sending admin email -----

                            $order = new project_orders($oid);
                            $order_obj = $order->get_order();
                            $total = $order_obj->order_total_amount;

                            $title = "Dispute was initiated for the project: " . $pst->post_title;
                            $text = "Hello, admin<br/>".
                            "The user: ".$user_init->user_login." started a dispute on the project: ".$pst->post_title ."<br/>".
                            "The dispute was sent against: ".  $user_thing->user_login .'<br/>'.
                            "Total project: " . projectTheme_get_show_price($total).'<br/>'.
                            "Go to: " . get_admin_url();

                            projecttheme_send_email(get_bloginfo('admin_email'), $title, $text);


                          }


                          echo "<div class=''>";
                            printf(__('Your dispute has been sent. The admin will analyze this and you will receive a notification when
                            something new will be added to your dispute. You can <a href="%s"><b>go back</b></a> to the disputes panel to see updates.','ProjectTheme') , get_permalink(get_option('ProjectTheme_my_account_disputes_id')));
                          echo '</div>';
                    }
                    else {


                        $closed_date = get_post_meta($pid,  'closed_date',  true);
                        $exp_del   = get_post_meta($pid,  'expected_delivery',  true);

                 ?>

                    <form method="post">

                      <input type="hidden" name="prid" value="<?php echo $pid; ?>" />
                      <input type="hidden" name="oid" value="<?php echo $oid; ?>" />




                          <div class="row row-special-dispute-1">

                            <div class="col-xs-12 col-sm-5 col-md-5 col-lg-5 col-ttls-disp"><?php echo __('Project Title', 'ProjectTheme'); ?></div>
                            <div class="col-xs-12 col-sm-7 col-md-7 col-lg-7"><?php echo $pst->post_title ?></div>
                          </div>


                          <div class="row row-special-dispute-1">

                            <div class="col-xs-12 col-sm-5 col-md-5 col-lg-5 col-ttls-disp"><?php echo __('Project Winner', 'ProjectTheme'); ?></div>
                            <div class="col-xs-12 col-sm-7 col-md-7 col-lg-7"><?php echo $winner_user->user_login ?></div>
                          </div>



                          <div class="row row-special-dispute-1">

                            <div class="col-xs-12 col-sm-5 col-md-5 col-lg-5 col-ttls-disp"><?php echo __('Work Started On', 'ProjectTheme'); ?></div>
                            <div class="col-xs-12 col-sm-7 col-md-7 col-lg-7"><?php echo date_i18n('d-M-Y', $closed_date) ?></div>
                          </div>



                          <div class="row row-special-dispute-1">

                            <div class="col-xs-12 col-sm-5 col-md-5 col-lg-5 col-ttls-disp"><?php echo __('Expected Deliver Was', 'ProjectTheme'); ?></div>
                            <div class="col-xs-12 col-sm-7 col-md-7 col-lg-7"><?php echo date_i18n('d-M-Y', $exp_del) ?></div>
                          </div>



                            <div class="row row-special-dispute-1">

                              <div class="col-xs-12 col-sm-5 col-md-5 col-lg-5 col-ttls-disp"><?php echo __('You are', 'ProjectTheme'); ?></div>
                              <div class="col-xs-12 col-sm-7 col-md-7 col-lg-7"><?php if(get_current_user_id() == $pst->post_author) echo __('the owner of the project','ProjectTheme');
                              else echo __('the winning bidder user','ProjectTheme'); ?></div>
                              </div>


                              <div class="row row-special-dispute-1">

                                <div class="col-xs-12 col-sm-5 col-md-5 col-lg-5 col-ttls-disp"><?php echo __('Winner Bid', 'ProjectTheme'); ?></div>
                                <div class="col-xs-12 col-sm-7 col-md-7 col-lg-7"><?php echo projectTheme_get_show_price($bid->bid) ?></div>
                             </div>



                             <div class="row row-special-dispute-1">

                               <div class="col-xs-12 col-sm-5 col-md-5 col-lg-5 col-ttls-disp"><?php echo __('Reason For Reporting', 'ProjectTheme'); ?></div>
                               <div class="col-xs-12 col-sm-7 col-md-7 col-lg-7">

                                    <select name="reason" required class="form-control">
                                      <option value=""><?php echo __('Select reason', 'ProjectTheme'); ?></option>
                                      <?php

                                      for($i = 1; $i<=10; $i++)
                                      {

                                          $x = get_option('pt_disp_reason_'.$i);

                                        if(!empty($x))
                                        {
                                            ?>
                                                  <option value="<?php echo $i ?>"><?php echo $x; ?></option>

                                            <?php

                                      }      }


                                       ?>

                                    </select>

                               </div>
                            </div>



                           <div class="row row-special-dispute-1">

                             <div class="col-xs-12 col-sm-5 col-md-5 col-lg-5 col-ttls-disp"><?php echo __('Description of your issue', 'ProjectTheme'); ?></div>
                             <div class="col-xs-12 col-sm-7 col-md-7 col-lg-7"><textarea required name="issue" rows="5" class="form-control" ></textarea></div>
                          </div>


                          <div class="row row-special-dispute-1">

                            <div class="col-xs-12 col-sm-5 col-md-5 col-lg-5"> </div>
                            <div class="col-xs-12 col-sm-7 col-md-7 col-lg-7"><input type="submit" name="subdisp" class="btn btn-primary" value="<?php _e('Raise dispute','ProjectTheme'); ?>" /></div>
                         </div>



                    </form>

                  <?php  } ?>




              </div>
              </div>




              </div>  </div></div></div></div>
<?php

$page = ob_get_contents();
	ob_end_clean();

	return $page;

}


 ?>
