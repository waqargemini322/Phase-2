<?php

function project_theme_account_my_services()
{
    ob_start();


    global $current_user, $wp_query;
    $current_user=wp_get_current_user();

    $uid = $current_user->ID;

    get_template_part ( 'lib/my_account/aside-menu'  );


    ?>

    	<div class="page-wrapper" style="display:block">
    		<div class="container-fluid"  >


    		<?php



    		do_action('pt_for_demo_work_3_0');


    ?>

    <div class="container">

    <div class="row">
    <div class="col-sm-12 col-lg-12">
    <div class="page-header">
    				<h1 class="page-title">
    					<?php echo sprintf(__('Services','ProjectTheme')  ) ?>
    				</h1>
    			</div></div></div>




    <div class="row">



	<div class="account-main-area col-xs-12 col-sm-12 col-md-12 col-lg-12">

      <div class="w-100 mb-3"><a href="<?php echo get_permalink(get_option('ProjectTheme_post_service')) ?>" class="btn btn-success btn-sm">Post New Service</a></div>

                     <?php


    				global $wp_query;
    				$query_vars = $wp_query->query_vars;
    				$post_per_page = 5;


    				$closed = array(
    						'key' => 'closed',
    						'value' => "0",
    						'compare' => '='
    					);



    				$args = array('post_type' => 'service', 'author' => $uid, 'order' => 'DESC', 'orderby' => 'date', 'posts_per_page' => $post_per_page,
    				'paged' => 1, 'meta_query' => array($paid, $closed), 'post_status' =>array('draft','publish') );

    				query_posts($args);



    				if(have_posts()) :
    					?>



    					<?php

    				while ( have_posts() ) : the_post();
    					projectTheme_get_service_acc();
    				endwhile;

    				?>

    				<?php

    				 else:

    				echo '<div class="card">';
    				echo '<div class="box_content padd20"> ';
    				_e("You have no services yet.",'ProjectTheme');
    				echo ' </div>';
    				echo ' </div>';

    				endif;

    				wp_reset_query();


    				?>

  </div>  </div> </div> </div> </div>








    <?php

    $data = ob_get_contents();
    ob_end_clean();
    return  $data;


}



 ?>
