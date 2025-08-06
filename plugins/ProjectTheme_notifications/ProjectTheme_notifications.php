<?php
/*
Plugin Name: ProjectTheme Notifications
Plugin URI: https://sitemile.com/
Description: Adds a notification center for the users, facebook like. For project theme
Author: SiteMile.com
Author URI: https://sitemile.com/
Version: 1.1
Text Domain: notif
*/


include 'notifications_page_account.php';
include 'notifications.class.php';

add_filter('wp_head','pt_noti_head');
add_filter('template_redirect','pt_noti_temp_redir');
add_filter('pt_notification_bell_account_side','pt_notification_bell_account_side');
register_activation_hook(  __FILE__, 'ProjectTheme_notif_myplugin_activate' );

add_shortcode( 'project_theme_my_account_notifications', 'project_theme_my_account_notifications_pg' );

function pt_notif_number_of_notifications($uid)
{

  global $wpdb;
  $s = "select id from ".$wpdb->prefix."project_notifications where uid='$uid' and rd='0' order by id desc limit 5";
  $r = $wpdb->get_results($s);
  return count($r);

}

add_filter('projettheme_on_placing_a_bid','projettheme_on_placing_a_bid_notification', 10, 3);
add_filter('projettheme_on_receiving_a_message','projettheme_on_receiving_a_message_notification', 10, 2);
add_filter('projettheme_on_choosing_winner','projettheme_on_choosing_winner_notification', 10, 4);
add_filter('projettheme_on_putting_money_into_escrow','projettheme_on_putting_money_into_escrow_notification', 10, 4);
add_filter('projettheme_on_releasing_escrow','projettheme_on_releasing_escrow_notification', 10, 5);


function projettheme_on_releasing_escrow_notification($fromid,  $toid, $id, $amount, $oid)
{
  $order = new project_orders($oid);
  $obj = $order->get_order();
  $pid = $obj->pid;

  $pst = get_post($pid);
  $user_that_postedObj = get_userdata($fromid);

  $noti = new project_notifications();
  $notification_type = 4; //payment type

  $description = sprintf(__('The escrow amount of %s, from %s, was released for the project %s','noti'), '<span class="text-success">'.projectTheme_get_show_price($amount).'</span>', $user_that_postedObj->user_login,
  '<b>'.$pst->post_title.'</b>' );

  $noti->insert_notification($toid, $id, $description, $notification_type);

}

//***************************************************************************************************
//
//        adding a notification when putting money into escrow for the user
//
//***************************************************************************************************

function projettheme_on_putting_money_into_escrow_notification($fromid,  $toid, $oid, $amount)
{
  $order = new project_orders($oid);
  $obj = $order->get_order();
  $pid = $obj->pid;


  $pst = get_post($pid);
  $user_that_postedObj = get_userdata($fromid);

  $noti = new project_notifications();
  $notification_type = 4; //payment type

  $description = sprintf(__('You have received %s into escrow, from %s, for the project %s','noti'), '<span class="text-success">'.projectTheme_get_show_price($amount).'</span>', $user_that_postedObj->user_login,
  '<b>'.$pst->post_title.'</b>' );

  $noti->insert_notification($toid, $oid, $description, $notification_type);

}

//***************************************************************************************************
//
//        function here
//
//***************************************************************************************************

function projettheme_on_choosing_winner_notification($buyer,  $freelancer, $pid, $order_net_amount)
{

  $pst = get_post($pid);
  $user_that_postedObj = get_userdata($user_that_send);

  $noti = new project_notifications();
  $notification_type = 5;

  $description = sprintf(__('Your bid of %s was chosen as a winner for the project %s. <a href="%s">Check here</a>','noti'), '<span class="text-success">'.projectTheme_get_show_price($order_net_amount).'</span>',
  '<b>'.$pst->post_title.'</b>',
  ProjectTheme_get_project_link_with_page(get_option('ProjectTheme_my_account_freelancer_area'), 'pending'));
  $noti->insert_notification($freelancer, $pid, $description, $notification_type);


}


//***************************************************************************************************
//
//        function here
//
//***************************************************************************************************


function projettheme_on_receiving_a_message_notification($tm, $user_that_send, $receiver)
{


  $user_that_postedObj = get_userdata($user_that_send);

  $noti = new project_notifications();
  $notification_type = 1;

  $description = sprintf(__('You have received a new private message from %s. <a href="%s">Check here</a>','noti'), $user_that_postedObj->user_login, get_permalink( get_option('ProjectTheme_my_account_private_messages_id') ));
  $noti->insert_notification($receiver, $tm, $description, $notification_type);



}


//***************************************************************************************************
//
//        function here
//
//***************************************************************************************************

function projettheme_on_placing_a_bid_notification($pid, $bid, $user_that_posted)
{

  $pst = get_post($pid);
  $uid = $pst->post_author;
  $user_that_postedObj = get_userdata($user_that_posted);

  $noti = new project_notifications();
  $notification_type = 2;

  $description = sprintf(__('You have received a new bid of %s for the project %s from %s','noti'),
  '<span class="text-success">'.projectTheme_get_show_price($bid).'</span>', '<b><a href="'.get_permalink($pid).'">'.$pst->post_title.'</a></b>', $user_that_postedObj->user_login);

  $noti->insert_notification($uid, $pid, $description, $notification_type);



}


function ProjectTheme_notif_myplugin_activate()
{
  if(function_exists('ProjectTheme_insert_pages'))
  {
    ProjectTheme_insert_pages_account('ProjectTheme_my_account_notifications_id', 			'Notifications', 				'[project_theme_my_account_notifications]', 			get_option('ProjectTheme_my_account_page_id') );
  }

  global $wpdb;

  //notification_type 1 message, 2 bid, 3 new workspace, 4 payment, 5 winner

  $ss = "CREATE TABLE `".$wpdb->prefix."project_notifications` (
																					`id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
																					`uid` BIGINT NOT NULL DEFAULT '0',
                                          `related_id` BIGINT NOT NULL DEFAULT '0',
                                          `notification_type` TINYINT NOT NULL DEFAULT '0',
																					`description` TEXT NOT NULL ,
																					`rd` TINYINT NOT NULL DEFAULT '0',
																					`datemade` INT NOT NULL DEFAULT '0',
																					`readdate` INT NOT NULL DEFAULT '0'
			) ENGINE = MYISAM ;
			";
	$wpdb->query($ss);

}
function pt_notification_bell_account_side()
{

  ?>
  <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle pl-md-3 position-relative" href="javascript:void(0)" id="bell" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-bell svg-icon"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg></span>
          <?php

              $pt_notif_number_of_notifications = pt_notif_number_of_notifications(get_current_user_id());
              if($pt_notif_number_of_notifications > 0)
              {

           ?>
          <span class="badge badge-primary notify-no rounded-circle"><?php echo $pt_notif_number_of_notifications ?></span>

        <?php } ?>
      </a>
      <div class="dropdown-menu dropdown-menu-left mailbox animated bounceInDown">
          <ul class="list-style-none">
              <li>
                  <div class="message-center notifications position-relative ps-container ps-theme-default" data-ps-id="b2623af1-a5d9-3fd5-8fa4-7f9c14984681">
                      <!-- Message -->

                      <?php

                      $uid = get_current_user_id();
                      global $wpdb;
                      $s = "select * from ".$wpdb->prefix."project_notifications where uid='$uid' order by id desc limit 5";
                      $r = $wpdb->get_results($s);
                      $dtf = get_option('date_format');

                      if(count($r) > 0)
                      {
                        foreach($r as $row)
                        {
                            ?>

                            <a href="<?php echo get_permalink(get_option('ProjectTheme_my_account_notifications_id')) ?>" class="message-item d-flex align-items-center border-bottom px-3 py-2">
                                <div class="btn btn-danger rounded-circle btn-circle"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-airplay text-white"><path d="M5 17H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-1"></path><polygon points="12 15 17 21 7 21 12 15"></polygon></svg></div>
                                <div class="w-75 d-inline-block v-middle pl-2">
                                    <span class="font-12 text-nowrap d-block text-muted"><?php echo strip_tags($row->description,'<span><b>') ?></span>
                                    <span class="font-12 text-nowrap d-block text-muted"><?php echo date_i18n($dtf, $row->datemade) ?></span>
                                </div>
                            </a>

                            <?php
                          }
                      }
                      else {
                        // code...
                      }

                       ?>



                  <div class="ps-scrollbar-x-rail" style="left: 0px; bottom: 0px;"><div class="ps-scrollbar-x" tabindex="0" style="left: 0px; width: 0px;"></div></div>
                  <div class="ps-scrollbar-y-rail" style="top: 0px; right: 3px;"><div class="ps-scrollbar-y" tabindex="0" style="top: 0px; height: 0px;"></div></div></div>
              </li>
              <li>
                  <a class="nav-link pt-3 text-center text-dark" href="<?php echo get_permalink(get_option('ProjectTheme_my_account_notifications_id')) ?>">
                      <strong><?php _e('Check all notifications','notif'); ?></strong>
                      <i class="fa fa-angle-right"></i>
                  </a>
              </li>
          </ul>
      </div>
  </li>


  <?php
}

function pt_noti_temp_redir()
{
    if(isset($_GET['check_if_new_messages']))
    {
      $array['amount_of_messages'] = $unread_messages = projectTheme_get_unread_number_messages(get_current_user_id());;
      echo json_encode($array);
      die();
    }

}


function pt_noti_head()
{

  if(is_user_logged_in())
  {
?>


<script>


function execute_notification_pt()
{
      var unreadstring = '<span class="nav-unread" id="unread_cirlce"></span>';
      var ahref_row = '<?php pt_notif_ef_messages() ?>';
      var notificationsDropdown = '<?php echo pt_there_are_no_new_notifications() ?>';

      //---------------------

      var SITE_URL2 = '<?php echo get_site_url() ?>';

      jQuery.ajax({
        url: SITE_URL2 + "/?check_if_new_messages=1" ,
        success: function(data) {
          //console.log(data)

          var obj       = JSON.parse(data);
          var messages  =  (obj.amount_of_messages);



          if(messages > 0)
          {
              if(jQuery("#unread_cirlce").length == 0)
              {

                      jQuery( "#nav-for-circle" ).append(unreadstring);
                      jQuery("#dropdown-for-noti").prepend(ahref_row);

                      jQuery( "#no_new_notifications" ).remove();

              }


          }
          else {

            // no messages

                    jQuery( "#unread_cirlce" ).remove();
                    jQuery( "#messages-link-a" ).remove();

                    if(jQuery("#no_new_notifications").length == 0)
                    {
                        jQuery("#dropdown-for-noti").prepend(notificationsDropdown);
                    }





          }


        },
        complete: function() {
          // schedule the next request *only* when the current one is complete:
          setTimeout(execute_notification_pt, 1000);
        }
      });


}

jQuery(document).ready(function()
{
  setTimeout(execute_notification_pt, 1000);
});

</script>

<?php
}
}


function pt_notifications_plugin_zm()
{
      $total = 0;
      $unread_messages = projectTheme_get_unread_number_messages(get_current_user_id());

      //-------


      $total = pt_notif_number_of_notifications(get_current_user_id());


  ?>

  <div class="dropdown d-none d-md-flex">
    <a class="nav-link icon" data-toggle="dropdown" aria-expanded="false" id="nav-for-circle">
      <i class="fas fa-bell"></i>
      <?php

          if($total > 0)
          {
              ?>
                    <span class="nav-unread" id="unread_cirlce"></span>

              <?php
          }


       ?>
      <!-- <span class="nav-unread"></span> -->
    </a>
    <div id="dropdown-for-noti" class="dropdown-menu dropdown-menu-right dropdown-menu-arrow" x-placement="bottom-end" style="position: absolute; transform: translate3d(39px, 32px, 0px); top: 0px; left: 0px; will-change: transform;">

      <?php if($total > 0) { ?>

          <?php pt_notif_ef_messages() ?>

    <?php } else {?>

        <?php pt_there_are_no_new_notifications() ?>

      <?php } ?>

      <div class="dropdown-divider"></div>
      <a href="<?php echo get_permalink(get_option('ProjectTheme_my_account_notifications_id')) ?>" class="dropdown-item text-center"><?php _e('View All Notifications','ProjectTheme') ?></a>
    </div>
  </div>


  <?php
}


//------------------------------------------------------

function pt_there_are_no_new_notifications() {
  ?>  <a href="#" class="dropdown-item d-flex" id="no_new_notifications"> <div> <?php _e('There are no new notifications yet.','ProjectTheme') ?> </div> </a> <?php
}
function pt_notif_ef_messages()
{



  $uid = get_current_user_id();
  global $wpdb;
  $s = "select * from ".$wpdb->prefix."project_notifications where uid='$uid' order by id desc limit 5";
  $r = $wpdb->get_results($s);
  $dtf = get_option('date_format');

  if(count($r) > 0)
  {
    ?>

    <a href="<?php echo get_permalink(get_option('ProjectTheme_my_account_notifications_id')) ?>" class="dropdown-item d-flex" id="no_new_notifications"> <div> <?php _e('You have new notifications. Check them out.','notif') ?> </div> </a>


    <?php
  }
  else {

      ?>

      <a href="<?php echo get_permalink(get_option('ProjectTheme_my_account_livechat_id')) ?>" class="dropdown-item d-flex" id="messages-link-a">
        <span class="avatar mr-3 align-self-center" style="background-image: url(demo/faces/male/41.jpg)"></span>
        <div><?php _e('You have new unread messages. Check them out.','ProjectTheme'); ?><div class="small text-muted">Few seconds ago</div></div>
      </a>

      <?php
  }
}


 ?>
