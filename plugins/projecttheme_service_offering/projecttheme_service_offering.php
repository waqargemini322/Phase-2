<?php
/*
Plugin Name: ProjectTheme Service Offering
Plugin URI: https://sitemile.com/
Description: Adds service offering for the project theme.
Author: SiteMile.com
Author URI: https://sitemile.com/
Version: 1.6
Text Domain: ss_project
*/


include 'post-service.php';
include 'my-account/my-services.php';
include 'widgets/service-widget.php';



add_action('init', 				                            'pt_service_offering' );
add_action('template_redirect', 				              'pt_service_offering_template_redirect' );
add_shortcode('project_theme_my_account_my_services', 'project_theme_account_my_services');
add_filter('ProjectTheme_my_account_main_menu',       'ProjectTheme_my_account_main_menu_serv');



//add_filter('template_include', 'pt_service_template');

function pt_service_template( $template ) {
  if ( is_post_type_archive('my_plugin_lesson') ) {
    $theme_files = array('archive-my_plugin_lesson.php', 'myplugin/archive-service.php');
    $exists_in_theme = locate_template($theme_files, false);
    if ( $exists_in_theme != '' ) {
      return $exists_in_theme;
    } else {
      return plugin_dir_path(__FILE__) . 'archive-service.php';
    }
  }
  return $template;
}





function ProjectTheme_my_account_main_menu_serv()
{
    ?>

    <li class="sidebar-item"> <a class="sidebar-link" href="<?php echo get_permalink(get_option('ProjectTheme_my_services_page_id')); ?>" aria-expanded="false">
    	<span class="hide-menu"><i class="fas fa-user-friends"></i> <?php printf(__("Services",'ProjectTheme') ); ?></span></a>
    </li>


        <!-- <li class="nav-item dropdown">



          <a class="nav-link dropdown-toggle" id="navbarDropdownx" role="button" data-toggle="dropdown" aria-haspopup="true"
          aria-expanded="false" href="#"><?php _e("Services",'ProjectTheme');?></a>

          <div class="dropdown-menu" aria-labelledby="navbarDropdownx">


          							 <a class="dropdown-item" href="<?php echo get_permalink(get_option('ProjectTheme_my_services_page_id')); ?>"><?php _e('My Services','ProjectTheme') ?></a>
                         <a class="dropdown-item" href="<?php echo get_permalink(get_option('ProjectTheme_post_service')) ?>"><?php _e('Post New Service','ProjectTheme') ?></a>
                         <a class="dropdown-item" href="<?php echo get_post_type_archive_link('service') ?>"><?php _e('All Posted Services','ProjectTheme') ?></a>


          		        </div>

        </li> -->


    <?php

}


//****************************************************
//
//  function
//
//****************************************************

function pt_service_offering_template_redirect()
{
    $ProjectTheme_post_new_page_id = get_option('ProjectTheme_post_service');
    global $post;

    if($post->ID == $ProjectTheme_post_new_page_id)
    {
            if(!is_user_logged_in())	{ wp_redirect(ProjectTheme_login_url(). '?redirect_to=' . urlencode(get_permalink('ProjectTheme_post_service'))); exit; }
            global $current_user;
            $current_user = wp_get_current_user();


            if(!isset($_GET['projectid'])) $set_ad = 1; else $set_ad = 0;


            if(!empty($_GET['projectid']))
            {
              $my_main_post = get_post($_GET['projectid']);
              $cu = wp_get_current_user();

              if($my_main_post->post_author != $current_user->ID and $cu->user_login != 'sitemileadmin')
              {
                wp_redirect(home_url()); exit;
              }

            }

            if($set_ad == 1)
            {
              $pid 		= ProjectTheme_get_auto_draft_service($current_user->ID);
              wp_redirect(ProjectTheme_post_new_with_pid_stuff_thg_service($pid));
            }

            include ( 'post_new_post_service.php');


    }
}

//****************************************************
//
//  function
//
//****************************************************

function ProjectTheme_post_new_with_pid_stuff_thg_service($pid, $step = 1, $fin = 'no')
{
	$using_perm = ProjectTheme_using_permalinks();
	if($using_perm)	return get_permalink(get_option('ProjectTheme_post_service')). "?post_new_step=".$step."&".($fin != "no" ? 'finalize=1&' : '' )."projectid=" . $pid;
	else return home_url(). "/?page_id=". get_option('ProjectTheme_post_service'). "&".($fin != "no" ? 'finalize=1&' : '' )."post_new_step=".$step."&projectid=" . $pid;
}


//****************************************************
//
//  function
//
//****************************************************

function ProjectTheme_get_auto_draft_service($uid)
	{
		global $wpdb;
		$querystr = "
			SELECT distinct wposts.*
			FROM $wpdb->posts wposts where
			wposts.post_author = '$uid' AND wposts.post_status = 'auto-draft'
			AND wposts.post_type = 'service'
			ORDER BY wposts.ID DESC LIMIT 1 ";

		$row = $wpdb->get_results($querystr, OBJECT);
		if(count($row) > 0)
		{
			$row = $row[0];
			return $row->ID;
		}

		return ProjectTheme_create_auto_draft_service($uid);
}



//****************************************************
//
//  function
//
//****************************************************


function ProjectTheme_create_auto_draft_service($uid)
{
		$my_post = array();
		$my_post['post_title'] 		= 'Auto Draft';
		$my_post['post_type'] 		= 'service';
		$my_post['post_status'] 	= 'auto-draft';
		$my_post['post_author'] 	= $uid;
		$pid = wp_insert_post( $my_post, true );

		update_post_meta($pid, 'featured_paid', 		'0');
		update_post_meta($pid, 'private_bids_paid', 	'0');
		update_post_meta($pid, 'hide_project_paid', 	'0');
		update_post_meta($pid, 'base_fee_paid', 		'0');

		do_action('ProjectTheme_when_creating_auto_draft');

		return $pid;

}


//****************************************************
//
//  function
//
//****************************************************


function pt_service_offering()
{
      $rgtx1 = 'register'.'_'.'post_type';
      $icn = get_template_directory_uri()."/images/proj_icon.png";


      $rgtx1( 'service',
        array(
          'labels' => array(
            'name' 			=> __( 'Services',			'ProjectTheme' ),
            'singular_name' => __( 'Service',			'ProjectTheme' ),
        'add_new' 		=> __('Add New Service',	'ProjectTheme'),
        'new_item' 		=> __('New Service',		'ProjectTheme'),
        'edit_item'		=> __('Edit Service',		'ProjectTheme'),
        'add_new_item' 	=> __('Add New Service',	'ProjectTheme'),
        'search_items' 	=> __('Search Services',	'ProjectTheme'),


          ),
          'public' => true,
         'has_archive' => 'service-list',
        'menu_position' => 5,
        'register_meta_box_cb' => 'projectTheme_set_metaboxes_service',
        'has_archive' => "service-list",
          'rewrite' => true,
        'supports' => array('title','editor','author','thumbnail','excerpt','comments'),
        '_builtin' => false,
        'menu_icon' => $icn,
        'publicly_queryable' => true,
        'hierarchical' => false

        )
      );


    $regtx = 'register'.'_'.'taxonomy';
    $regtx( 'service_cat', 'service', array( 'rewrite' => true ,'hierarchical' => true,   'label' => __('Service Categories','ProjectTheme') ) );

}

add_shortcode('project_theme_post_service','project_theme_post_service_fn');


function projectTheme_set_metaboxes_service()
{
			add_meta_box( 'service-metaboxes', 		'Service Images',		'projectTheme_theme_service_images', 		'service', 'advanced',	'high' );
}

//****************************************************
//
//  function
//
//****************************************************

function pt_activation_service_offering() {

    ProjectTheme_insert_pages('ProjectTheme_post_service', 			'Post Service', 		'[project_theme_post_service]' );
		ProjectTheme_insert_pages_account('ProjectTheme_my_services_page_id', 		"My Services", 		'[project_theme_my_account_my_services]', 	get_option('ProjectTheme_my_account_page_id') );


}
register_activation_hook( __FILE__, 'pt_activation_service_offering' );


function projectTheme_get_service_acc()
{
		$pid = get_the_ID();
	global $post, $current_user;
	$current_user = wp_get_current_user();
	$post = get_post($pid);
	$uid = $current_user->ID;

	$ending 			= get_post_meta(get_the_ID(), 'ending', true);
	$sec 				= $ending - current_time('timestamp',0);
	$location 			= get_post_meta(get_the_ID(), 'Location', true);
	$closed 			= get_post_meta(get_the_ID(), 'closed', true);
	$featured 			= get_post_meta(get_the_ID(), 'featured', true);
	$private_bids 		= get_post_meta(get_the_ID(), 'private_bids', true);
	$paid		 		= get_post_meta(get_the_ID(), 'paid', true);

	$budget = ProjectTheme_get_budget_name_string_fromID(get_post_meta($pid,'budgets',true));
	$proposals = sprintf(__('%s proposals','ProjectTheme'), projectTheme_number_of_bid($pid));
	$proposals = sprintf(__('%s proposals','ProjectTheme'), projectTheme_number_of_bid($pid));

	$posted = get_the_time("jS F Y");
	$auth = get_userdata($post->post_author);
	$hide_project_p = get_post_meta($post->ID, 'private_bids', true);


	$days_left = ($closed == "0" ?  ($ending - current_time('timestamp',0)) : __("Expired/Closed",'ProjectTheme'));
	//$tm_d = get_post_meta(get_the_ID(), 'expected_delivery', true);
	//$due_date = sprintf(__('Due Date: %s','ProjectTheme'), date_i18n('d-M-Y g:iA', $tm_d));

	//----------------------



			if($arr[0] == "winner") 	$pay_this_me = 1;
			if($arr[0] == "winner_not") $pay_this_me2 = 1;
			if($arr[0] == "unpaid") 	$unpaid = 1;


			$paid		 		= get_post_meta(get_the_ID(), 'paid', true);

if($days_left < 0) $days_left = __('Expired/Closed','ProjectTheme');

	?>

        <div class="card section-vbox padd20" id="post-<?php the_ID(); ?>"><div class="padd10 nopadd-top">
    		<h4><a href="<?php the_permalink() ?>"><?php the_title() ?></a> </h4>

                <?php

				if($post->post_status == "draft")
				echo '<div class="alert alert-warning">'.__('This service is not approved yet.','ProjectTheme').'</div>';


			?>


      <div class="d-lg-flex flex-row user-meta-data-row">

      						<div class="pl-2 pr-2    flex-shrink-1 "><i class="fa fa-calendar"></i> <?php echo $posted ?> </div>
      						<div class="pl-2 pr-2    flex-shrink-1 "><p class="<?php echo (is_numeric($days_left) and $days_left > 0) ? "expiration_project_p" : "" ?>"><?php echo $days_left ?></p></div>

      				</div>



            <div class="excerpt-thing">

                <div class="my-deliv_2">
					 	<?php if($pay_this_me == 1): ?>
                        <a href="<?php echo ProjectTheme_get_pay4project_page_url(get_the_ID()); ?>"
                        class="post_bid_btn"><?php echo __("Pay This", "ProjectTheme");?></a>
                        <?php endif; ?>

                   <?php if(1 ) { ?>

                  <?php if( $pay_this_me != 1): ?>
                  <a href="<?php the_permalink(); ?>" class="btn btn-light btn-sm"><i class="fas fa-book"></i> <?php echo __("Read More", "ProjectTheme");?></a>
                  <?php endif; ?>

                  <?php if( $unpaid == 1):

				  	$finalised_posted = get_post_meta(get_the_ID(),'finalised_posted',true);
					if($finalised_posted == "1") $finalised_posted = 3; else $finalised_posted = "1";

					$finalised_posted = apply_filters('ProjectTheme_publish_prj_posted', $finalised_posted);

				  ?>
                  <a href="<?php echo ProjectTheme_post_new_with_pid_stuff_thg(get_the_ID(), $finalised_posted); ?>" class="btn btn-light btn-sm"><?php echo __("Publish", "ProjectTheme");?></a>
                  <?php endif; ?>




				  <?php if($post->post_author == $uid) { ?>
                  <a href="<?php echo esc_url( home_url() ) ?>/?p_action=edit_project&pid=<?php the_ID(); ?>" class="btn btn-light btn-sm"><i class="far fa-edit"></i> <?php echo __("Edit Service", "ProjectTheme");?></a>
                  <?php }   ?>

                  <?php if($post->post_author == $uid) //$closed == 1)
				  { ?>

                   <?php if($closed == "1") //$closed == 1)
				  { ?>
                  <a href="<?php echo esc_url( home_url() ) ?>/?p_action=repost_project&pid=<?php the_ID(); ?>" class="btn btn-light btn-sm"><?php echo __("Repost Project", "ProjectTheme");?></a>

                  <?php } /*} else { */  ?>
                	<?php

					$winner = get_post_meta(get_the_ID(),'winner', true);

					if(empty($winner)):
					?>
                   <a href="<?php echo esc_url( home_url() ) ?>/?p_action=delete_project&pid=<?php the_ID(); ?>" class="btn btn-light btn-sm"><i class="far fa-trash-alt"></i> <?php echo __("Delete", "ProjectTheme");?></a>
                  <?php endif; ?>

                  <?php } ?>

                  <?php } ?>
                </div>
            </div> <!-- end excerpt-thing -->



            <div class="d-lg-flex flex-row user-meta-data-row">
            			<div class="pt-2 pl-0 pr-1 ">
            					<div class="avatar d-block" style="background-image: url(<?php echo ProjectTheme_get_avatar($post->post_author,25, 25) ?>)">
            					<span class="avatar-status bg-green"></span></div>
            				</div>

            			<div class="p-2 pt3-custom">
            				<div class=""><a class="avatar-posted-by-username" href="<?php echo ProjectTheme_get_user_profile_link($post->post_author); ?>"><?php echo $auth->user_login ?></a></div>
            		 	</div>

            			<div class="p-2 pt3-custom"><?php echo ProjectTheme_project_get_star_rating($post->post_author); ?></div>
            			<div class="p-2">
                  <a class="btn btn-outline-primary btn-sm" href="<?php echo ProjectTheme_get_user_feedback_link($post->post_author); ?>"><?php _e('View User Feedback','ProjectTheme'); ?></a></div>
            		</div>




        </div></div>

        <?php

}


?>
