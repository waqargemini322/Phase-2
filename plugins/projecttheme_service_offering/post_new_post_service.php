<?php

global $projectOK, $MYerror, $class_errors;
$projectOK = 0;

global $wp_query;
$pid = $wp_query->query_vars['projectid'];
$MYerror = array();
//************************ STEP 1 SUBMIT *********************************

do_action('ProjectTheme_post_new_post_post',$pid);

if(isset($_POST['project_submit1'] ))
{
  $project_title 			= trim($_POST['project_title']);
  $project_description 	= nl2br($_POST['project_description']);
  $project_category 		= $_POST['project_cat_cat'];

  $price 					= trim($_POST['price']);
  $project_location_addr 	= trim($_POST['project_location_addr']);


  update_post_meta($pid, 'finalised_posted', '0');

  //--------------------------------

  $projectOK = 1;

  if(empty($project_title))
  {
    $projectOK 							= 0;
    $MYerror['project_title'] 					= __('You cannot leave the project title blank!','ProjectTheme');
    $class_errors['project_title']		= 'error_class_post_new';
  }


  if(empty($project_description))
  {
    $projectOK = 0;
    $MYerror['project_description'] 	= __('You cannot leave the project description blank!','ProjectTheme');
    $class_errors['project_description']		= 'error_class_post_new';
  }


  $my_post = array();

  $my_post['post_title'] 		= $project_title;
  $my_post['post_status'] 	= 'publish';
  $my_post['ID'] 				= $pid;
  $my_post['post_content'] 	= $project_description;
  $ending = current_time('timestamp') + 3600*24*30;


  wp_update_post( $my_post );

  update_post_meta($pid, "price", 		$_POST['price']);
  update_post_meta($pid, "closed", 		"0");
  update_post_meta($pid, "closed_date", 	"0");
  update_post_meta($pid, "ending", 		$ending); // ending date for the project


  if($projectOK == 1) //if everything ok, go to next step
  {
    $stp = 2;

    wp_publish_post( $pid );

    wp_redirect(ProjectTheme_post_new_with_pid_stuff_thg_service($pid, $stp)); //projectTheme_post_new_link().'/step/2/?when_posting=1&post_new_project_id='.$pid);
    exit;
  }

}


 ?>
