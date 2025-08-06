// Ensure jQuery is loaded and use it in no-conflict mode
(function($) {
    var is_at_bottom = 0;
    var initialA = 0;

    // Utility function to escape HTML for display
    function escapeHtml(text) {
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
        let previewElementHtml = ''; // Will contain <img> or <i>
        let iconClass = 'fa fa-file'; // Default icon (FontAwesome)

        if (fileData.type.includes('image')) {
            previewElementHtml = `<img src="${escapeHtml(fileData.url)}" alt="${escapeHtml(fileData.name)}">`;
            iconClass = 'fa fa-file-image';
        } else if (fileData.type.includes('video')) {
            previewElementHtml = `<i class="fa fa-file-video"></i>`;
            iconClass = 'fa fa-file-video';
        } else if (fileData.type.includes('pdf')) {
            previewElementHtml = `<i class="fa fa-file-pdf"></i>`;
            iconClass = 'fa fa-file-pdf';
        } else if (fileData.type.includes('word') || fileData.name.toLowerCase().endsWith('.doc') || fileData.name.toLowerCase().endsWith('.docx')) {
            previewElementHtml = `<i class="fa fa-file-word"></i>`;
            iconClass = 'fa fa-file-word';
        } else if (fileData.type.includes('excel') || fileData.name.toLowerCase().endsWith('.xls') || fileData.name.toLowerCase().endsWith('.xlsx')) {
            previewElementHtml = `<i class="fa fa-file-excel"></i>`;
            iconClass = 'fa fa-file-excel';
        } else if (fileData.type.includes('powerpoint') || fileData.name.toLowerCase().endsWith('.ppt') || fileData.name.toLowerCase().endsWith('.pptx')) {
            previewElementHtml = `<i class="fa fa-file-powerpoint"></i>`;
            iconClass = 'fa fa-file-powerpoint';
        } else if (fileData.type.includes('zip') || fileData.type.includes('rar') || fileData.name.toLowerCase().endsWith('.zip') || fileData.name.toLowerCase().endsWith('.rar') || fileData.name.toLowerCase().endsWith('.7z')) {
            previewElementHtml = `<i class="fa fa-file-archive"></i>`;
            iconClass = 'fa fa-file-archive';
        } else if (fileData.type.includes('text') || fileData.name.toLowerCase().endsWith('.txt') || fileData.name.toLowerCase().endsWith('.csv') || fileData.name.toLowerCase().endsWith('.log')) {
            previewElementHtml = `<i class="fa fa-file-alt"></i>`;
            iconClass = 'fa fa-file-alt';
        } else if (fileData.type.includes('audio')) {
            previewElementHtml = `<i class="fa fa-file-audio"></i>`;
            iconClass = 'fa fa-file-audio';
        } else {
            previewElementHtml = `<i class="fa fa-file"></i>`;
        }

        return `<div class="chat-file-preview" data-file-url="${escapeHtml(fileData.url)}" data-file-type="${escapeHtml(fileData.type)}">
                    <a href="${escapeHtml(fileData.url)}" target="_blank" download="${escapeHtml(fileData.name)}">
                        ${previewElementHtml}
                        <span>${escapeHtml(fileData.name)}</span>
                    </a>
                </div>`;
    }


    /**
     * Processes raw message content from the database, extracting text and file data,
     * applying filters, escaping, and combining them for display.
     * This function is the single source of truth for rendering message content.
     * @param {string} rawContentFromDb The raw message content string, potentially containing embedded file JSON.
     * @returns {string} The HTML string ready for display in the message bubble.
     */
    function processMessageContentForDisplay(rawContentFromDb) {
        let textPart = rawContentFromDb;
        let filePreviewsHtml = '';
        let hasFiles = false;

        // --- Step 1: Extract and process JSON file attachment data ---
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
                
                fileDataArray.forEach(fileData => {
                    filePreviewsHtml += generateFilePreviewHtml(fileData);
                });
                hasFiles = true;
            } catch (e) {
                console.error("ERROR: Error parsing file attachment JSON:", e);
                filePreviewsHtml = " [Error displaying files] ";
                hasFiles = true;
            }
        }

        // --- Step 2: Process the text part (filtering and escaping) ---
        // New strategy: Perform filtering on plain text FIRST, then handle links.
        // This ensures filters don't mangle legitimate HTML links.

        // Store pre-formatted Zoom links temporarily to reinsert them later.
        const zoomLinks = [];
        // --- MODIFICATION START: Updated regex to handle all Zoom message variations ---
        const preformatted_zoom_pattern = /(A Zoom meeting (?:has been initiated! Join here:|is currently active\. Join Here:) <a href="https?:\/\/[^"]+zoom\.us[^"]+" target="_blank">Join Video Meeting<\/a>(?:\. This link will expire in .*?)?\.)/gi;
        // --- MODIFICATION END ---
        
        // Replace Zoom links with a placeholder before filtering sensitive info
        textPart = textPart.replace(preformatted_zoom_pattern, (match) => {
            const placeholder = `__ZOOM_LINK_PLACEHOLDER_${zoomLinks.length}__`;
            zoomLinks.push(match);
            return placeholder;
        });

        // Apply text-based filters
        const email_pattern = /[^@\s]*@[^@\s]*\.[^@\s]*/g;
        textPart = textPart.replace(email_pattern, "[removed]");

        const phone_pattern = /\+?[0-9][0-9()\-\s+]{4,20}[0-9]/g;
        textPart = textPart.replace(phone_pattern, "[blocked]");

        // Escape the remaining plain text content that is not a placeholder
        textPart = escapeHtml(textPart);

        // Reinsert the preserved Zoom links
        zoomLinks.forEach((link, index) => {
            textPart = textPart.replace(`__ZOOM_LINK_PLACEHOLDER_${index}__`, link);
        });

        // Process any remaining generic URLs (that were not pre-formatted)
        // Ensure this runs AFTER filtering and escaping, and that it doesn't re-process existing <a> tags.
        const url_pattern = /(https?:\/\/[^()\s]+)/gi;
        processedContent = textPart.replace(url_pattern, (match, url) => {
            // Only wrap if it's not already part of an HTML tag (e.g., href="...")
            // And ensure it's not part of the already re-inserted Zoom links
            if (!match.includes('href="') && !match.includes('src="') && !match.includes('data-file-url=') && !match.includes('zoom.us')) {
                return '<a href="' + url + '" target="_blank">' + url + '</a>';
            }
            return match;
        });
        textPart = processedContent; // Update textPart with processed content

        // --- Step 3: Combine text and file HTML, adding <br><br> if both exist ---
        let finalDisplayContent = '';
        const hasText = textPart.trim().length > 0; // Check if textPart has substantial content

        if (hasText) {
            finalDisplayContent += textPart;
        }

        if (hasFiles) {
            if (hasText) {
                finalDisplayContent += '<br><br>'; // Add line break if both text and files are present
            }
            finalDisplayContent += 'Attached Files: ' + filePreviewsHtml;
        }
        
        return finalDisplayContent;
    }


    function progressHandling(event) {
        var percent = 0;
        var position = event.loaded || event.position;
        var total = event.total;
        var progress_bar_id = "#progress-wrp";
        if (event.lengthComputable) {
            percent = Math.ceil(position / total * 100);
        }
        if ($(progress_bar_id).length) {
            $(progress_bar_id + " .progress-bar").css("width", +percent + "%");
            $(progress_bar_id + " .status").text(percent + "%");
        }
    }

    function isEmpty(str) {
        return (!str || 0 === str.length);
    }

    function updateScroll(){
        var messagesContainer = document.getElementById("messages");
        if (messagesContainer) {
            if(is_at_bottom == 1 || initialA == 0) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                initialA = 1;
            }
        }
    }

    window.chatPollingTimeout = null;

    function chat_regular_messages() {
        var thid = $('#thid').val();
        var last_id = $('#last_id').val();
        var text_message_box = $('#text_message_box').val();
        var otherpartyid = $('#otherpartyid').val();

        if(thid > 0) {
            $.ajax({
                url: pt_livechat_ajax.ajaxurl,
                data: {
                    action: 'updatemessages_regular',
                    thid: thid,
                    last_id: last_id,
                    otherpartymessage: text_message_box,
                    otherpartyid: otherpartyid
                },
                success: function(response) {
                    if (response.success) {
                        var obj = response.data;

                        $("#last_id").val(obj.last_id);
                        if (obj.content_messages) {
                            // Create a temporary div to parse the incoming HTML
                            // The PHP sends the whole <li>...</li> block
                            var tempDiv = $('<div>').html(obj.content_messages);
                            var messagesToAppend = [];

                            tempDiv.find('li').each(function() {
                                var $this = $(this);
                                var messageId = $this.data('message-id');

                                // Only append if message is new or has no ID
                                if (messageId && $('#messages-box li[data-message-id="' + messageId + '"]').length === 0) {
                                    // Get raw content from the innerHTML of <p> and process it
                                    const rawPContent = $this.find('p').html();
                                    if (rawPContent) {
                                        const processedPContent = processMessageContentForDisplay(rawPContent);
                                        $this.find('p').html(processedPContent);
                                    }
                                    messagesToAppend.push($this[0].outerHTML);
                                } else if (!messageId) {
                                    // Fallback for messages without ID, process their content as well
                                    const rawPContent = $this.find('p').html();
                                    if (rawPContent) {
                                        const processedPContent = processMessageContentForDisplay(rawPContent);
                                        $this.find('p').html(processedPContent);
                                    }
                                    messagesToAppend.push($this[0].outerHTML);
                                }
                            });

                            if (messagesToAppend.length > 0) {
                                $("#messages-box").append(messagesToAppend.join(''));
                                updateScroll();
                            }
                        }

                        var other_user_is_typing = obj.other_user_is_typing;
                        if(other_user_is_typing == "yes") {
                            $("#is_typing").show();
                        } else {
                            $("#is_typing").hide();
                        }

                    } else {
                        console.error("Error updating messages:", response.data.message);
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error (polling):", textStatus, errorThrown, jqXHR.responseText);
                },
                complete: function() {
                    window.chatPollingTimeout = setTimeout(chat_regular_messages, 1200);
                }
            });
        } else {
            window.chatPollingTimeout = setTimeout(chat_regular_messages, 1200);
        }
    }

    function isScrolledToBottom(el) {
        var $el = $(el);
        if ($el.length === 0) return false;
        return el.scrollHeight - $el.scrollTop() - $el.outerHeight() < 1;
    }

    function loadContactList(searchQuery = '') {
        const currentThid = $('#current_thid').val();

        $.get( pt_livechat_ajax.ajaxurl, { action: 'get_chat_search', get_chat_search: searchQuery, thid: currentThid }, function( response ) {
            if (response.success) {
                $('#contacts-ul').html(response.data.html);
            } else {
                console.error("Error loading contacts:", response.data.message);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error("AJAX Error (load contacts):", textStatus, errorThrown, jqXHR.responseText);
        });
    }


    $(function() {
        loadContactList();
        setInterval(loadContactList, 5000);

        $('#searchbar_search').on("keyup", function() {
            var valu = $(this).val();
            loadContactList(valu);
        });

        $(document).on('click', '#contacts-ul li.contact', function() {
            const newThid = $(this).data('thid');
            const newToUser = $(this).data('to-user');
            const currentActiveThid = $('#current_thid').val();

            if (newThid && newToUser && newThid != currentActiveThid) {
                $('#current_thid').val(newThid);
                $('#to_user').val(newToUser);
                $('#thid').val(newThid);

                $('#messages-box').empty();
                $('#last_id').val(0);
                
                const otherUserAvatar = $(this).find('img').attr('src');
                const otherUserName = $(this).find('.meta .name').text();
                $('.contact-profile img').attr('src', otherUserAvatar);
                $('.contact-profile p').text(otherUserName);

                $('#contacts-ul li.contact').removeClass('active');
                $(this).addClass('active');

                if (window.chatPollingTimeout) {
                    clearTimeout(window.chatPollingTimeout);
                }
                chat_regular_messages();

                loadContactList();
            }
        });


        $(document).on('click', '#openfile', function() {
            const myfileInput = document.getElementById("myfile");
            if (myfileInput) {
                myfileInput.click();
            } else {
                console.error("File input #myfile not found.");
                alert("File upload is not available. Please refresh the page.");
            }
        });


        $('#myfile').change(function(e){
            const files = e.target.files;
            const maxFiles = 3;
            const maxTotalSizeMB = 50;
            const maxTotalSizeBytes = maxTotalSizeMB * 1024 * 1024;

            if (files.length > maxFiles) {
                alert("You can only upload a maximum of " + maxFiles + " files at a time.");
                $(this).val('');
                $(".message-input-file").html("");
                return;
            }

            let totalSize = 0;
            let fileNames = [];
            for (let i = 0; i < files.length; i++) {
                totalSize += files[i].size;
            }

            if (totalSize > maxTotalSizeBytes) {
                alert("Total file size exceeds " + maxTotalSizeMB + " MB. Please select smaller files.");
                $(this).val('');
                $(".message-input-file").html("");
                return;
            }

            // --- IMPORTANT: Display file names in the input box ---
            for (let i = 0; i < files.length; i++) {
                fileNames.push(files[i].name);
            }
            $(".message-input-file").html(fileNames.join(', '));
            console.log("File(s) selected and validated. Ready to send.");
        });


        $("#send_chat_button").click(function (){
            var chatbox_textarea = $("#chatbox_textarea").val();
            var oid = $("#oid").val();
            var currend_id = $("#currend_id").val();
            var toid = $("#toid").val();

            if(!isEmpty(chatbox_textarea)) {
                $.post( pt_livechat_ajax.ajaxurl, {
                    action: 'send_order_chat_message',
                    chatbox_textarea: chatbox_textarea,
                    oid: oid,
                    current_user_id: currend_id,
                    toid: toid
                })
                .done(function( response ) {
                    if (response.success) {
                        console.log("Order message sent:", response.data);
                        $("#chatbox_textarea").val("");
                    } else {
                        console.error("Error sending order message:", response.data.message);
                        alert("Error: " + response.data.message);
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                        console.error("AJAX Error (order chat):", textStatus, errorThrown, jqXHR.responseText);
                        alert("Network error sending order message. Please check console for details.");
                    });
            } else {
                alert(pt_livechat_ajax.MESSAGE_EMPTY_STRING);
            }
        });

        $("#send_me_a_message").click(function (){
            console.log("Send button clicked or Enter pressed.");
            send_regular_chat_message_fn();
        });

        $('#text_message_box').keypress(function (e) {
            if (e.which == 13) {
                e.preventDefault();
                console.log("Enter key pressed.");
                send_regular_chat_message_fn();
            }
        });

        if ($('#thid').val() > 0) {
            chat_regular_messages();
        }

        // --- NEW: Process initially loaded messages on document ready ---
        // This processes ALL <p> tags in messages-box, regardless of how they were loaded.
        // It ensures consistency for optimistic updates and initial page loads.
        $(document).ready(function() {
            if(document.getElementById("messages") !== null) {
                $('#messages').scrollTop($('#messages')[0].scrollHeight);
                console.log("Initial scroll to:", $('#messages')[0].scrollHeight);
            }

            $('#messages-box li p').each(function() {
                const rawContent = $(this).html(); // Get the innerHTML directly
                if (rawContent) {
                    const processedContent = processMessageContentForDisplay(rawContent);
                    $(this).html(processedContent);
                }
            });
        });


        $(document).on('click', '.chat-file-preview', function(e) {
            e.preventDefault();
            const fileUrl = $(this).data('file-url');
            const fileType = $(this).data('file-type');
            const fileName = $(this).find('span').text();

            if (!fileUrl) {
                console.error("File URL not found for preview.");
                return;
            }

            if (fileType.includes('image') || fileType.includes('video')) {
                const $modal = $('<div class="chat-media-modal"></div>');
                const $modalContent = $('<div class="chat-media-modal-content"></div>');
                
                let mediaElement;
                if (fileType.includes('image')) {
                    mediaElement = $(`<img src="${fileUrl}" alt="${fileName}" class="chat-modal-media">`);
                } else if (fileType.includes('video')) {
                    mediaElement = $(`<video controls autoplay src="${fileUrl}" class="chat-modal-media"></video>`);
                }

                const $closeButton = $('<span class="chat-modal-close">&times;</span>');
                const $downloadButton = $(`<a href="${fileUrl}" download="${fileName}" class="chat-modal-download-btn"><i class="fa fa-download"></i> Download</a>`);

                $modalContent.append($closeButton, mediaElement, $downloadButton);
                $modal.append($modalContent);
                $('body').append($modal);

                $closeButton.on('click', function() { $modal.remove(); });
                $modal.on('click', function(event) {
                    if ($(event.target).is($modal)) {
                        $modal.remove();
                    }
                });
            } else {
                const link = document.createElement('a');
                link.href = fileUrl;
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        });
    });

    function send_regular_chat_message_fn() {
        console.log("send_regular_chat_message_fn called.");
        var fileInput = document.getElementById("myfile");
        var files = fileInput ? fileInput.files : [];
        var text_message_box_val = $("#text_message_box").val();

        console.log("Text message value:", text_message_box_val);
        console.log("Files selected:", files.length);

        if (isEmpty(text_message_box_val) && files.length === 0) {
             alert(pt_livechat_ajax.MESSAGE_EMPTY_STRING);
             console.log("Message is empty and no files selected. Returning.");
             return;
        }

        const maxFiles = 3;
        const maxTotalSizeMB = 50;
        const maxTotalSizeBytes = maxTotalSizeMB * 1024 * 1024;

        if (files.length > maxFiles) {
            alert("You can only upload a maximum of " + maxFiles + " files at a time.");
            $(fileInput).val('');
            $(".message-input-file").html("");
            console.log("Too many files. Returning.");
            return;
        }

        let totalSize = 0;
        for (let i = 0; i < files.length; i++) {
            totalSize += files[i].size;
        }

        if (totalSize > maxTotalSizeBytes) {
            alert("Total file size exceeds " + maxTotalSizeMB + " MB. Please select smaller files.");
            $(fileInput).val('');
            $(".message-input-file").html("");
            return;
        }

        var formData = new FormData();
        formData.append("action", "send_regular_chat_message");
        formData.append("nonce", pt_livechat_ajax.nonce);
        formData.append("chatbox_textarea", text_message_box_val);
        formData.append("thid", $("#current_thid").val());
        formData.append("to_user", $("#to_user").val());

        let fileDataForOptimisticRender = [];
        for (let i = 0; i < files.length; i++) {
            formData.append("file[]", files[i]);
            // For optimistic rendering, create temporary URLs for local files
            const tempUrl = URL.createObjectURL(files[i]);
            fileDataForOptimisticRender.push({
                url: tempUrl,
                name: files[i].name,
                type: files[i].type
            });
        }
        console.log("FormData prepared. Files appended:", files.length);


        var avatarurl = $("#my-current-avatar").val();
        
        // Construct the raw content string that mimics what PHP will save
        let raw_optimistic_content_for_processing = text_message_box_val;
        if (fileDataForOptimisticRender.length > 0) {
            raw_optimistic_content_for_processing += '__FILE_ATTACHMENT_JSON_START__' + JSON.stringify(fileDataForOptimisticRender) + '__FILE_ATTACHMENT_JSON_END__';
        }

        // Process this raw content using the same function that processes received messages
        var processed_optimistic_content = processMessageContentForDisplay(raw_optimistic_content_for_processing);


        var tempMessageId = 'temp-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        var app = '<li class="sent" data-message-id="' + tempMessageId + '"><img src="'+ escapeHtml(avatarurl) +'" width="30" height="30" alt=""> <p>' + processed_optimistic_content + '</p></li>';
        var $optimisticMessageElement = $(app);
        $("#messages-box").append($optimisticMessageElement);
        updateScroll();

        $("#text_message_box").val("");
        $(".message-input-file").html(""); // Clear file name display after sending
        $(fileInput).val(''); // Clear file input after sending


        console.log("Sending AJAX request...");
        $.ajax({
            type: "POST",
            url: pt_livechat_ajax.ajaxurl,
            xhr: function () {
                var myXhr = $.ajaxSettings.xhr();
                if (myXhr.upload) {
                    myXhr.upload.addEventListener('progress', progressHandling, false);
                }
                return myXhr;
            },
            success: function (response) {
                console.log("AJAX Success response:", response);
                if (response.success) {
                    console.log("Message sent successfully:", response.data);
                    if (response.data && response.data.new_last_id) {
                        $("#last_id").val(response.data.new_last_id);
                        if ($optimisticMessageElement) {
                            $optimisticMessageElement.attr('data-message-id', response.data.new_last_id);
                        }
                    }
                } else {
                    console.error("Error sending message:", response.data.message);
                    alert("Error: " + response.data.message);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                alert("Network error or server error occurred while sending message. Please check console for details.");
            },
            async: true,
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            timeout: 60000
        });
    }

})(jQuery);
