// Ensure jQuery is loaded and use it in no-conflict mode
(function($) {
    'use strict';
    
    var is_at_bottom = 0;
    var initialA = 0;
    var messageUpdateInterval = null;
    var lastMessageId = 0;

    // Utility function to escape HTML for display
    function escapeHtml(text) {
        if (typeof text !== 'string') return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Function to generate file preview HTML based on file data
    function generateFilePreviewHtml(fileData) {
        if (!fileData || !fileData.url || !fileData.name) return '';
        
        let previewElementHtml = '';
        let iconClass = 'fa fa-file';

        if (fileData.type && fileData.type.includes('image')) {
            previewElementHtml = '<img src="' + escapeHtml(fileData.url) + '" alt="' + escapeHtml(fileData.name) + '">';
            iconClass = 'fa fa-file-image';
        } else if (fileData.type && fileData.type.includes('video')) {
            previewElementHtml = '<i class="fa fa-file-video"></i>';
            iconClass = 'fa fa-file-video';
        } else if (fileData.type && fileData.type.includes('pdf')) {
            previewElementHtml = '<i class="fa fa-file-pdf"></i>';
            iconClass = 'fa fa-file-pdf';
        } else if (fileData.type && (fileData.type.includes('word') || fileData.name.toLowerCase().endsWith('.doc') || fileData.name.toLowerCase().endsWith('.docx'))) {
            previewElementHtml = '<i class="fa fa-file-word"></i>';
            iconClass = 'fa fa-file-word';
        } else if (fileData.type && (fileData.type.includes('excel') || fileData.name.toLowerCase().endsWith('.xls') || fileData.name.toLowerCase().endsWith('.xlsx'))) {
            previewElementHtml = '<i class="fa fa-file-excel"></i>';
            iconClass = 'fa fa-file-excel';
        } else if (fileData.type && (fileData.type.includes('powerpoint') || fileData.name.toLowerCase().endsWith('.ppt') || fileData.name.toLowerCase().endsWith('.pptx'))) {
            previewElementHtml = '<i class="fa fa-file-powerpoint"></i>';
            iconClass = 'fa fa-file-powerpoint';
        } else if (fileData.type && (fileData.type.includes('zip') || fileData.type.includes('rar') || fileData.name.toLowerCase().endsWith('.zip') || fileData.name.toLowerCase().endsWith('.rar') || fileData.name.toLowerCase().endsWith('.7z'))) {
            previewElementHtml = '<i class="fa fa-file-archive"></i>';
            iconClass = 'fa fa-file-archive';
        } else if (fileData.type && (fileData.type.includes('text') || fileData.name.toLowerCase().endsWith('.txt') || fileData.name.toLowerCase().endsWith('.csv') || fileData.name.toLowerCase().endsWith('.log'))) {
            previewElementHtml = '<i class="fa fa-file-alt"></i>';
            iconClass = 'fa fa-file-alt';
        } else if (fileData.type && fileData.type.includes('audio')) {
            previewElementHtml = '<i class="fa fa-file-audio"></i>';
            iconClass = 'fa fa-file-audio';
        } else {
            previewElementHtml = '<i class="fa fa-file"></i>';
        }

        return '<div class="chat-file-preview" data-file-url="' + escapeHtml(fileData.url) + '" data-file-type="' + escapeHtml(fileData.type) + '">' +
               '<a href="' + escapeHtml(fileData.url) + '" target="_blank" download="' + escapeHtml(fileData.name) + '">' +
               previewElementHtml +
               '<span>' + escapeHtml(fileData.name) + '</span>' +
               '</a></div>';
    }

    // Process message content for display
    function processMessageContentForDisplay(rawContentFromDb) {
        if (!rawContentFromDb) return '';
        
        let textPart = rawContentFromDb;
        let filePreviewsHtml = '';

        // Extract and process JSON file attachment data
        const file_attachment_json_pattern = /__FILE_ATTACHMENT_JSON_START__([\s\S]*?)__FILE_ATTACHMENT_JSON_END__/i;
        const jsonMatch = rawContentFromDb.match(file_attachment_json_pattern);

        if (jsonMatch && jsonMatch[1]) {
            // Remove the JSON part from the text part
            textPart = rawContentFromDb.replace(file_attachment_json_pattern, '');

            try {
                // Safely decode HTML entities if present within the JSON string
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = jsonMatch[1];
                let jsonStringForParsing = tempDiv.textContent || tempDiv.innerText;

                // PHP's json_encode escapes forward slashes, so we need to unescape them for JSON.parse
                jsonStringForParsing = jsonStringForParsing.replace(/\\"/g, '"').replace(/\\\\/g, '\\');
                jsonStringForParsing = jsonStringForParsing.replace(/\\u002F/g, '/');

                const fileDataArray = JSON.parse(jsonStringForParsing);
                
                if (Array.isArray(fileDataArray)) {
                    fileDataArray.forEach(function(fileData) {
                        filePreviewsHtml += generateFilePreviewHtml(fileData);
                    });
                }
            } catch (e) {
                console.error("ERROR: Error parsing file attachment JSON:", e);
            }
        }

        // Combine text and file previews
        let finalHtml = escapeHtml(textPart);
        if (filePreviewsHtml) {
            finalHtml += '<div class="message-attachments">' + filePreviewsHtml + '</div>';
        }

        return finalHtml;
    }

    // File upload handling
    var Upload = function(file) {
        this.file = file;
    };

    Upload.prototype.getType = function() {
        return this.file.type;
    };

    Upload.prototype.getSize = function() {
        return this.file.size;
    };

    Upload.prototype.getName = function() {
        return this.file.name;
    };

    Upload.prototype.doUpload = function() {
        var that = this;
        var formData = new FormData();

        var chatbox_textarea = $("#text_message_box").val();
        var thid = $("#current_thid").val();
        var to_user = $("#to_user").val();
        var no_file = 1;

        // Add file if present
        if (document.getElementById("myfile") && document.getElementById("myfile").files.length != 0) {
            formData.append("file", this.file, this.getName());
            formData.append("upload_file", true);
            no_file = 2;
        } else {
            no_file = 1;
        }

        formData.append("chatbox_textarea", chatbox_textarea);
        formData.append("to_user", to_user);
        formData.append("thid", thid);
        formData.append("nonce", pt_livechat_ajax.nonce);

        if (no_file == 1 && isEmpty(chatbox_textarea)) {
            return;
        }

        // Update last message ID
        $("#last_id").val(parseInt($("#last_id").val()) + 1);

        var avatarurl = $("#my-current-avatar").val();
        var usernameofuser = $("#username-of-user").val();

        // Add message to UI immediately
        var app = '<li class="sent"><img src="' + avatarurl + '" width="30" height="30" alt=""> <p>' + escapeHtml(chatbox_textarea) + '</p></li>';
        $("#messages-box").append(app);

        $("#text_message_box").val("");

        // Send AJAX request
        $.ajax({
            type: "POST",
            url: pt_livechat_ajax.ajaxurl,
            data: formData,
            xhr: function() {
                var myXhr = $.ajaxSettings.xhr();
                if (myXhr.upload) {
                    myXhr.upload.addEventListener('progress', that.progressHandling, false);
                }
                return myXhr;
            },
            success: function(response) {
                if (response.success) {
                    $(".text_message_box").val("");
                    if (document.getElementById("myfile")) {
                        $("#myfile").val('');
                    }
                    $(".message-input-file").html(" ");
                } else {
                    console.error("Message send failed:", response.data.message);
                    // Remove the message from UI if it failed
                    $("#messages-box li.sent:last").remove();
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", error);
                // Remove the message from UI if it failed
                $("#messages-box li.sent:last").remove();
            },
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            timeout: 30000
        });
    };

    Upload.prototype.progressHandling = function(event) {
        var percent = 0;
        var position = event.loaded || event.position;
        var total = event.total;
        if (event.lengthComputable) {
            percent = Math.ceil(position / total * 100);
        }
        // You can add progress bar here if needed
    };

    // Utility functions
    function isEmpty(str) {
        return !str || str.trim().length === 0;
    }

    function updateScroll() {
        if (is_at_bottom == 1) {
            var objDiv = document.getElementById("messages-box");
            objDiv.scrollTop = objDiv.scrollHeight;
        }
    }

    // Main chat functionality
    function chat_regular_messages() {
        var thid = $("#current_thid").val();
        var last_id = $("#last_id").val();

        if (!thid || !last_id) {
            return;
        }

        $.ajax({
            type: "POST",
            url: pt_livechat_ajax.ajaxurl,
            data: {
                action: 'updatemessages_regular',
                thid: thid,
                last_id: last_id,
                nonce: pt_livechat_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.messages) {
                    var messages = response.data.messages;
                    var avatarurl = $("#my-current-avatar").val();
                    var usernameofuser = $("#username-of-user").val();

                    messages.forEach(function(message) {
                        var messageContent = processMessageContentForDisplay(message.content);
                        var messageHtml = '';

                        if (message.initiator == getCurrentUserId()) {
                            messageHtml = '<li class="sent"><img src="' + avatarurl + '" width="30" height="30" alt=""> <p>' + messageContent + '</p></li>';
                        } else {
                            messageHtml = '<li class="replies"><img src="' + avatarurl + '" width="30" height="30" alt=""> <p>' + messageContent + '</p></li>';
                        }

                        $("#messages-box").append(messageHtml);
                        $("#last_id").val(message.id);
                    });

                    updateScroll();
                }
            },
            error: function(xhr, status, error) {
                console.error("Error updating messages:", error);
            }
        });
    }

    function getCurrentUserId() {
        // This should be set in the HTML or passed via wp_localize_script
        return window.currentUserId || 0;
    }

    function isScrolledToBottom(el) {
        return Math.abs(el.scrollHeight - el.scrollTop - el.clientHeight) < 1;
    }

    // Load contact list
    function loadContactList(searchQuery) {
        $.ajax({
            type: "POST",
            url: pt_livechat_ajax.ajaxurl,
            data: {
                action: 'get_chat_search',
                search_query: searchQuery || '',
                nonce: pt_livechat_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.threads) {
                    var threads = response.data.threads;
                    var contactsHtml = '';

                    if (threads.length === 0) {
                        contactsHtml = '<li><div class="wrap"><div class="meta"><p class="preview z1x2x3c4 padd10">' + 
                                     'No chats found.</p></div></div></li>';
                    } else {
                        threads.forEach(function(thread) {
                            var otherUserId = (thread.user1 == getCurrentUserId()) ? thread.user2 : thread.user1;
                            var lastMessage = thread.last_message || '';
                            
                            contactsHtml += '<li class="active" data-thid="' + thread.id + '" data-user="' + otherUserId + '">' +
                                           '<div class="wrap">' +
                                           '<div class="meta">' +
                                           '<p class="name">User ' + otherUserId + '</p>' +
                                           '<p class="preview">' + escapeHtml(lastMessage) + '</p>' +
                                           '</div></div></li>';
                        });
                    }

                    $('#contacts-ul').html(contactsHtml);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error loading contacts:", error);
            }
        });
    }

    // Send message function
    function send_regular_chat_message_fn() {
        var chatbox_textarea = $("#text_message_box").val();
        var thid = $("#current_thid").val();
        var to_user = $("#to_user").val();

        if (isEmpty(chatbox_textarea)) {
            return;
        }

        // Handle file uploads
        var files = document.getElementById("myfile");
        if (files && files.files.length > 0) {
            for (var i = 0; i < files.files.length; i++) {
                var upload = new Upload(files.files[i]);
                upload.doUpload();
            }
        } else {
            // Send text message only
            $.ajax({
                type: "POST",
                url: pt_livechat_ajax.ajaxurl,
                data: {
                    action: 'send_regular_chat_message',
                    chatbox_textarea: chatbox_textarea,
                    thid: thid,
                    to_user: to_user,
                    nonce: pt_livechat_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $("#text_message_box").val("");
                        $("#last_id").val(parseInt($("#last_id").val()) + 1);
                    } else {
                        console.error("Message send failed:", response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", error);
                }
            });
        }
    }

    // Event handlers
    $(document).ready(function() {
        // Initialize contact list
        loadContactList();

        // Set up message update interval
        messageUpdateInterval = setInterval(chat_regular_messages, 3000);

        // Handle contact clicks
        $(document).on('click', '#contacts-ul li', function() {
            var thid = $(this).data('thid');
            var userId = $(this).data('user');
            
            if (thid && userId) {
                window.location.href = pt_livechat_ajax.site_url + '/?thid=' + thid;
            }
        });

        // Handle search
        $('#searchbar_search').on('input', function() {
            var searchQuery = $(this).val();
            loadContactList(searchQuery);
        });

        // Handle message sending
        $('#send-button').on('click', function(e) {
            e.preventDefault();
            send_regular_chat_message_fn();
        });

        // Handle Enter key in textarea
        $('#text_message_box').on('keypress', function(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                send_regular_chat_message_fn();
            }
        });

        // Handle scroll events
        $('#messages-box').on('scroll', function() {
            is_at_bottom = isScrolledToBottom(this) ? 1 : 0;
        });

        // Handle file input changes
        $('#myfile').on('change', function() {
            var files = this.files;
            var fileNames = [];
            
            for (var i = 0; i < files.length; i++) {
                fileNames.push(files[i].name);
            }
            
            if (fileNames.length > 0) {
                $('.message-input-file').html('<span>Selected: ' + fileNames.join(', ') + '</span>');
            } else {
                $('.message-input-file').html('');
            }
        });
    });

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (messageUpdateInterval) {
            clearInterval(messageUpdateInterval);
        }
    });

})(jQuery);