<?php

include 'paypal.class.php';


	global $wp_query, $wpdb, $current_user;
	$pid = $wp_query->query_vars['pid'];
	$current_user = wp_get_current_user();
	$uid = $current_user->ID;
	$post = get_post($pid);

$action = $_GET['action'];
$business = trim(get_option('ProjectTheme_paypal_email'));
if(empty($business)) die('Error. Admin, please add your paypal email in backend!');

$p = new paypal_class;             // initiate an instance of the class
$p->paypal_url = 'https://www.paypal.com/cgi-bin/webscr';   // testing paypal url

//--------------

	$ProjectTheme_paypal_enable_sdbx = get_option('ProjectTheme_paypal_enable_sdbx');
	if($ProjectTheme_paypal_enable_sdbx == "yes")
	$p->paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';     // paypal url

//--------------

$this_script = home_url().'/?p_action=purchase_membership_paypal_response';

if(empty($action)) $action = 'process';



switch ($action) {



   case 'process':      // Process and order...

	   $title_post = __('Membership Fee','ProjectTheme');
     $mem_id = $_GET['id'];

     if($_GET['tp'] == "freelancer") $slug = "freelancer"; else  $slug = "project_owner";

     $cost = get_option('pt_'.$slug.'_membership_cost_' . $mem_id);

		 /*

		 paypal subscription - use updated version of the theme

		 $link = PT_paypal_create_subscription($cost);

		 wp_redirect($link);
		 exit; */

//--- ------------------------------------------

      //$p->add_field('business', 'sitemile@sitemile.com');
      $p->add_field('business', $business);

	  $p->add_field('currency_code', get_option('ProjectTheme_currency'));
	  $p->add_field('return', $this_script.'&action=success');
      $p->add_field('cancel_return', $this_script.'&action=cancel');
      $p->add_field('notify_url', $this_script.'&action=ipn');
      $p->add_field('item_name', $title_post);
	  $p->add_field('custom', $mem_id.'|'.$uid.'|'.current_time('timestamp',0)."|" . $slug);
      $p->add_field('amount', ProjectTheme_formats_special($cost,2));
	  $p->add_field('bn', 'SiteMile_SP');

      $p->submit_paypal_post(); // submit the fields to paypal

      break;

   case 'success':      // Order was successful...
	case 'ipn':



	if(isset($_POST['custom']))
	{

		$cust 					= $_POST['custom'];
		$cust 					= explode("|",$cust);

		$pid					= $cust[0];
		$uid					= $cust[1];
		$datemade 				= $cust[2];

		do_action('projecttheme_on_membership_buy', $uid, $_POST['mc_gross']);

		//--------------------------------------------

		update_post_meta($pid, "paid", 				"1");
		update_post_meta($pid, "closed", 			"0");
		ProjectTheme_mark_images_cost_extra($pid);
		//--------------------------------------------

		update_post_meta($pid, 'base_fee_paid', '1');

		$featured = get_post_meta($pid,'featured',true);
		if($featured == "1") update_post_meta($pid, 'featured_paid', '1');

		$private_bids = get_post_meta($pid,'private_bids',true);
		if($private_bids == "1" or $private_bids == "yes" ) update_post_meta($pid, 'private_bids_paid', '1');

		$hide_project = get_post_meta($pid,'hide_project',true);
		if($hide_project == "1" or $hide_project == "yes" ) update_post_meta($pid, 'hide_project_paid', '1');

		$ProjectTheme_get_images_cost_extra = ProjectTheme_get_images_cost_extra($pid);

		$image_fee_paid = get_post_meta($pid, 'image_fee_paid', true);
		update_post_meta($pid, 'image_fee_paid', ($image_fee_paid + $ProjectTheme_get_images_cost_extra));

		//--------------------------------------------

		do_action('ProjectTheme_paypal_listing_response', $pid);

		$projectTheme_admin_approves_each_project = get_option('projectTheme_admin_approves_each_project');
		$paid_listing_date = get_post_meta($pid,'paid_listing_date_paypal' . $datemade,true);

		if(empty($paid_listing_date))
		{

			if($projectTheme_admin_approves_each_project != "yes")
			{
				wp_publish_post( $pid );
				$xx = current_time('timestamp',0);
												$post_pr_new_date = date('Y-m-d H:i:s',$xx);
												$gmt = get_gmt_from_date($xx);

												$post_pr_info = array(  "ID" 	=> $pid,
												  "post_date" 				=> $post_pr_new_date,
												  "post_date_gmt" 			=> $gmt,
												  "post_status" 			=> "publish"	);

				wp_update_post($post_pr_info);

				ProjectTheme_send_email_posted_project_approved($pid);
				ProjectTheme_send_email_posted_project_approved_admin($pid);

			}
			else
			{

				ProjectTheme_send_email_posted_project_not_approved($pid);
				ProjectTheme_send_email_posted_project_not_approved_admin($pid);
				ProjectTheme_send_email_subscription($pid);

			}

			update_post_meta($pid, "paid_listing_date_paypal" .$datemade, current_time('timestamp',0));
		}
	}

	if(get_option('projectTheme_admin_approves_each_project') == 'yes')
	{
		if(ProjectTheme_using_permalinks())
		{
			wp_redirect(get_permalink(get_option('ProjectTheme_my_account_page_id')) . "?prj_not_approved=" . $pid);
		}
		else
		{
			wp_redirect(get_permalink(get_option('ProjectTheme_my_account_page_id')) . "&prj_not_approved=" . $pid);
		}
	}
	else	wp_redirect(get_permalink($pid));

   break;

   case 'cancel':       // Order was canceled...

	wp_redirect(home_url());

       break;




 }

?>
