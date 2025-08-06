<?php

function project_theme_my_account_notifications_pg()
{
    ob_start();

    global $current_user, $wpdb, $wp_query;
    $current_user = wp_get_current_user();
    $uid = $current_user->ID;



    get_template_part ( 'lib/my_account/aside-menu'  );
    do_action('pt_for_demo_work_3_0');

    ?>


    <div class="page-wrapper" style="display:block">
    	<div class="container-fluid"  >




    				<div class="container">



    						<h5 class="my-account-headline-1"><?php echo __('Notifications','pt_affiliates'); ?></h5>


                <div class="card">






    									<?php

    										$s = "select * from ".$wpdb->prefix."project_notifications where uid='$uid' order by id desc";
    										$r = $wpdb->get_results($s);

    										if(count($r) == 0) {   echo '<p class="p-3">';	_e('There are no notifications.','notif');   echo '</div>'; }
    										else {

    											?>




    											<?php


    												foreach($r as $row)
    												{
    															$pst = get_post($row->pid);

    												?>

    														<div class="row p-3" style="<?php echo $row->rd == 0 ? "background: #dfedff" : "" ?>">
                                  <div  class="text-center col col-xs-12 col-md-2 p-1"><div class="btn btn-danger rounded-circle btn-circle"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-airplay text-white"><path d="M5 17H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-1"></path><polygon points="12 15 17 21 7 21 12 15"></polygon>
                                  </svg></div></div>

                                  <div class=" col col-xs-12 col-md-7 p-1"><?php echo $row->description ?></div>
                                      <div class="text-center  col col-xs-12 col-md-3 p-1"><?php echo date_i18n(get_option('date_format'), $row->datemade) ?></div>
                                </div>

    												<?php

                            global $wpdb;
                            $s = "update ".$wpdb->prefix."project_notifications set rd='1' where id='{$row->id}'";
                            $wpdb->query($s);
                            
    										}



    									}
    									 ?>


                    </div>


                    </div>  </div> </div>
    <?php

    $page = ob_get_contents();
    	ob_end_clean();

    	return $page;

}


 ?>
