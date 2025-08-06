<?php

class project_notifications
{
  function __construct() {

   }

   function insert_notification($uid, $related_id, $description, $notification_type)
   {

     $datemade = current_time('timestamp');


     global $wpdb;
     $s = "insert into ".$wpdb->prefix."project_notifications (uid, related_id, notification_type, description, datemade) values('$uid','$related_id','$notification_type','$description','$datemade' )";
     $wpdb->query($s);

   }
}

 ?>
