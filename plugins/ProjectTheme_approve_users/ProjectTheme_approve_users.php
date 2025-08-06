<?php
/*
Plugin Name: ProjectTheme Freelancer Approve
Plugin URI: https://sitemile.com
Description: Lets you manually approve users/freelancers in your website before they are able to use. For project theme
Version: 1.1
Author: sitemile.com
Author URI: https://sitemile.com
Text Domain: pt_approve_users
*/



add_filter('pt_at_account_dash_top','pt_at_account_dash_top_approve_plug');
add_filter('projecttheme_personal_info_page_above_main_form','projecttheme_personal_info_page_above_main_form_fnc1');


function projecttheme_personal_info_page_above_main_form_fnc1()
{

  $uid = get_current_user_id();


  if(!empty($_POST['save-info21']))
  {


    					require_once(ABSPATH . "wp-admin" . '/includes/file.php');
    					require_once(ABSPATH . "wp-admin" . '/includes/image.php');


    $uid = get_current_user_id();

    if(!empty($_FILES['upload_id']["name"]))
    {

      $upload_overrides 	= array( 'test_form' => false );
              $uploaded_file 		= wp_handle_upload($_FILES['upload_id'], $upload_overrides);

      $file_name_and_location = $uploaded_file['file'];
              $file_title_for_media_library = $_FILES['upload_id'  ]['name'];

      $file_name_and_location = $uploaded_file['file'];
      $file_title_for_media_library = $_FILES['upload_id']['name'];

      $arr_file_type 		= wp_check_filetype(basename($_FILES['upload_id']['name']));
      $uploaded_file_type = $arr_file_type['type'];
      $urls  = $uploaded_file['url'];



      if($uploaded_file_type == "image/png" or $uploaded_file_type == "image/jpg" or $uploaded_file_type == "image/jpeg" or $uploaded_file_type == "image/gif" )
      {

        $attachment = array(
                'post_mime_type' => $uploaded_file_type,
                'post_title' => 'User ID',
                'post_content' => '',
                'post_status' => 'inherit',
                'post_parent' =>  0,
                'post_author' => $uid,
              );



        $attach_id = wp_insert_attachment( $attachment, $file_name_and_location, 0 );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_name_and_location );
        wp_update_attachment_metadata($attach_id,  $attach_data);



        $_wp_attached_file = get_post_meta($attach_id,'_wp_attached_file',true);

        if(!empty($_wp_attached_file))
        update_user_meta($uid, 'upload_id',  ($attach_id) );

      }

    }

    if(!empty($_FILES['business_proof']["name"]))
    {

      $upload_overrides 	= array( 'test_form' => false );
              $uploaded_file 		= wp_handle_upload($_FILES['business_proof'], $upload_overrides);

      $file_name_and_location = $uploaded_file['file'];
              $file_title_for_media_library = $_FILES['business_proof'  ]['name'];

      $file_name_and_location = $uploaded_file['file'];
      $file_title_for_media_library = $_FILES['business_proof']['name'];

      $arr_file_type 		= wp_check_filetype(basename($_FILES['business_proof']['name']));
      $uploaded_file_type = $arr_file_type['type'];
      $urls  = $uploaded_file['url'];



      if($uploaded_file_type == "image/png" or $uploaded_file_type == "image/jpg" or $uploaded_file_type == "image/jpeg" or $uploaded_file_type == "image/gif" )
      {

        $attachment = array(
                'post_mime_type' => $uploaded_file_type,
                'post_title' => 'Business Proof',
                'post_content' => '',
                'post_status' => 'inherit',
                'post_parent' =>  0,
                'post_author' => $uid,
              );



        $attach_id = wp_insert_attachment( $attachment, $file_name_and_location, 0 );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_name_and_location );
        wp_update_attachment_metadata($attach_id,  $attach_data);



        $_wp_attached_file = get_post_meta($attach_id,'_wp_attached_file',true);

        if(!empty($_wp_attached_file))
        {
            update_user_meta($uid, 'business_proof',  ($attach_id) );
        }

      }

    }


  }



  $business_proof = get_user_meta($uid,'business_proof', true);
  if(!empty($business_proof))
  {
      ?>

            <div class="alert alert-warning"><?php _e('Your documents have been uploaded. The admin will review the documents and approve your account.','ProjectTheme') ?></div>

      <?php
  }

if (!empty($_POST['users_profile_video_intro'])) {
    $users_profile_video_intro = sanitize_text_field($_POST['users_profile_video_intro']);
    update_user_meta($uid, 'users_profile_video_intro', $users_profile_video_intro);
}

    ?>

    <form method="post" enctype="multipart/form-data">
  <div class="card">
    <div class="card-body">
      <div class="row">

        <!-- Upload ID Field -->
        <div class="col-md-12">
          <div class="form-group">
            <label class="form-label"><?php echo __('Upload ID', 'ProjectTheme'); ?></label>
            <div class="custom-file">
              <input type="file" name="upload_id" class="custom-file-input" />
              <label class="custom-file-label">
                <?php 
                  $upload_id = get_user_meta($uid, 'upload_id', true);
                  if (!empty($upload_id)) {
                    echo __('File already uploaded. You can upload a new file to replace it.', 'ProjectTheme');
                  } else {
                    echo __('Choose file', 'ProjectTheme');
                  }
                ?>
              </label>
            </div>
            <?php if (!empty($upload_id)): ?>
              <div class="mt-2">
                <a href="<?php echo wp_get_attachment_url($upload_id); ?>" target="_blank">
                  <?php _e('View Uploaded File', 'ProjectTheme'); ?>
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Upload Business Proof Field -->
        <div class="col-md-12">
          <div class="form-group">
            <label class="form-label"><?php echo __('Upload Proof of Business', 'ProjectTheme'); ?></label>
            <div class="custom-file">
              <input type="file" name="business_proof" class="custom-file-input" />
              <label class="custom-file-label">
                <?php 
                  $business_proof = get_user_meta($uid, 'business_proof', true);
                  if (!empty($business_proof)) {
                    echo __('File already uploaded. You can upload a new file to replace it.', 'ProjectTheme');
                  } else {
                    echo __('Choose file', 'ProjectTheme');
                  }
                ?>
              </label>
            </div>
            <?php if (!empty($business_proof)): ?>
              <div class="mt-2">
                <a href="<?php echo wp_get_attachment_url($business_proof); ?>" target="_blank">
                  <?php _e('View Uploaded File', 'ProjectTheme'); ?>
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Video Introduction Field -->
        <div class="col-md-12">
          <div class="form-group">
            <label class="form-label"><?php echo __('Video Introduction', 'ProjectTheme'); ?></label>
            <input type="text" 
                   size="35" 
                   placeholder="Ex: https://www.youtube.com/watch?v=dQw4w9WgXcQ" 
                   name="users_profile_video_intro" 
                   value="<?php echo esc_url(get_user_meta($uid, 'users_profile_video_intro', true)); ?>" 
                   class="form-control" />
          </div>
        </div>

      </div>
    </div>

    <div class="card-footer text-right">
      <input type="submit" name="save-info21" class="btn btn-success" value="<?php _e('Upload for approval', 'ProjectTheme'); ?>" />
    </div>
  </div>
</form>


    <?php

}


function pt_at_account_dash_top_approve_plug()
{
    $uid = get_current_user_id();
    $approve = get_user_meta($uid, 'approve',  true )  ;


    if($approve == "no")
    {
        ?>

        <div class="mb-4 alert alert-danger">

          <?php

          $link = get_permalink(get_option('ProjectTheme_my_account_personal_info_id'));
          echo sprintf(__('You are not approved yet. The admin will review your information and approve your application. If you havent uploaded your documents <a href="%s">click here</a>','ProjectTheme'), $link);


          ?>
        </div>

        <?php
    }
    else if($approve == "yes")
    {
        ?>
              <div class="mb-4 alert alert-success"><?php
                    echo sprintf(__('Your account is approved. You can place bids now.','ProjectTheme'));
              ?></div>

        <?php
    }

}

function pt_new_contact_methods( $contactmethods ) {
    $contactmethods['approved'] = 'Approved';
    return $contactmethods;
}
add_filter( 'user_contactmethods', 'pt_new_contact_methods', 10, 1 );


function pt_new_modify_user_table( $column ) {
    $column['approved'] = 'Approved';
    return $column;
}
add_filter( 'manage_users_columns', 'pt_new_modify_user_table' );

function pt_new_modify_user_table_row( $val, $column_name, $user_id ) {
    switch ($column_name) {
        case 'approved' :

            $app = get_user_meta($user_id,'approve',true);

            if($app == "no") return 'Not Approved';
            else
            return 'Approved';
        default:
    }
    return $val;
}
add_filter( 'manage_users_custom_column', 'pt_new_modify_user_table_row', 10, 3 );

//--------------

function save_pt_ex_extra_profile_fields( $user_id ) {

    if ( !current_user_can( 'edit_user', $user_id ) )
        return false;

    /* Edit the following lines according to your set fields */
    update_usermeta( $user_id, 'approve', $_POST['approve'] );
}

add_action( 'personal_options_update', 'save_pt_ex_extra_profile_fields' );
add_action( 'edit_user_profile_update', 'save_pt_ex_extra_profile_fields' );

// Hook to set default 'approve' status to 'no' for new users
function set_default_user_approval_status( $user_id ) {
  // Set the 'approve' meta key to 'no' by default
  update_user_meta( $user_id, 'approve', 'no' );
}
add_action( 'user_register', 'set_default_user_approval_status' );


function pt_ex_extra_profile_fields( $user ) { ?>

    <h3><?php _e('Approve users'); ?></h3>
    <table class="form-table">

        <tr>
            <th><label for="hotmail">Approved ?</label></th>
            <td>
            <?php $approve = get_the_author_meta( 'approve', $user->ID )  ;

                  if($approve == "no") $apps = "no"; else  $apps = "yes";
            ?>


            <select name="approve">

              <option value="no" <?php echo $apps == "no" ? "selected='selected'" : "" ?>>NO</option>
                <option value="yes"  <?php echo $apps == "yes" ? "selected='selected'" : "" ?>>YES</option>

            </td>
        </tr>

        <tr><td colspan="2">

        <?php
        $business_proof = get_user_meta( $user->ID, 'business_proof', true );
        $upload_id = get_user_meta( $user->ID, 'upload_id', true );
        $video_intro = esc_url( get_user_meta( $user->ID, 'users_profile_video_intro', true ) ); // Sanitize video URL

        if ( !empty( $business_proof ) || !empty( $upload_id ) || !empty( $video_intro ) ) {
        ?>

          Your user has uploaded:
          <?php if ( !empty( $upload_id ) ): ?>
            <a href="<?php echo wp_get_attachment_url( $upload_id ); ?>" target="_blank">ID</a>,
          <?php endif; ?>
          <?php if ( !empty( $video_intro ) ): ?>
            <a href="<?php echo $video_intro; ?>" target="_blank">Video</a> and 
          <?php endif; ?>
          <?php if ( !empty( $business_proof ) ): ?>
            <a href="<?php echo wp_get_attachment_url( $business_proof ); ?>" target="_blank">proof of business</a>.
          <?php endif; ?>

        <?php } else { ?>

          Your user has not uploaded any documents yet.

        <?php } ?>

      </td></tr>

    </table>
<?php

}

// Then we hook the function to "show_user_profile" and "edit_user_profile"
add_action( 'show_user_profile', 'pt_ex_extra_profile_fields', 10 );
add_action( 'edit_user_profile', 'pt_ex_extra_profile_fields', 10 );

 ?>
