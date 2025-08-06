<?php

class project_chat
{
    private $threadid; // The ID of the current chat thread

    public function __construct($thid = '')
    {
        if(!empty($thid) && is_numeric($thid)) {
            $this->set_thid($thid);
        }
    }

    /**
     * Inserts a new message into the current chat thread.
     * Added $attached_files_json_string parameter.
     */
    public function insert_message($from_user, $to_user, $content, $attached = 0, $attached_files_json_string = NULL)
    {
        global $wpdb;
        $date_made = current_time('timestamp');
        $oid_1 = $this->threadid;

        $data = array(
            'threadid'     => $oid_1,
            'initiator'    => $from_user,
            'user'         => $to_user,
            'content'      => $content,
            'datemade'     => $date_made,
            'file_attached'=> $attached
        );

        $format = array('%d', '%d', '%d', '%s', '%d', '%d');

        if ($attached_files_json_string !== NULL) {
            $data['attached_files_json'] = $attached_files_json_string;
            $format[] = '%s'; // Add format for the new column
        }

        $result = $wpdb->insert(
            $wpdb->prefix . "project_pm",
            $data,
            $format
        );

        if ($result !== false) {
            // Update the last activity timestamp of the thread
            $wpdb->update(
                $wpdb->prefix . "project_pm_threads",
                array('lastupdate' => $date_made),
                array('id' => $oid_1),
                array('%d'),
                array('%d')
            );
            return $wpdb->insert_id;
        }
        return false;
    }

    /**
     * Gets or creates a thread ID between two users.
     * This method now handles the "Connection Credit" deduction logic.
     *
     * @param int $user1 The ID of the user initiating the chat.
     * @param int $user2 The ID of the user being contacted.
     * @return int|bool The thread ID on success, or false if a new chat cannot be created due to insufficient credits.
     */
    public function get_thread_id($user1, $user2)
    {
        global $wpdb;
        $s = $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}project_pm_threads WHERE (user1 = %d AND user2 = %d) OR (user1 = %d AND user2 = %d)",
            $user1, $user2, $user2, $user1
        );
        $r = $wpdb->get_results($s);

        // If a thread already exists, just return its ID. No credit is deducted.
        if(count($r) > 0) {
            return $r[0]->id;
        }

        // --- NEW: A thread does NOT exist, so this is a NEW chat initiation. Check for credits. ---
        // We assume user1 is the initiator.
        $initiator_id = $user1;
        
        // Use the credit helper function from the custom features plugin if it exists.
        if (function_exists('pt_get_connect_credits')) {
            $current_credits = pt_get_connect_credits($initiator_id);
        } else {
            // Fallback to checking the meta key directly if the function isn't loaded.
            $current_credits = (int) get_user_meta($initiator_id, 'pt_connect_credits', true);
        }

        // If the initiator has no credits, PREVENT chat creation and return false.
        if ($current_credits <= 0) {
            return false; // Not enough credits to initiate a new chat.
        }

        // --- User has credits, so proceed to create the thread AND deduct a credit. ---
        
        // 1. Create the new thread in the database.
        $tm = current_time('timestamp');
        $wpdb->insert(
            $wpdb->prefix . "project_pm_threads",
            array(
                'user1'      => $user1,
                'user2'      => $user2,
                'lastupdate' => $tm,
                'datemade'   => $tm
            ),
            array('%d', '%d', '%d', '%d')
        );
        
        $new_thread_id = $wpdb->insert_id;
        
        // 2. Deduct one connection credit from the initiator.
        if (function_exists('pt_deduct_connect_credits')) {
            pt_deduct_connect_credits($initiator_id, 1);
        } else {
            // Fallback for deduction if the helper function isn't available.
            update_user_meta($initiator_id, 'pt_connect_credits', $current_credits - 1);
        }

        // Return the ID of the newly created thread.
        return $new_thread_id;
    }


    /**
     * Get all thread IDs for a specific user, ordered by recent activity.
     */
    public function get_all_thread_ids($uid)
    {
        global $wpdb;
        $s = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}project_pm_threads WHERE (user1 = %d OR user2 = %d) ORDER BY lastupdate DESC",
            $uid, $uid
        );
        $r = $wpdb->get_results($s);
        return !empty($r) ? $r : false;
    }

    /**
     * Get the last message from a specific thread.
     */
    public function get_last_message_of_thread($thid)
    {
        global $wpdb;
        // Select the new column as well
        $s = $wpdb->prepare("SELECT content, file_attached, attached_files_json, datemade FROM {$wpdb->prefix}project_pm WHERE threadid = %d ORDER BY datemade DESC LIMIT 1", $thid);
        $r = $wpdb->get_results($s);
        return !empty($r) ? $r[0] : false;
    }

    /**
     * Get all messages from the current thread.
     * Added $include_attached_files_json parameter to select the new column.
     */
    public function get_all_messages_from_thread()
    {
        global $wpdb;
        // Select the new column as well
        $s = $wpdb->prepare("SELECT id, initiator, user, content, datemade, file_attached, attached_files_json FROM {$wpdb->prefix}project_pm WHERE threadid = %d ORDER BY datemade ASC", $this->threadid);
        $r = $wpdb->get_results($s);
        return !empty($r) ? $r : false;
    }

    /**
     * Get new messages from a thread since the last known message ID.
     * Added $include_attached_files_json parameter to select the new column.
     */
    public function get_messages_from_order_higher_than_id($current_user_id, $higher, $include_attached_files_json = false)
    {
        global $wpdb;
        if(empty($higher)) $higher = 0;

        $select_columns = "id, initiator, user, content, datemade, file_attached";
        if ($include_attached_files_json) {
            $select_columns .= ", attached_files_json";
        }

        $s = $wpdb->prepare(
            "SELECT {$select_columns} FROM {$wpdb->prefix}project_pm WHERE id > %d AND threadid = %d ORDER BY id ASC",
            $higher, $this->threadid
        );
        $r = $wpdb->get_results($s);

        // Mark incoming messages as read for the current user
        if (!empty($r)) {
            foreach ($r as $message) {
                if ($message->user == $current_user_id && $message->read_status == 0) {
                    $wpdb->update(
                        $wpdb->prefix . "project_pm",
                        array('read_status' => 1),
                        array('id' => $message->id),
                        array('%d'),
                        array('%d')
                    );
                }
            }
        }
        return $r;
    }
    
    /**
     * Get information for a single thread by its ID.
     * Modified to include zoom_link_url and zoom_link_timestamp.
     */
    public function get_single_thread_info($thid)
    {
        global $wpdb;
        $s = $wpdb->prepare("SELECT *, zoom_link_url, zoom_link_timestamp FROM {$wpdb->prefix}project_pm_threads WHERE id = %d", $thid);
        $r = $wpdb->get_results($s);
        return !empty($r) ? $r[0] : false;
    }

    /**
     * Updates the Zoom link and timestamp for a given thread.
     */
    public function update_zoom_link_in_thread($thid, $zoom_url, $timestamp)
    {
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . "project_pm_threads",
            array(
                'zoom_link_url'     => $zoom_url,
                'zoom_link_timestamp' => $timestamp
            ),
            array('id' => $thid),
            array('%s', '%d'),
            array('%d')
        );
        return $result !== false;
    }

    /**
     * Set the current thread ID for the class instance.
     */
    public function set_thid($thid)
    {
        $this->threadid = $thid;
    }
}
