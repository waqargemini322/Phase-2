<?php

function project_theme_post_service_fn()
{
    ob_start();


    global $wp_query, $projectOK, $current_user, $MYerror;
    $current_user = wp_get_current_user();

    $new_Project_step = $wp_query->query_vars['post_new_step'];
    if(empty($new_Project_step)) $new_Project_step = 1;

    $pid = $wp_query->query_vars['projectid']; if($pid == 0) $pid = 1;
    $uid = $current_user->ID;



    $cat 		= wp_get_object_terms($pid, 'service_cat', array('order' => 'ASC', 'orderby' => 'term_id' ));



  	if(!empty($pid))
  	$post 		= get_post($pid);


  	if(is_array($MYerror) and count($MYerror) > 0)
  	if($projectOK == 0)
  	{
  		echo '<div class="alert alert-danger">';

  			echo __('Your form has errors. Please check below, correct the errors, then submit again.','ProjectTheme');

  		echo '</div>';

    }


    ?>

    <div id="content" class="sonita">

          <div class="card">
              <div class="box_content">


    <div class="sonita2">

      <?php if($new_Project_step == 1) { ?>



    <form method="post" action="<?php echo ProjectTheme_post_new_with_pid_stuff_thg_service($pid, '1');?>">
    <ul class="post-new">

      <li class="<?php echo projecttheme_get_post_new_error_thing('project_title') ?>">
      <?php echo projecttheme_get_post_new_error_thing_display('project_title') ?>

        <h2><?php echo __('Your service title', 'ProjectTheme'); ?></h2>
        <p><input type="text" size="50" class="form-control full_wdth_me" name="project_title" placeholder="<?php _e('eg: I can create a website.','ProjectTheme') ?>" value="<?php echo (empty($_POST['project_title']) ?
    ($post->post_title == "Auto Draft" ? "" : $post->post_title) : $_POST['project_title']); ?>" /></p>
      </li>



      <?php


    $pst = $post->post_content;
    $pst = str_replace("<br />","",$pst);

  ?>
      <li class="<?php echo projecttheme_get_post_new_error_thing('project_description') ?>">
      <?php echo projecttheme_get_post_new_error_thing_display('project_description') ?>

        <h2><?php echo __('Description', 'ProjectTheme'); ?></h2>
      <p><textarea rows="6" cols="60" class="full_wdth_me form-control description_edit" placeholder="<?php _e('Describe here your service scope.','ProjectTheme') ?>"  name="project_description"><?php echo trim($pst); ?></textarea></p>
      </li>



      <li><h2><?php echo __('Service Budget', 'ProjectTheme'); ?></h2>
      <p class="strom_100"><?php $price = get_post_meta($pid,'price',true);  ?>
              	<input type="number" required size="50" class="form-control full_wdth_me" name="price" placeholder="<?php echo ProjectTheme_get_currency() ?>" value="<?php echo $price; ?>" />
      </p></li>



      <li>
       <h3><?php _e('Attach Images','ProjectTheme'); ?></h3>
     </li>

      <li>
     <div class="cross_cross">


       	<script type="text/javascript" src="<?php echo get_template_directory_uri(); ?>/js/dropzone.js"></script>
       	<link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/css/dropzone.css" type="text/css" />




 <script>
Dropzone.autoDiscover = false;

jQuery(function() {

Dropzone.autoDiscover = false;
var myDropzoneOptions = {
maxFilesize: 15,
 addRemoveLinks: true,
acceptedFiles:'image/*',
 clickable: true,
url: "<?php echo esc_url( home_url() )  ?>/?my_upload_of_project_files2=1",
};

var myDropzone = new Dropzone('div#myDropzoneElement2', myDropzoneOptions);

myDropzone.on("sending", function(file, xhr, formData) {
formData.append("author", "<?php echo $cid; ?>"); // Will send the filesize along with the file as POST data.
formData.append("ID", "<?php echo $pid; ?>"); // Will send the filesize along with the file as POST data.
});


 <?php

 $args = array(
'order'          => 'ASC',
'orderby'        => 'menu_order',
'post_type'      => 'attachment',
'post_parent'    => $pid,
'post_status'    => null,
'post_mime_type' => 'image',
'numberposts'    => -1,
);
$attachments = get_posts($args);

if($pid > 0)
if ($attachments)
{
   foreach ($attachments as $attachment)
 {
   $url = $attachment->guid;
   $imggg = $attachment->post_mime_type;
   $url = wp_get_attachment_url($attachment->ID);

     ?>
         var mockFile = { name: "<?php echo $attachment->post_title ?>", size: 12345, serverId: '<?php echo $attachment->ID ?>' };
         myDropzone.options.addedfile.call(myDropzone, mockFile);
         myDropzone.options.thumbnail.call(myDropzone, mockFile, "<?php echo projectTheme_generate_thumb($attachment->ID, 100, 100) ?>");

     <?php
 }
}

?>

myDropzone.on("success", function(file, response) {
 /* Maybe display some more file information on your page */
file.serverId = response;
file.thumbnail = "<?php echo get_template_directory_uri() ?>/images/file_icon.png";


});


myDropzone.on("removedfile", function(file, response) {
 /* Maybe display some more file information on your page */
 delete_this2(file.serverId);

});

});

</script>



<?php _e('Click the grey area below to add project images. Other files are not accepted. Use the form below.','ProjectTheme') ?>
 <div class="dropzone dropzone-previews" id="myDropzoneElement2" ></div>


</div>
     </li>



     <li>
     <h2>&nbsp;</h2>
     <p>
     <input type="submit" name="project_submit1" value="<?php _e("Submit Service", 'ProjectTheme'); ?>" class="btn btn-primary" /></p>
     </li>



    </ul>
  </form> <?php } ?>

<?php  if($new_Project_step == 2) { ?>



  <?php echo sprintf(__('Your service has been posted. <a href="%s">Click here</a> to go to your account.','ProjectTheme'), get_permalink( get_option('ProjectTheme_my_account_page_id') )); ?>


<?php } ?>

</div>





</div></div></div>


    <?php

    $data = ob_get_contents();
    ob_end_clean();
    return  $data;


}


?>
