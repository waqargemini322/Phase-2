<?php

class project_chat
{
    private $threadid; // The ID of the current chat thread

    public function __construct($thid = '')
    {
        if (!empty($thid) && is_numeric($thid)) {
            $this->set_thid($thid);
        }
    }

    /**
     * Inserts a new message into the current chat thread.
     * 
     * @param int $from_user The user ID sending the message
     * @param int $to_user The user ID receiving the message
     * @param string $content The message content
     * @param int $attached Whether the message has attachments (0 or 1)
     * @param string|null $attached_files_json_string JSON string of file attachments
     * @return int|false The message ID on success, false on failure
     */
    public function insert_message($from_user, $to_user, $content, $attached = 0, $attached_files_json_string = null)
    {
        global $wpdb;
        
        if (!$this->threadid) {
            return false;
        }
        
        $date_made = current_time('timestamp');
        
        $data = array(
            'threadid' => $this->threadid,
            'initiator' => $from_user,
            'user' => $to_user,
            'content' => $content,
            'datemade' => $date_made,
            'file_attached' => $attached
        );

        $format = array('%d', '%d', '%d', '%s', '%d', '%d');

        // Add attached_files_json if provided
        if ($attached_files_json_string !== null) {
            $data['attached_files_json'] = $attached_files_json_string;
            $format[] = '%s';
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
                array('id' => $this->threadid),
                array('%d'),
                array('%d')
            );
            
            return $wpdb->insert_id;
        }
        
        return false;
    }

    /**
     * Gets or creates a thread ID between two users.
     * This method handles the "Connection Credit" deduction logic.
     *
     * @param int $user1 The ID of the user initiating the chat
     * @param int $user2 The ID of the user being contacted
     * @return int|false The thread ID on success, or false if insufficient credits
     */
    public function get_thread_id($user1, $user2)
    {
        global $wpdb;
        
        // Check if thread already exists
        $s = $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}project_pm_threads WHERE (user1 = %d AND user2 = %d) OR (user1 = %d AND user2 = %d)",
            $user1, $user2, $user2, $user1
        );
        $r = $wpdb->get_results($s);

        // If a thread already exists, just return its ID. No credit is deducted.
        if (count($r) > 0) {
            return $r[0]->id;
        }

        // --- NEW: A thread does NOT exist, so this is a NEW chat initiation. Check for credits. ---
        $initiator_id = $user1;
        
        // Use the credit helper function from the custom features plugin if it exists
        if (function_exists('pt_get_connect_credits')) {
            $current_credits = pt_get_connect_credits($initiator_id);
        } else {
            // Fallback to checking the meta key directly if the function isn't loaded
            $current_credits = (int) get_user_meta($initiator_id, 'pt_connect_credits', true);
        }

        // If the initiator has no credits, PREVENT chat creation and return false
        if ($current_credits <= 0) {
            return false; // Not enough credits to initiate a new chat
        }

        // --- User has credits, so proceed to create the thread AND deduct a credit ---
        $tm = current_time('timestamp');
        
        $result = $wpdb->insert(
            $wpdb->prefix . "project_pm_threads",
            array(
                'user1' => $user1,
                'user2' => $user2,
                'lastupdate' => $tm,
                'datemade' => $tm
            ),
            array('%d', '%d', '%d', '%d')
        );

        if ($result === false) {
            return false;
        }

        $thread_id = $wpdb->insert_id;

        // Deduct one connect credit from the initiator
        if (function_exists('pt_deduct_connect_credits')) {
            pt_deduct_connect_credits($initiator_id, 1);
        } else {
            // Fallback credit deduction
            $new_credits = $current_credits - 1;
            update_user_meta($initiator_id, 'pt_connect_credits', $new_credits);
            clean_user_cache($initiator_id);
        }

        return $thread_id;
    }

    /**
     * Gets all thread IDs for a user.
     *
     * @param int $uid User ID
     * @return array|false Array of thread objects or false on failure
     */
    public function get_all_thread_ids($uid)
    {
        global $wpdb;
        
        $s = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}project_pm_threads WHERE user1 = %d OR user2 = %d ORDER BY lastupdate DESC",
            $uid, $uid
        );
        $r = $wpdb->get_results($s);

        if (count($r) > 0) {
            return $r;
        }
        
        return false;
    }

    /**
     * Gets all thread IDs for a user with search functionality.
     *
     * @param int $uid User ID
     * @param string $search_term Search term
     * @return array|false Array of thread objects or false on failure
     */
    public function get_all_thread_ids_by_search($uid, $search_term = '')
    {
        global $wpdb;
        
        if (empty($search_term)) {
            return $this->get_all_thread_ids($uid);
        }
        
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';
        
        $s = $wpdb->prepare(
            "SELECT DISTINCT t.* FROM {$wpdb->prefix}project_pm_threads t 
             LEFT JOIN {$wpdb->prefix}project_pm p ON t.id = p.threadid 
             LEFT JOIN {$wpdb->prefix}users u ON (t.user1 = u.ID OR t.user2 = u.ID) 
             WHERE (t.user1 = %d OR t.user2 = %d) 
             AND (p.content LIKE %s OR u.user_login LIKE %s OR u.display_name LIKE %s) 
             ORDER BY t.lastupdate DESC",
            $uid, $uid, $search_term, $search_term, $search_term
        );
        $r = $wpdb->get_results($s);

        if (count($r) > 0) {
            return $r;
        }
        
        return false;
    }

    /**
     * Gets the last message of a thread.
     *
     * @param int $thid Thread ID
     * @return object|false Last message object or false on failure
     */
    public function get_last_message_of_thread($thid)
    {
        global $wpdb;
        
        $s = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}project_pm WHERE threadid = %d ORDER BY datemade DESC LIMIT 1",
            $thid
        );
        $r = $wpdb->get_results($s);

        if (count($r) > 0) {
            return $r[0];
        }
        
        return false;
    }

    /**
     * Gets all messages from the current thread.
     *
     * @return array|false Array of message objects or false on failure
     */
    public function get_all_messages_from_thread()
    {
        global $wpdb;
        
        if (!$this->threadid) {
            return false;
        }
        
        $s = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}project_pm WHERE threadid = %d ORDER BY datemade ASC",
            $this->threadid
        );
        $r = $wpdb->get_results($s);

        if (count($r) > 0) {
            return $r;
        }
        
        return false;
    }

    /**
     * Gets messages from a thread with ID higher than specified.
     *
     * @param int $current_user_id Current user ID
     * @param int $higher Minimum message ID
     * @param bool $include_attached_files_json Whether to include file attachment data
     * @return array|false Array of message objects or false on failure
     */
    public function get_messages_from_order_higher_than_id($current_user_id, $higher, $include_attached_files_json = false)
    {
        global $wpdb;
        
        if (!$this->threadid) {
            return false;
        }
        
        $s = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}project_pm WHERE threadid = %d AND id > %d ORDER BY datemade ASC",
            $this->threadid, $higher
        );
        $r = $wpdb->get_results($s);

        if (count($r) > 0) {
            // Process messages to include file attachments if requested
            if ($include_attached_files_json) {
                foreach ($r as $message) {
                    if (!empty($message->attached_files_json)) {
                        // The file data is already in JSON format in the database
                        $message->file_attachments = json_decode($message->attached_files_json, true);
                    } else {
                        $message->file_attachments = array();
                    }
                }
            }
            
            return $r;
        }
        
        return false;
    }

    /**
     * Gets single thread information.
     *
     * @param int $thid Thread ID
     * @return object|false Thread object or false on failure
     */
    public function get_single_thread_info($thid)
    {
        global $wpdb;
        
        $s = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}project_pm_threads WHERE id = %d",
            $thid
        );
        $r = $wpdb->get_results($s);

        if (count($r) > 0) {
            return $r[0];
        }
        
        return false;
    }

    /**
     * Updates zoom link in a thread.
     *
     * @param int $thid Thread ID
     * @param string $zoom_url Zoom meeting URL
     * @param int $timestamp Timestamp when the link was created
     * @return bool Success status
     */
    public function update_zoom_link_in_thread($thid, $zoom_url, $timestamp)
    {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . "project_pm_threads",
            array(
                'zoom_link_url' => $zoom_url,
                'zoom_link_timestamp' => $timestamp
            ),
            array('id' => $thid),
            array('%s', '%d'),
            array('%d')
        );
        
        return $result !== false;
    }

    /**
     * Sets the thread ID.
     *
     * @param int $thid Thread ID
     */
    public function set_thid($thid)
    {
        $this->threadid = (int) $thid;
    }

    /**
     * Gets the thread ID.
     *
     * @return int Thread ID
     */
    public function get_thid()
    {
        return $this->threadid;
    }
}