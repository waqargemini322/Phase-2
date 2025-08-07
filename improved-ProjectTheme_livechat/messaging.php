<?php
/**
 * Checks if a user is currently considered online.
 * A user is considered online if their 'lastonline' meta was updated within the last X minutes.
 *
 * @param int $uid User ID.
 * @return bool True if online, false otherwise.
 */
function projecttheme_is_user_online($uid) {
    if (empty($uid)) return false;

    // Ensure we get the freshest data by clearing cache before fetching
    clean_user_cache($uid);

    $last_online_timestamp = (int) get_user_meta($uid, 'lastonline', true);
    
    // Define the time window for being considered online (e.g., 5 minutes = 300 seconds)
    $online_threshold = apply_filters('projecttheme_online_threshold', 300); // 5 minutes by default

    // Current time in timestamp
    $current_time = current_time('timestamp');

    if (($current_time - $last_online_timestamp) < $online_threshold) {
        return true;
    }

    return false;
}

/**
 * Get PM link for thread ID.
 */
function projecttheme_get_pm_link_for_thid($thid) {
    $pm_page = get_option('ProjectTheme_my_account_livechat_id');
    
    if (projecttheme_using_permalinks()) {
        return get_permalink($pm_page) . "?thid=" . $thid;
    } else {
        return get_permalink($pm_page) . "&thid=" . $thid;
    }
}

// Add shortcode
add_shortcode('project_theme_my_account_livechat', 'pt_live_chat_messaging');

// Include chat class
if (!class_exists('project_chat')) { 
    include 'chat-regular.class.php'; 
}

/**
 * Renders the entire live chat page.
 * This is the definitive, fully corrected version.
 */
function pt_live_chat_messaging() {
    ob_start();

    // Error handling for insufficient credits
    if (isset($_GET['chat_error']) && $_GET['chat_error'] === 'no_connect_credits') {
        ?>
        <div class="page-wrapper" style="display:block">
            <div class="container-fluid">
                <div class="row row-no-margin">
                    <div class="col-xs-12">
                        <div class="alert alert-warning">
                            <h4>Out of Credits</h4>
                            <p>You don't have enough connection credits to start a new chat. Please upgrade your membership or wait for your credits to renew.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    global $current_user;
    $current_user = wp_get_current_user();
    $uid = $current_user->ID;
    $current_thid = isset($_GET['thid']) ? (int)$_GET['thid'] : 0;
?>

<div class="page-wrapper" style="display:block">
    <div class="container-fluid">
        <?php do_action('pt_at_account_dash_top'); ?>
        <div class="row row-no-margin">
            <div class="col-xs-12">
                <div class="card nopadding mb-4">
                    <input type="hidden" value="<?php echo esc_url(function_exists('projectTheme_get_avatar') ? projectTheme_get_avatar($uid, 40, 40) : ''); ?>" id="my-current-avatar" />
                    <input type="hidden" value="<?php echo esc_attr($current_user->user_login); ?>" id="username-of-user" />
                    <input type="hidden" value="<?php echo esc_attr($uid); ?>" id="current-user-id" />
                    
                    <div id="frame">
                        <div id="sidepanel">
                            <div id="profile">
                                <div class="wrap">
                                    <img id="profile-img" src="<?php echo esc_url(function_exists('projectTheme_get_avatar') ? projectTheme_get_avatar($uid, 50, 50) : ''); ?>" class="online" alt="" />
                                    <p><?php echo esc_html($current_user->user_login); ?></p>
                                </div>
                            </div>
                            <div id="search">
                                <label for="searchbar_search"><i class="fa fa-search" aria-hidden="true"></i></label>
                                <input type="text" class="bar" autocomplete="off" placeholder="<?php _e('Search contacts...', 'ProjectTheme'); ?>" id='searchbar_search' />
                            </div>
                            <div id="contacts">
                                <ul id='contacts-ul'></ul>
                            </div>
                        </div>
                        <div class="content">
                            <?php
                            // This block now correctly fetches and displays the recipient's info
                            if ($current_thid > 0) {
                                $chat = new project_chat($current_thid);
                                $thread_info = $chat->get_single_thread_info($current_thid);
                                if ($thread_info) {
                                    $other_user_id = ($thread_info->user1 == $uid) ? $thread_info->user2 : $thread_info->user1;
                                    $other_user_data = get_userdata($other_user_id);
                                    $to_user = $other_user_id;
                            ?>
                                    <div class="contact-profile">
                                        <div class="profile-info-group">
                                            <img src="<?php echo esc_url(function_exists('projectTheme_get_avatar') ? projectTheme_get_avatar($other_user_id, 40, 40) : ''); ?>" alt="" />
                                            <?php if ($other_user_data): ?>
                                                <p><?php echo esc_html($other_user_data->user_login); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <?php
                                        $current_user_role = function_exists('ProjectTheme_mems_get_current_user_role') ? ProjectTheme_mems_get_current_user_role($uid) : '';
                                        if ($current_user_role === 'investor') {
                                        ?>
                                            <div id="zoom-controls-group">
                                                <span id="zoom-invites-label">Zoom Invites: <span id="zoom-invites-count">0</span></span>
                                                <div class="zoom-button-circle" title="Start Zoom Meeting">Zoom</div>
                                            </div>
                                        <?php } ?>
                                    </div>
                            <?php
                                }
                            }
                            ?>
                            <div class="messages" id="messages-box">
                                <ul id="chat-messages">
                                    <?php
                                    if ($current_thid > 0) {
                                        $messages = $chat->get_all_messages_from_thread();
                                        if ($messages) {
                                            $last_message_id = 0;
                                            foreach ($messages as $message) {
                                                $message_content = $message->content;
                                                $is_sent = ($message->initiator == $uid);
                                                $last_message_id = $message->id;
                                                
                                                // Process message content for display
                                                $display_content = processMessageContentForDisplay($message_content);
                                                
                                                if ($is_sent) {
                                                    echo '<li class="sent"><img src="' . esc_url(function_exists('projectTheme_get_avatar') ? projectTheme_get_avatar($uid, 30, 30) : '') . '" width="30" height="30" alt=""> <p>' . $display_content . '</p></li>';
                                                } else {
                                                    echo '<li class="replies"><img src="' . esc_url(function_exists('projectTheme_get_avatar') ? projectTheme_get_avatar($other_user_id, 30, 30) : '') . '" width="30" height="30" alt=""> <p>' . $display_content . '</p></li>';
                                                }
                                            }
                                            echo '<input type="hidden" id="last_id" value="' . $last_message_id . '">';
                                        } else {
                                            echo '<input type="hidden" id="last_id" value="0">';
                                        }
                                    } else {
                                        echo '<input type="hidden" id="last_id" value="0">';
                                    }
                                    ?>
                                </ul>
                            </div>
                            <div class="message-input">
                                <div class="wrap">
                                    <input type="text" id="text_message_box" placeholder="Write your message..." />
                                    <input type="file" id="myfile" multiple style="display: none;" />
                                    <div class="message-input-file"></div>
                                    <button class="submit" id="send-button">
                                        <i class="fa fa-paper-plane" aria-hidden="true"></i>
                                    </button>
                                    <button class="file-upload-btn" onclick="document.getElementById('myfile').click();">
                                        <i class="fa fa-paperclip" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" id="current_thid" value="<?php echo esc_attr($current_thid); ?>" />
                    <input type="hidden" id="to_user" value="<?php echo esc_attr(isset($to_user) ? $to_user : ''); ?>" />
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Set current user ID for JavaScript
window.currentUserId = <?php echo $uid; ?>;
</script>

<?php
    return ob_get_clean();
}

/**
 * Process message content for display, handling file attachments.
 */
function processMessageContentForDisplay($rawContentFromDb) {
    if (empty($rawContentFromDb)) {
        return '';
    }
    
    $textPart = $rawContentFromDb;
    $filePreviewsHtml = '';

    // Extract and process JSON file attachment data
    $file_attachment_json_pattern = '/__FILE_ATTACHMENT_JSON_START__([\s\S]*?)__FILE_ATTACHMENT_JSON_END__/i';
    if (preg_match($file_attachment_json_pattern, $rawContentFromDb, $jsonMatch)) {
        // Remove the JSON part from the text part
        $textPart = preg_replace($file_attachment_json_pattern, '', $rawContentFromDb);

        if (isset($jsonMatch[1])) {
            try {
                // Safely decode HTML entities if present within the JSON string
                $jsonStringForParsing = html_entity_decode($jsonMatch[1]);
                
                // PHP's json_encode escapes forward slashes, so we need to unescape them
                $jsonStringForParsing = str_replace('\\"', '"', $jsonStringForParsing);
                $jsonStringForParsing = str_replace('\\\\', '\\', $jsonStringForParsing);
                $jsonStringForParsing = str_replace('\\u002F', '/', $jsonStringForParsing);

                $fileDataArray = json_decode($jsonStringForParsing, true);
                
                if (is_array($fileDataArray)) {
                    foreach ($fileDataArray as $fileData) {
                        $filePreviewsHtml .= generateFilePreviewHtml($fileData);
                    }
                }
            } catch (Exception $e) {
                error_log("ERROR: Error parsing file attachment JSON: " . $e->getMessage());
            }
        }
    }

    // Combine text and file previews
    $finalHtml = esc_html($textPart);
    if ($filePreviewsHtml) {
        $finalHtml .= '<div class="message-attachments">' . $filePreviewsHtml . '</div>';
    }

    return $finalHtml;
}

/**
 * Generate file preview HTML based on file data.
 */
function generateFilePreviewHtml($fileData) {
    if (empty($fileData) || empty($fileData['url']) || empty($fileData['name'])) {
        return '';
    }
    
    $previewElementHtml = '';
    $iconClass = 'fa fa-file';

    if (!empty($fileData['type'])) {
        if (strpos($fileData['type'], 'image') !== false) {
            $previewElementHtml = '<img src="' . esc_url($fileData['url']) . '" alt="' . esc_attr($fileData['name']) . '">';
            $iconClass = 'fa fa-file-image';
        } elseif (strpos($fileData['type'], 'video') !== false) {
            $previewElementHtml = '<i class="fa fa-file-video"></i>';
            $iconClass = 'fa fa-file-video';
        } elseif (strpos($fileData['type'], 'pdf') !== false) {
            $previewElementHtml = '<i class="fa fa-file-pdf"></i>';
            $iconClass = 'fa fa-file-pdf';
        } elseif (strpos($fileData['type'], 'word') !== false || 
                  strtolower(substr($fileData['name'], -4)) === '.doc' || 
                  strtolower(substr($fileData['name'], -5)) === '.docx') {
            $previewElementHtml = '<i class="fa fa-file-word"></i>';
            $iconClass = 'fa fa-file-word';
        } elseif (strpos($fileData['type'], 'excel') !== false || 
                  strtolower(substr($fileData['name'], -4)) === '.xls' || 
                  strtolower(substr($fileData['name'], -5)) === '.xlsx') {
            $previewElementHtml = '<i class="fa fa-file-excel"></i>';
            $iconClass = 'fa fa-file-excel';
        } elseif (strpos($fileData['type'], 'powerpoint') !== false || 
                  strtolower(substr($fileData['name'], -4)) === '.ppt' || 
                  strtolower(substr($fileData['name'], -5)) === '.pptx') {
            $previewElementHtml = '<i class="fa fa-file-powerpoint"></i>';
            $iconClass = 'fa fa-file-powerpoint';
        } elseif (strpos($fileData['type'], 'zip') !== false || 
                  strpos($fileData['type'], 'rar') !== false || 
                  strtolower(substr($fileData['name'], -4)) === '.zip' || 
                  strtolower(substr($fileData['name'], -4)) === '.rar' || 
                  strtolower(substr($fileData['name'], -3)) === '.7z') {
            $previewElementHtml = '<i class="fa fa-file-archive"></i>';
            $iconClass = 'fa fa-file-archive';
        } elseif (strpos($fileData['type'], 'text') !== false || 
                  strtolower(substr($fileData['name'], -4)) === '.txt' || 
                  strtolower(substr($fileData['name'], -4)) === '.csv' || 
                  strtolower(substr($fileData['name'], -4)) === '.log') {
            $previewElementHtml = '<i class="fa fa-file-alt"></i>';
            $iconClass = 'fa fa-file-alt';
        } elseif (strpos($fileData['type'], 'audio') !== false) {
            $previewElementHtml = '<i class="fa fa-file-audio"></i>';
            $iconClass = 'fa fa-file-audio';
        } else {
            $previewElementHtml = '<i class="fa fa-file"></i>';
        }
    } else {
        $previewElementHtml = '<i class="fa fa-file"></i>';
    }

    return '<div class="chat-file-preview" data-file-url="' . esc_attr($fileData['url']) . '" data-file-type="' . esc_attr($fileData['type']) . '">' .
           '<a href="' . esc_url($fileData['url']) . '" target="_blank" download="' . esc_attr($fileData['name']) . '">' .
           $previewElementHtml .
           '<span>' . esc_html($fileData['name']) . '</span>' .
           '</a></div>';
}