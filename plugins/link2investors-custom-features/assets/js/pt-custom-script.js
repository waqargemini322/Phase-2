jQuery(document).ready(function($) {

    //======================================================================
    // 1. DYNAMIC CHATBOX LAYOUT FIX (Definitive Version)
    //======================================================================
    function adjustChatLayout() {
        var frame = $('#frame');
        if (frame.length) {
            var frameTopPosition = frame.offset().top;
            var windowHeight = $(window).height();
            var bottomPadding = 20;
            var newHeight = windowHeight - frameTopPosition - bottomPadding;

            frame.css({
                'width': '100%',
                'height': Math.max(newHeight, 650) + 'px'
            });

            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                        const currentWidth = frame.css('width');
                        if (currentWidth !== '100%' && currentWidth.includes('px')) {
                            frame.css('width', '100%');
                        }
                    }
                });
            });

            observer.observe(frame[0], { attributes: true });
            frame.css('width', '100%');
        }
    }

    setTimeout(function() {
        adjustChatLayout();
    }, 100);
    $(window).on('resize', adjustChatLayout);


    //======================================================================
    // 2. ZOOM INVITE SYSTEM (Complete)
    //======================================================================
    function getAndDisplayZoomInvites() {
        if ($('#zoom-controls-group').length === 0) return;
        $.ajax({
            url: pt_ajax_obj.ajax_url,
            type: 'POST',
            data: { action: 'l2i_get_zoom_invites', nonce: pt_ajax_obj.zoom_meeting_nonce },
            success: function(response) {
                if (response.success) {
                    $('#zoom-invites-count').text(response.data.invites);
                }
            }
        });
    }
    getAndDisplayZoomInvites();
    setInterval(getAndDisplayZoomInvites, 30000); 


    //======================================================================
    // 3. REAL-TIME TOP BANNER CREDIT UPDATES (NEW)
    //======================================================================
    function getAndDisplayAllCredits() {
        const $bidsElement = $('.alert-info:contains("You have")');
        if ($bidsElement.length === 0) return;

        $.ajax({
            url: pt_ajax_obj.ajax_url,
            type: 'POST',
            data: { action: 'l2i_get_all_credits', nonce: pt_ajax_obj.all_credits_nonce },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    let currentBannerHtml = $bidsElement.html();
                    currentBannerHtml = currentBannerHtml.replace(/You have (\d+) bids left./, `You have ${data.bids} bids left.`);
                    currentBannerHtml = currentBannerHtml.replace(/CONNECTS Credits: (\d+)/, `CONNECTS Credits: ${data.connects}`);
                    currentBannerHtml = currentBannerHtml.replace(/ZOOM Invites: (\d+)/, `ZOOM Invites: ${data.invites}`);
                    $bidsElement.html(currentBannerHtml);

                }
            }
        });
    }

    getAndDisplayAllCredits();
    setInterval(getAndDisplayAllCredits, 10000);


    //======================================================================
    // 4. ZOOM MEETING INITIATION & COOLDOWN (FINAL)
    //======================================================================

    let zoomCooldownInterval = null;

    function displayMessage(message, type = 'info', container_id = '#chat-message-area') {
        const messageContainer = $(container_id);
        if (messageContainer.length) {
            messageContainer.removeClass('success error info').addClass(type).html(message).fadeIn();
            setTimeout(() => { messageContainer.fadeOut(); }, 5000);
        }
    }

    /**
     * Starts the countdown timer for the Zoom link expiration.
     * @param {number} expirationTimestamp Unix timestamp when the link expires (in seconds).
     */
    function startZoomCooldownCountdown(expirationTimestamp) {
        const $cooldownTimer = $('#zoom-cooldown-timer');
        if (zoomCooldownInterval) {
            clearInterval(zoomCooldownInterval);
        }

        function updateCountdown() {
            const currentTime = Math.floor(Date.now() / 1000);
            const timeLeft = expirationTimestamp - currentTime;

            if (timeLeft > 0) {
                // Calculate minutes, rounding up.
                const minutes = Math.ceil(timeLeft / 60);
                // Handle singular vs. plural text
                const minutesText = minutes === 1 ? 'minute' : 'minutes';
                // Display minutes only, without "approximately"
                $cooldownTimer.html(`Zoom link expires in ${minutes} ${minutesText}`).fadeIn();
            } else {
                $cooldownTimer.fadeOut().html('');
                clearInterval(zoomCooldownInterval);
                zoomCooldownInterval = null; 
            }
        }
        updateCountdown();
        // Update the timer every 30 seconds as seconds are no longer displayed
        zoomCooldownInterval = setInterval(updateCountdown, 30000);
    }

    /**
     * Fetches the current Zoom cooldown status for the active thread.
     */
    function fetchZoomCooldownStatus() {
        const thread_id = $('#current_thid').val();
        if (!thread_id || thread_id <= 0) {
            $('#zoom-cooldown-timer').fadeOut().html('');
             if (zoomCooldownInterval) {
                clearInterval(zoomCooldownInterval);
                zoomCooldownInterval = null;
            }
            return;
        }

        $.ajax({
            url: pt_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'get_zoom_cooldown_status',
                thid: thread_id
            },
            success: function(response) {
                if (response.success && response.data.zoom_link_url && response.data.zoom_link_timestamp) {
                    const expirationTimestamp = response.data.zoom_link_timestamp + 3600; 
                    const currentTime = Math.floor(Date.now() / 1000);
                    if (expirationTimestamp > currentTime) { 
                        startZoomCooldownCountdown(expirationTimestamp);
                    } else {
                        $('#zoom-cooldown-timer').fadeOut().html('');
                        if (zoomCooldownInterval) {
                            clearInterval(zoomCooldownInterval);
                            zoomCooldownInterval = null;
                        }
                    }
                } else {
                    $('#zoom-cooldown-timer').fadeOut().html('');
                    if (zoomCooldownInterval) {
                        clearInterval(zoomCooldownInterval);
                        zoomCooldownInterval = null;
                    }
                }
            },
            error: function() {
                $('#zoom-cooldown-timer').fadeOut().html('');
                if (zoomCooldownInterval) {
                    clearInterval(zoomCooldownInterval);
                    zoomCooldownInterval = null;
                }
            }
        });
    }

    // Check status on page load and when a new chat thread is selected
    fetchZoomCooldownStatus();
    $(document).on('click', '#contacts-ul li.contact', function() {
        setTimeout(fetchZoomCooldownStatus, 200); 
    });


    $(document).on('click', '.zoom-button-circle', function(e) {
        e.preventDefault();
        var $zoomButton = $(this);
        if ($zoomButton.data('isProcessing')) {
            return;
        }
        
        const to_user_id = $('#to_user').val();
        const thread_id = $('#current_thid').val();
        if (!to_user_id || !thread_id) {
            displayMessage('Error: Could not identify the recipient or chat thread.', 'error', '#chat-message-area');
            return;
        }
        
        $zoomButton.data('isProcessing', true).css('opacity', '0.5');

        $.ajax({
            url: pt_ajax_obj.ajax_url,
            type: 'POST',
            data: { action: 'link2investors_create_zoom_meeting', nonce: pt_ajax_obj.zoom_meeting_nonce, to_user: to_user_id, thid: thread_id },
            success: function(response) {
                if (response.success) {
                    $('#zoom-invites-count').text(response.data.remaining_invites);
                    getAndDisplayAllCredits(); 

                    if (response.data.reused_link) {
                        displayMessage('Zoom meeting link reused!', 'info', '#chat-message-area');
                    } else {
                        displayMessage('New Zoom meeting created!', 'success', '#chat-message-area');
                    }

                    const currentTimestamp = Math.floor(Date.now() / 1000);
                    const expirationTimestamp = currentTimestamp + response.data.cooldown_remaining;
                    startZoomCooldownCountdown(expirationTimestamp);
                    
                } else {
                    displayMessage('Error: ' + response.data.message, 'error', '#chat-message-area');
                    getAndDisplayZoomInvites();
                    fetchZoomCooldownStatus();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                displayMessage('Network error occurred. Please try again.', 'error', '#chat-message-area');
                getAndDisplayZoomInvites(); 
                fetchZoomCooldownStatus();
            },
            complete: function() {
                $zoomButton.removeData('isProcessing').css('opacity', '1');
            }
        });
    });


    //======================================================================
    // 5. CONNECTION REQUEST SYSTEM
    //======================================================================
    function displayGeneralMessage(message, type = 'info', container_id = '#pt-connection-message') {
        const messageContainer = $(container_id);
        if(messageContainer.length) {
            messageContainer.removeClass('success error info').addClass(type).html(message).fadeIn();
            setTimeout(() => { messageContainer.fadeOut(); }, 5000);
        }
    }
    
    $(document).on('click', '#pt-send-connection-request', function(e) {
        e.preventDefault();
        const $button = $(this);
        const receiverId = $button.data('receiver-id');
        $button.prop('disabled', true).text('Sending...');

        $.ajax({
            url: pt_ajax_obj.ajax_url,
            type: 'POST',
            data: { action: 'pt_send_connection_request', receiver_id: receiverId, _wpnonce: pt_ajax_obj.send_connection_nonce },
            success: function(response) {
                if (response.success) {
                    displayGeneralMessage(response.data, 'success');
                    $button.text('Request Sent');
                    getAndDisplayAllCredits(); 
                } else {
                    displayGeneralMessage('Error: ' + response.data, 'error');
                    $button.prop('disabled', false).text('Send Connection Request');
                }
            },
            error: function() {
                displayGeneralMessage('Network error: Could not send request.', 'error');
                $button.prop('disabled', false).text('Send Connection Request');
            }
        });
    });

    $(document).on('click', '.accept-connection, .reject-connection', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $listItem = $button.closest('li');
        const requestId = $listItem.data('request-id');
        const actionType = $button.hasClass('accept-connection') ? 'accept' : 'reject';
        $button.prop('disabled', true).siblings('button').prop('disabled', true);

        $.ajax({
            url: pt_ajax_obj.ajax_url,
            type: 'POST',
            data: { action: 'pt_handle_connection_action', request_id: requestId, action_type: actionType, _wpnonce: pt_ajax_obj.connection_action_nonce },
            success: function(response) {
                if (response.success) {
                    displayGeneralMessage(response.data, 'success', '#pt-requests-message');
                    $listItem.fadeOut('slow', function() { $(this).remove(); });
                    getAndDisplayAllCredits(); 
                } else {
                    displayGeneralMessage('Error: ' + response.data, 'error', '#pt-requests-message');
                    $button.prop('disabled', false).siblings('button').prop('disabled', false);
                }
            },
            error: function() {
                displayGeneralMessage('Network error: Could not process action.', 'error', '#pt-requests-message');
                $button.prop('disabled', false).siblings('button').prop('disabled', false);
            }
        });
    });
});
