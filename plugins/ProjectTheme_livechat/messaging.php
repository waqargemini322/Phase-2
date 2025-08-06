<?php
// Functions from the original file (unchanged)
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

function projecttheme_get_pm_link_for_thid($thid) { /* ... */ }
add_shortcode('project_theme_my_account_livechat', 'pt_live_chat_messaging');
if (!class_exists('project_chat')) { include 'chat-regular.class.php'; }

/**
 * Renders the entire live chat page.
 * This is the definitive, fully corrected version.
 */
function pt_live_chat_messaging()
{
    ob_start();

    // Error handling for insufficient credits (unchanged)
    if (isset($_GET['chat_error']) && $_GET['chat_error'] === 'no_connect_credits') {
        // ... code to display "Out of Credits" message ...
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
                <div id="frame">
                    <div id="sidepanel">
                        <div id="profile"><div class="wrap">
                            <img id="profile-img" src="<?php echo esc_url(function_exists('projectTheme_get_avatar') ? projectTheme_get_avatar($uid, 50, 50) : ''); ?>" class="online" alt="" />
                            <p><?php echo esc_html($current_user->user_login); ?></p>
                        </div></div>
                        <div id="search">
                            <label for="searchbar_search"><i class="fa fa-search" aria-hidden="true"></i></label>
                            <input type="text" class="bar" autocomplete="off" placeholder="<?php _e('Search contacts...', 'ProjectTheme'); ?>" id='searchbar_search' />
                        </div>
                        <div id="contacts"><ul id='contacts-ul'></ul></div>
                    </div>
                    <div class="content">
                        <?php
                            // This block now correctly fetches and displays the recipient's info
                            if ($current_thid > 0) {
                                $chat = new project_chat($current_thid);
                                $thread_info = $chat->get_single_thread_info($current_thid);
                                if ($thread_info) {
                                    $other_user_id = ($thread_info->user1 == $uid) ? $thread_info->user2 : $thread_info->user1; // Corrected variable name $thread->user1 to $thread_info->user1
                                    $other_user_data = get_userdata($other_user_id);
                                    $to_user = $other_user_id;
                        ?>
                                <div class="contact-profile">
                                    <div class="profile-info-group">
                                        <img src="<?php echo esc_url(function_exists('projectTheme_get_avatar') ? projectTheme_get_avatar($other_user_id, 40, 40) : ''); ?>" alt="" />
                                        <?php if ($other_user_data): ?><p><?php echo esc_html($other_user_data->user_login); ?></p><?php endif; ?>
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
                        <!-- NEW: Message display area for Zoom feedback -->
                        <div id="chat-message-area" class="pt-message-box" style="margin: 10px 20px;"></div>
                        <!-- NEW: Countdown display area for Zoom link expiration -->
                        <div id="zoom-cooldown-timer" class="pt-message-box info" style="margin: 10px 20px; display:none;"></div>


                        <div class="messages" id="messages">
                            <ul id="messages-box">
                                <?php
                                // RESTORED: This PHP block correctly loads the initial message history.
                                if ($current_thid > 0 && isset($chat)) {
                                    $messages = $chat->get_all_messages_from_thread(); 
                                    $last_id = 0;
                                    if (is_array($messages)) {
                                        foreach ($messages as $message) {
                                            $last_id = $message->id;
                                            $message_class = ($message->initiator == $uid) ? 'sent' : 'replies';
                                            $avatar_url = function_exists('projectTheme_get_avatar') ? projectTheme_get_avatar($message->initiator, 30, 30) : '';
                                            
                                            // Pass the raw content from the database.
                                            // The JS function processMessageContentForDisplay will be called on this.
                                            $display_content = wp_unslash($message->content); 
                                            ?>
                                            <li class="<?php echo esc_attr($message_class); ?>" data-message-id="<?php echo esc_attr($message->id); ?>">
                                                <img src="<?php echo esc_url($avatar_url); ?>" alt="" />
                                                <p><?php echo $display_content; // Directly echo the content, JS will process ?></p>
                                            </li>
                                            <?php
                                        }
                                    }
                                }
                                ?>
                            </ul>
                        </div>
                        <div class="message-input">
                            <div class="wrap">
                                <input type="hidden" value="<?php echo $current_thid; ?>" id="current_thid" />
                                <input type="hidden" value="<?php echo isset($to_user) ? $to_user : 0; ?>" id="to_user" />
                                <input type="hidden" value="<?php echo isset($last_id) ? $last_id : 0; ?>" id="last_id" />
                                <input type="hidden" value="<?php echo $uid; ?>" id="otherpartyid" />
                                <input type="hidden" value="<?php echo $current_thid; ?>" id="thid" />

                                <input type="file" id="myfile" name="myfile[]" multiple style="display: none;" />
                                <button class="file-attach-button" id="openfile" title="Attach File"><i class="fa fa-paperclip" aria-hidden="true"></i></button>
                                <span class="message-input-file"></span>
                                
                                <input type="text" placeholder="Write your message..." id="text_message_box" />
                                <button class="submit" id="send_me_a_message"><i class="fa fa-paper-plane" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    // Fetch current user's credits for the banner display
    $current_bids = function_exists('pt_get_bid_credits') ? pt_get_bid_credits($uid) : 0;
    $current_connects = function_exists('pt_get_connect_credits') ? pt_get_connect_credits($uid) : 0;
    $current_invites = function_exists('pt_get_invite_credits') ? pt_get_invite_credits($uid) : 0;
?>
<script type="text/javascript">
    // Initial values for the top banner, will be updated by AJAX
    document.addEventListener('DOMContentLoaded', function() {
        var bidsElement = document.querySelector('.alert-info:contains("You have")');
        if (bidsElement) {
            bidsElement.innerHTML = bidsElement.innerHTML
                .replace(/You have (\d+) bids left./, `You have <span id="banner-bids-count"><?php echo $current_bids; ?></span> bids left.`)
                .replace(/CONNECTS Credits: (\d+)/, `CONNECTS Credits: <span id="banner-connects-count"><?php echo $current_connects; ?></span>`)
                .replace(/ZOOM Invites: (\d+)/, `ZOOM Invites: <span id="banner-invites-count"><?php echo $current_invites; ?></span>`);
        }
    });
</script>
<?php
    return ob_get_clean();
}
?>
