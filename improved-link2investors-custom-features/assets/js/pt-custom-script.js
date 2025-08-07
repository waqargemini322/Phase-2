// Link2Investors Custom Features JavaScript
(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initConnectionButtons();
        initZoomControls();
        initCreditDisplay();
    });
    
    // Initialize connection buttons
    function initConnectionButtons() {
        $(document).on('click', '.pt-connect-btn:not(.pending):not(.connected)', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var userId = $button.data('user-id');
            
            if (!userId) {
                console.error('No user ID found for connection button');
                return;
            }
            
            // Disable button to prevent double-clicking
            $button.prop('disabled', true);
            
            $.ajax({
                url: pt_ajax_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'send_connection_request',
                    receiver_id: userId,
                    nonce: pt_ajax_obj.send_connection_nonce
                },
                success: function(response) {
                    if (response.success) {
                        $button.text('Request Sent').addClass('pending').prop('disabled', true);
                        showNotification('Connection request sent successfully!', 'success');
                    } else {
                        showNotification(response.data || 'Failed to send connection request.', 'error');
                        $button.prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    showNotification('An error occurred while sending the request.', 'error');
                    $button.prop('disabled', false);
                }
            });
        });
    }
    
    // Initialize zoom controls
    function initZoomControls() {
        $(document).on('click', '.zoom-button-circle', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var thid = $('#current_thid').val();
            
            if (!thid) {
                showNotification('No active chat thread found.', 'error');
                return;
            }
            
            // Disable button to prevent double-clicking
            $button.prop('disabled', true);
            
            $.ajax({
                url: pt_ajax_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'create_zoom_meeting',
                    thid: thid,
                    nonce: pt_ajax_obj.zoom_meeting_nonce
                },
                success: function(response) {
                    if (response.success) {
                        var zoomUrl = response.data.zoom_url;
                        var remainingCredits = response.data.remaining_credits;
                        
                        // Update zoom invites count
                        $('#zoom-invites-count').text(remainingCredits);
                        
                        // Add zoom link to chat
                        addZoomLinkToChat(zoomUrl);
                        
                        showNotification('Zoom meeting created successfully!', 'success');
                    } else {
                        showNotification(response.data || 'Failed to create zoom meeting.', 'error');
                        $button.prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    showNotification('An error occurred while creating the zoom meeting.', 'error');
                    $button.prop('disabled', false);
                }
            });
        });
    }
    
    // Add zoom link to chat
    function addZoomLinkToChat(zoomUrl) {
        var zoomMessage = '<div class="zoom-meeting-link">' +
                         '<i class="fa fa-video-camera"></i> ' +
                         '<a href="' + zoomUrl + '" target="_blank" class="zoom-link">' +
                         'Join Zoom Meeting</a>' +
                         '</div>';
        
        // Add to messages box
        $('#messages-box').append('<li class="sent"><p>' + zoomMessage + '</p></li>');
        
        // Scroll to bottom
        var objDiv = document.getElementById("messages-box");
        if (objDiv) {
            objDiv.scrollTop = objDiv.scrollHeight;
        }
    }
    
    // Initialize credit display
    function initCreditDisplay() {
        // Update credit counts periodically
        updateCreditDisplay();
        setInterval(updateCreditDisplay, 30000); // Update every 30 seconds
    }
    
    // Update credit display
    function updateCreditDisplay() {
        $.ajax({
            url: pt_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'get_all_credits',
                nonce: pt_ajax_obj.all_credits_nonce
            },
            success: function(response) {
                if (response.success) {
                    var credits = response.data;
                    
                    // Update zoom invites count if element exists
                    if ($('#zoom-invites-count').length) {
                        $('#zoom-invites-count').text(credits.invite);
                    }
                    
                    // Update other credit displays if they exist
                    if ($('.connect-credits-count').length) {
                        $('.connect-credits-count').text(credits.connect);
                    }
                    
                    if ($('.bid-credits-count').length) {
                        $('.bid-credits-count').text(credits.bid);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Error updating credits:', error);
            }
        });
    }
    
    // Show notification
    function showNotification(message, type) {
        // Create notification element
        var $notification = $('<div class="pt-notification ' + type + '">' + message + '</div>');
        
        // Add to page
        $('body').append($notification);
        
        // Show notification
        $notification.fadeIn(300);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Handle zoom link clicks
    $(document).on('click', '.zoom-link', function(e) {
        e.preventDefault();
        
        var zoomUrl = $(this).attr('href');
        
        // Open in new window/tab
        window.open(zoomUrl, '_blank', 'width=800,height=600');
        
        // Show notification
        showNotification('Opening Zoom meeting...', 'info');
    });
    
    // Handle connection button hover effects
    $(document).on('mouseenter', '.pt-connect-btn', function() {
        if (!$(this).hasClass('pending') && !$(this).hasClass('connected')) {
            $(this).addClass('hover');
        }
    }).on('mouseleave', '.pt-connect-btn', function() {
        $(this).removeClass('hover');
    });
    
    // Handle zoom button hover effects
    $(document).on('mouseenter', '.zoom-button-circle', function() {
        $(this).addClass('hover');
    }).on('mouseleave', '.zoom-button-circle', function() {
        $(this).removeClass('hover');
    });
    
})(jQuery);