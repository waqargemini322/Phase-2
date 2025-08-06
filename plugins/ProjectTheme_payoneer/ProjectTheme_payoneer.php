<?php
/*
Plugin Name: ProjectTheme Payoneer
Plugin URI: http://sitemile.com/
Description: Lets your users withdraw via payoneer.
Author: SiteMile.com
Author URI: http://sitemile.com/
Version: 1.1
Text Domain: ProjectTheme_withdraw
*/

add_filter('ProjectTheme_add_new_withdraw_methods','ProjectTheme_add_new_withdraw_methods_payo');



add_filter('projecttheme_at_top_of_withdraw_page','projecttheme_at_top_of_withdraw_page_payoneer');


function projecttheme_at_top_of_withdraw_page_payoneer()
{
  if(isset($_POST['withdraw_by_payoneer']))
  {
        $payoneer_email      = $_POST['payoneer_email'];
        $amount             = $_POST['amount'];

        global $wpdb;
        $uid  = get_current_user_id();

        $min = get_option('project_theme_min_withdraw');
        if(empty($min)) $min = 0;
        $bal = projectTheme_get_credits($uid);

        if($bal < $amount)
        {
              echo '<div class="alert alert-danger">'.__('Your balance is lower than the amount you requested.','ProjectTheme').'</div>';
        }
        elseif($amount < $min) {
              // code...
              echo '<div class="alert alert-danger">'.sprintf(__('The withdraw limit is %s, please request the same amount or higher.','ProjectTheme'), projectTheme_get_show_price($min)).'</div>';
        }
        else {

              if(!empty($_POST['tm']))
              {
                $tm = $_POST['tm']; //current_time('timestamp',0);
              } else $tm = current_time('timestamp');

              //------

              $s = "select * from ".$wpdb->prefix."project_withdraw where uid='$uid' and datemade='$tm' ";
              $r = $wpdb->get_results($s);

              if(count($r) == 0)
              {
                  $meth = 'Payoneer';
                  $s = "insert into ".$wpdb->prefix."project_withdraw (methods, payeremail, amount, datemade, uid, done)
                  values('$meth','$payoneer_email','$amount','$tm','$uid','0')";
                  $wpdb->query($s);


                  // added 3.1.7
                  ProjectTheme_send_email_on_withdrawal_requested_user($uid, $amount, $meth);
                  ProjectTheme_send_email_on_withdrawal_requested_admin($uid, $amount, $meth);

                  projectTheme_update_credits($uid, $bal - $amount);

                }

                echo '<div class="alert alert-success">'.__('Your withdrawal request has been submitted.','ProjectTheme').'</div>';
        }

  }
}


function ProjectTheme_add_new_withdraw_methods_payo()
{
  ?>

  <div class="card mb-4"><div class="card-body">
   <h5 class="cff123 mb-4"><?php _e('Widthdraw by Payoneer','ProjectTheme') ?></h5>


             <form method="post" enctype="application/x-www-form-urlencoded">
             <input type="hidden" name="meth" value="Payoneer" />
             <input type="hidden" name="tm" value="<?php echo current_time('timestamp',0) ?>" />


             <div class="form-group">
             <div class="input-group">
                 <span class="input-group-prepend">
                   <span class="input-group-text"><?php echo projectTheme_currency() ?></span>
                 </span>
                 <input type="number" step="0.01" name="amount" required="" class="form-control no-border-radius" placeholder="<?php _e('Amount to withdraw','payoneer') ?>">
               </div></div>


                 <div class="form-group">
               <div class="input-group">
                   <input type="email"  required class="form-control no-border-radius" name="payoneer_email" placeholder="<?php _e('Your Payoneer email address','payoneer') ?>">
                 </div>	</div>

                 </div>

           <div class="card-footer text-right"> <input type="submit" class="btn btn-success" name="withdraw_by_payoneer" value="<?php echo __("Withdraw","payoneer"); ?>" /> </div>

         </form>	 </div>




  <?php
}
?>
