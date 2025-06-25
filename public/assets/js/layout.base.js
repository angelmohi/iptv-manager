var CommonFunctions = {};

// Get iframe element
CommonFunctions.getIframeElement = function() {
    var iframeElement = document.getElementById('iframeElement');
    if (!iframeElement) {
        iframeElement = parent.document.getElementById('iframeElement');
        if (!iframeElement) {
            return;
        }
    }
    return iframeElement;
}

CommonFunctions.notification = function(type, message) {
    var iframeElement = CommonFunctions.getIframeElement();
    var notification = {
        type: type,
        showConfirmButton: false,
        timer: 5000,
        text: message
    };
    if (iframeElement) {
        parent.swal(notification);
    } else {
        swal(notification);
    }
};

CommonFunctions.validation = function($form, validationErrors) {
    function parseField(field) {
        var parts = field.split('.');
        if (parts.length == 1) {
            return parts[0];
        }
        var field = parts.shift();
        parts = parts.map(function(item) {
            return "[" + item + "]";
        });
        return field + parts.join('');
    }
    
    $.each(validationErrors, function(field, errors) {
        var fieldName = parseField(field);
        var $field = $form.find('[name="' + fieldName + '"]');
        if ($field.length > 1) {
            $field = $field.filter(':first');
        }
        else if ($field.length == 0) {
            $field = $form.find('[name="' + fieldName + '[]"]');
        }
        
        if ($field.length == 1) {
            var $target = $field;
            var $container = $field.parent();
            if ($container.hasClass('input-group')) {
                $target = $container;
                $container = $container.parent();
            }
            if ($target.hasClass('select2') || $target.hasClass('select2-hidden-accessible')) {
                $target = $target.next();
                $container = $target.parent();
            }

            if ($target.hasClass('form-check-input')) {
                var $fields = $form.find('[name="' + fieldName + '"]');
                $fields.addClass('is-invalid');
    
                $fields.each(function(index, element) {
                    $(element).addClass('is-invalid');
                    $(element).on('change', function() {
                        $fields.removeClass('is-invalid');
                        $error.html('');
                    });
                });
            }
            
            $target.addClass('is-invalid');
            
            var $error = $container.children('.invalid-feedback:first');
            if ($error.length == 0) {
                $error = $('<div class="invalid-feedback" />');
                $target.after($error);
            }
            $error.html(errors[0]);
            
            if (!$target.hasClass('was-invalid-before')) {
                $field.on('change', function() {
                    $target.removeClass('is-invalid');
                    $error.html('');
                });
                $target.addClass('was-invalid-before');
            }
        }
    });

    // Resize iframe to fit error messsages if needed
    CommonFunctions.resizeIframePopup();

    // Scroll to first error
    if ($('.is-invalid').length){
        $('html, body').animate({
            scrollTop: (($('.is-invalid').offset().top - 140))
        }, 200);
    }
};

CommonFunctions.notificationConfirmDelete = function(text, buttonText, url, params, callback) {
    swal({
        type: 'warning',
        title: 'Atención',
        text: text,
        allowEscapeKey: false,
        allowOutsideClick: false,
        allowEnterKey: false,
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: buttonText,
        cancelButtonText: 'Cancelar',
        showLoaderOnConfirm: true,
        preConfirm: function() {
            return new Promise(function(resolve) {
                $('.swal2-buttonswrapper > button:not(:first)').remove();
                                
                var $form = $('<form method="POST"><input type="hidden" name="_method" value="DELETE" /></form>');
                if (params) {
                    $.each(params, function(key, value) {
                        $input = $('<input type="hidden" />');
                        $input.attr('name', key);
                        $input.val(value);
                        $form.append($input);
                    });
                }
                $form.prop('action', url);
                $form.data('callback', callback);
                $form.appendTo('body');
                CommonFunctions.setupAjaxForm($form);
                $form.submit();
            });
        }
    });
};

CommonFunctions.notificationConfirmPut = function(text, buttonText, url, confirmButtonColor = '#d33', params, callback) {
    swal({
        type: 'warning',
        title: 'Atención',
        text: text,
        allowEscapeKey: false,
        allowOutsideClick: false,
        allowEnterKey: false,
        showCancelButton: true,
        confirmButtonColor: confirmButtonColor,
        confirmButtonText: buttonText,
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: function() {
            return new Promise(function(resolve) {
                $('.swal2-buttonswrapper > button:not(:first)').remove();
                                
                var $form = $('<form method="POST"><input type="hidden" name="_method" value="PUT" /></form>');
                if (params) {
                    $.each(params, function(key, value) {
                        $input = $('<input type="hidden" />');
                        $input.attr('name', key);
                        $input.val(value);
                        $form.append($input);
                    });
                }
                $form.prop('action', url);
                $form.data('callback', callback);
                $form.appendTo('body');
                CommonFunctions.setupAjaxForm($form);
                $form.submit();
            });
        }
    });
};

CommonFunctions.notificationConfirmPost = function(text, buttonText, url, confirmButtonColor = '#d33', params, callback) {
    swal({
        type: 'warning',
        title: 'Atención',
        text: text,
        allowEscapeKey: false,
        allowOutsideClick: false,
        allowEnterKey: false,
        showCancelButton: true,
        confirmButtonColor: confirmButtonColor,
        confirmButtonText: buttonText,
        cancelButtonText: 'Cancelar',
        showLoaderOnConfirm: true,
        preConfirm: function() {
            return new Promise(function(resolve) {
                $('.swal2-buttonswrapper > button:not(:first)').remove();
                                
                var $form = $('<form method="POST"><input type="hidden" name="_method" value="POST" /></form>');
                if (params) {
                    $.each(params, function(key, value) {
                        $input = $('<input type="hidden" />');
                        $input.attr('name', key);
                        $input.val(value);
                        $form.append($input);
                    });
                }
                $form.prop('action', url);
                $form.data('callback', callback);
                $form.appendTo('body');
                CommonFunctions.setupAjaxForm($form);
                $form.submit();
            });
        }
    });
};

CommonFunctions.notificationConfirm = function(text, buttonText, form) {
    swal({
        type: 'warning',
        title: 'Attention',
        text: text,
        allowEscapeKey: false,
        allowOutsideClick: false,
        allowEnterKey: false,
        showCancelButton: true,
        confirmButtonText: buttonText,
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: function() {
            return new Promise(function(resolve) {
                var $form = $(form);
                $form.submit();
                swal.close();
            });
        }
    });
};

CommonFunctions.notificationConfirmSearch = function(text, buttonText, buttonColor, callback) {
    var isValid = true; 
    swal({
        type: 'warning',
        title: 'Attention',
        text: text,
        allowEscapeKey: false,
        allowOutsideClick: false,
        allowEnterKey: false,
        showCancelButton: true,
        confirmButtonColor: buttonColor,
        confirmButtonText: buttonText,
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: function() {
            return new Promise(function(resolve, reject) {
                if (!isValid) {
                    reject();
                    return;
                }
                callback();
                resolve();
            });
        }
    });
};

CommonFunctions.setupAjaxForm = function(form, options) {
    options = $.extend({}, {
        notify: CommonFunctions.notification,
        validate: CommonFunctions.validation
    }, options);

    var $form = $(form);
    if ($form.data('callback')) {
        options['custom'] = typeof $form.data('callback') === "function" ?
            $form.data('callback') : window[$form.data('callback')];
    }
    if ($form.data('notify')) {
        options['notify'] = typeof $form.data('notify') === "function" ?
            $form.data('notify') : window[$form.data('notify')];
    }
    if ($form.data('validate')) {
        options['validate'] = typeof $form.data('validate') === "function" ?
            $form.data('validate') : window[$form.data('validate')];
    }
    if ($form.data('redirect')) {
        options['redirect'] = typeof $form.data('redirect') === "function" ?
            $form.data('redirect') : window[$form.data('redirect')];
    }
    if ($form.data('custom')) {
        options['custom'] = typeof $form.data('custom') === "function" ?
            $form.data('custom') : window[$form.data('custom')];
    }
    if ($form.data('before-submit')) {
        options['beforeSubmit'] = typeof $form.data('before-submit') === "function" ?
            $form.data('before-submit') : window[$form.data('before-submit')];
    }
    if ($form.data('before-serialize')) {
        options['beforeSerialize'] = typeof $form.data('before-serialize') === "function" ?
            $form.data('before-serialize') : window[$form.data('before-serialize')];
    }

    $form.ajaxForm(options);
}

// Set iframe height function
CommonFunctions.resizeIframePopup = function(fixedHeight = 0) {
    var iframeElement = CommonFunctions.getIframeElement();
    if (!iframeElement) {
        return;
    }

    var height = iframeElement.contentWindow.document.body.offsetHeight;
    
    // Set minimum height
    if (height == 0) {
        height = 435;
    }

    // Set fixed height if needed
    if (fixedHeight > 0) {
        height = fixedHeight;
    }

    iframeElement.style.height = height + 'px';
}

CommonFunctions.openIframePopup = function(src, classes = '', title = '') {
    // Remove previous modal
    $('#iframeModal').remove();
    
    $iframeModal = $(
        '<div class="modal fade" id="iframeModal" tabindex="-1" aria-hidden="true">' + 
            '<div class="modal-dialog modal-dialog-centered iframe-modal ' + classes + '">' + 
                '<div class="modal-content">' +
                    '<div class="modal-header">' +
                        '<h5 class="modal-title">' + title + '</h5>' +
                        '<button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>' +
                    '</div>' +
                    '<div class="modal-body" style="width: auto;">' +
                        '<iframe id="iframeElement" frameborder="0" width="100%" height="100%" src="' + src + '"></iframe>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>'
    );
    $iframeModal.appendTo('body');

    $('#iframeElement').on('load', function() {
        // Set height with a timeout because some browsers fire iframe onload event before css is applied
        setTimeout(function() {
            CommonFunctions.resizeIframePopup();
        }, 150);

        $iframeModal.modal('show');
    });
}

CommonFunctions.closeIframePopup = function() {
    var iframeElement = CommonFunctions.getIframeElement();
    if (!iframeElement) {
        return;
    }

    var $iframeElement = $(iframeElement);
    var $iframeModal = $iframeElement.closest('.modal');
    var $body = $iframeModal.closest('body');
    $body.removeClass('modal-open');
    $body.find('.modal-backdrop').remove();
    // Remove overflow hidden from body
    $body.css('overflow', 'auto');
    $iframeModal.remove();
}

// Generate files dropzone
CommonFunctions.generateFilesDropzone = function($dropZone, filePreviewUrl = "", fileDownloadUrl = "", files = [], multiple = true, value = "",
    section = ""
) {
    var multiple = multiple ? 'multiple' : '';
    var $fileInput = $('<input type="file" style="display: none;" ' + multiple + '>');
    var $filePreviews = $('<div class="row file-previews"></div>');
    $dropZone.after($fileInput);
    $dropZone.before($filePreviews);

    $dropZone.on('dragover', function(e) {
        e.preventDefault();
    });
    $dropZone.on('drop', function(e) {
        e.preventDefault();
        const files = e.originalEvent.dataTransfer.files;
        handleFiles(files);
    });
    $dropZone.on('click', function() {
        $fileInput.click();
    });

    // Generate random string identifier
    function generateIdentifier(length) {
        let result = '';
        const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        const charactersLength = characters.length;
        let counter = 0;
        while (counter < length) {
            result += characters.charAt(Math.floor(Math.random() * charactersLength));
            counter += 1;
        }
        return result;
    }

    // Generate the default preview box
    function generatePreviewBox(identifier, filename, progressbar = false) {
        var $filePreviewBox = $(
            '<div class="col-6 col-md-4 col-lg-3 mb-3 file-box" id="' + identifier + '"></div>'
        );

        if (progressbar) {
            $filePreviewBox.append(
                '<div class="progress mb-2">' +
                    '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"' +
                        'style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"' +
                    '>' +
                        'Processing...' +
                    '</div>' + 
                '</div>'
            );
        }

        $filePreviewBox.append(
            '<div class="file-preview">' +
                '<img class="img-fluid" src="' + defaultFileImgUrl + '" />' +
            '</div>' +
            '<div class="text-center file-details">' +
                '<p>' + filename + '</p>' +
            '</div>'
        );

        $filePreviews.append($filePreviewBox);
    }

    // Generate specific file preview by file type
    function generatePreview(identifier, filename, src = defaultFileImgUrl, value = "") {
        var fileType = filename.split('.').pop();
        fileType = fileType.toLowerCase();

        var $filePreviewBox = $('#' + identifier);
        var $filePreview = $filePreviewBox.find('.file-preview');

        if (value != "") {
            $filePreviews.append(
                '<input type="hidden" id="file' + identifier + '" name="files[' + identifier + ']" value="' + value + '" />'
            );
        } else {
            $filePreviews.append(
                '<input type="hidden" id="file' + identifier + '" name="files[' + identifier + ']" value="1" />'
            );
        }

        var previewHtml = '';
        var minHeight = 0;
        if (fileType.match(/(jpg|jpeg|png|gif)$/)) {
            $filePreview.html('');
            previewHtml = '<img class="img-fluid" src="' + src + '" />';
            $filePreview.append(previewHtml);
        } else if (fileType.match(/(pdf|eml|html|xlsx|xls)$/)) {
            $filePreview.html('');
            previewHtml = '<embed src="' + src + '" style="width:100%; height:100%;" frameborder="0" />';
            $filePreview.append(previewHtml);
        }

        $filePreview.append(
            '<div class="floating-buttons d-flex">' +
                '<a class="btn btn-primary btn-icon" href="' + fileDownloadUrl.replace(':id', identifier) +
                    '" target="_blank"' +
                '>' +
                    '<i class="fas fa-download"></i>' +
                '</a>' +
                '<button class="btn btn-secondary btn-icon remove-file">' +
                    '<i class="fas fa-times"></i>' +
                '</button>' +
            '</div>'
        );

        $filePreviewBox.find('.remove-file').on('click', function(e) {
            e.preventDefault();
            var $filePreviewBox = $(this).closest('.file-box');
            var identifier = $filePreviewBox.attr('id');
            $filePreviewBox.remove();
            $('#file' + identifier).val(0);
        });

        if (previewHtml != '') {
            $previewModal = $(
                '<div class="modal fade" id="previewModal' + identifier + '" tabindex="-1" aria-hidden="true">' + 
                    '<div class="modal-dialog modal-dialog-centered preview-modal modal-xl">' + 
                        '<div class="modal-content" style="min-height: ' + minHeight + 'px;">' +
                            '<div class="modal-header">' +
                                '<h5 class="modal-title"></h5>' +
                                '<button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>' +
                            '</div>' +
                            '<div class="modal-body" style="width: auto;">' +
                                previewHtml +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
            $filePreviewBox.append($previewModal);

            $filePreviewBox.find('.floating-buttons').prepend(
                // Temporarily commented to see how it looks without it. if it confirmed, remove the commented code and the modal
                /* '<button type="button" class="btn btn-primary btn-icon preview-file">' +
                    '<i class="fas fa-search"></i>' +
                '</button>' + */
                '<a class="btn btn-primary btn-icon" href="' + src + '" target="_blank">' +
                    '<i class="fas fa-search"></i>' +
                '</a>'
            );

            $filePreviewBox.find('.preview-file').on('click', function(e) {
                e.preventDefault();
                var $filePreviewBox = $(this).closest('.file-box');
                var identifier = $filePreviewBox.attr('id');
                $('#previewModal' + identifier).modal('show');
            });

            $filePreviewBox.find('embed').on('load', function() {
                var height = $('#' + identifier).closest('.card').height() - 200;
                var $modal = $('#previewModal' + identifier);
                $modal.find('.modal-content').css('min-height', height);
            });
        }
    }

    // Handle files to upload
    function handleFiles(files) {
        if (!multiple) {
            $filePreviews.empty();
        }
        for (const file of files) {
            var identifier = generateIdentifier(15);
            generatePreviewBox(identifier, file.name, true);
            var $filePreviewBox = $('#' + identifier);

            const formData = new FormData();
            formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
            formData.append('filename', file.name);
            formData.append('identifier', identifier);
            formData.append('section', section);

            // Function to create XMLHttpRequest for each file
            function createXHR(identifier) {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(e){
                    if (e.lengthComputable) {
                        var percentage = parseInt((e.loaded / e.total * 100), 10);
                        if (percentage < 100) {
                            $('#' + identifier + ' .progress-bar').css('width', percentage + "%");
                            $('#' + identifier + ' .progress-bar').html(percentage + "%");
                        } else {
                            $('#' + identifier + ' .progress-bar').css('width', "100%");
                            $('#' + identifier + ' .progress-bar').html("Processing...");
                        }

                    }
                }, false);
                return xhr;
            }

            // Send the file to the server to store it temporarily
            $.ajax({
                url: temporaryFilePresignUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(data) {
                    // Upload the file to S3 using the presigned URL
                    $.ajax({
                        url: data.presigned_url,
                        type: 'PUT',
                        headers: {
                            "Content-Type": file.type
                        },
                        data: file,
                        // Set timeout to 20 minutes
                        timeout: 1200000,
                        processData: false,
                        xhr: function() {
                            return createXHR(data.identifier); // Use the function to create XMLHttpRequest
                        },
                        success: function() {
                            var src = temporaryFilePreviewUrl.replace(':id', data.identifier);
                            generatePreview(data.identifier, file.name, src, value);
    
                            $filePreviewBox = $('#' + data.identifier);
                            $filePreviewBox.find('.file-preview').addClass('new-file');
                            $progressBar = $filePreviewBox.find('.progress-bar');
                            if ($progressBar.length > 0) {
                                $progressBar.removeClass('progress-bar-striped progress-bar-animated');
                                $progressBar.addClass('bg-success');
                                $progressBar.css('width', '100%');
                                $progressBar.html('Uploaded successfully');
                            }
                        },
                        error: function(e) {
                            console.log(e);
                            $filePreviewBox = $('#' + identifier);
                            $filePreviewBox.find('.file-preview').addClass('new-file');
                            $progressBar = $filePreviewBox.find('.progress-bar');
                            $progressBar.removeClass('progress-bar-striped progress-bar-animated');
                            $progressBar.addClass('bg-danger');
                            $progressBar.css('width', '100%');
                            $progressBar.html('Error uploading file');
                        },
                    });
                },
                error: function(e) {
                    console.log(e);
                    $filePreviewBox = $('#' + identifier);
                    $filePreviewBox.find('.file-preview').addClass('new-file');
                    $progressBar = $filePreviewBox.find('.progress-bar');
                    $progressBar.removeClass('progress-bar-striped progress-bar-animated');
                    $progressBar.addClass('bg-danger');
                    $progressBar.css('width', '100%');
                    $progressBar.html('Error processing file');
                },
            });
        }
    }

    // Handle file input change event to upload files
    $fileInput.on('change', function(event) {
        const files = event.target.files;
        handleFiles(files);
    });

    // Load existing files
    if (files != null && files.length > 0) {
        for (const file of files) {
            var identifier = file.id;
            generatePreviewBox(identifier, file.original_name);
            generatePreview(identifier, file.original_name, filePreviewUrl.replace(':id', identifier));
        }
    }
}

// Generate text autocomplete
CommonFunctions.generateTextAutocomplete = function($input, searchUrl, selectCallback = null) {
    $input.attr('autocomplete', 'off');
    $input.attr('placeholder', 'Autocomplete');
    var searchResults = $('<div class="search-results"></div>');
    $input.after(searchResults);
    var activeSearchRequest = null;

    function showSearchResults(query) {
        if (query.length >= 3) {
            // Abort previous request if still running
            if (activeSearchRequest && activeSearchRequest.readyState != 4) {
                activeSearchRequest.abort();
            }

            activeSearchRequest = $.ajax({
                url: searchUrl,
                method: 'GET',
                data: { q: query },
                success: function(data) {
                    var resultsList = '<div class="search-results-box">';

                    data.forEach(function(item) {
                        resultsList += '<div class="search-result" data-id="' + item.id + '">' + item.name + '</div>';
                    });

                    resultsList += '</div>';

                    searchResults.html(resultsList);
                    // On click on a search result set the input value to the result and hide the results
                    searchResults.on('click', '.search-result', function() {
                        var selectedItemText = $(this).text();
                        $input.val(selectedItemText);
                        searchResults.hide();
                    });

                    searchResults.show();
                }
            });
        } else {
            searchResults.empty();
            searchResults.hide();
        }
    }

    $input.on('input', function() {
        showSearchResults($(this).val());
    });

    $input.on('focus', function() {
        showSearchResults($(this).val());
    });

    $(document).on('click', function(event) {
        if (!$input.is(event.target) && $input.has(event.target).length === 0) {
            searchResults.hide();
        }
    });

    if (selectCallback !== null) {
        searchResults.on('click', '.search-result', function() {
            var selectedItemId = $(this).data('id');
            selectCallback(selectedItemId);
        });
    }
}

function isEmptyObject(value) {
    if (value == null || value == undefined) {
      // null or undefined
      return true;
    }
  
    if (typeof value !== 'object') {
      // boolean, number, string, function, etc.
      return false;
    }
  
    const proto = Object.getPrototypeOf(value);
    if (proto !== null && proto !== Object.prototype) {
      return false;
    }
  
    for (const prop in value) {
        if (Object.hasOwn(value, prop)) {
            return false;
        }
    }

    return true;
}
CommonFunctions.generateCalendar = function(container, feedUrl, options) {
    function eventClick(info) {
        var extendedProps = info.event.extendedProps;

        if (extendedProps && extendedProps.iframe_href != undefined) {
            var iframeHref = extendedProps.iframe_href;
            var iframeClasses = null;
            var iframeTitle = null;
            if (extendedProps.iframe_classes != undefined) {
                iframeClasses = extendedProps.iframe_classes;
            }
            if (extendedProps.iframe_title != undefined) {
                iframeTitle = extendedProps.iframe_title;
            }

            CommonFunctions.openIframePopup(iframeHref, iframeClasses, iframeTitle);
        }
    }
    if (isEmptyObject(options)) {
        var headerToolbar = {
            start: "title",
            center: "",
            end: "today timeGridWeek,timeGridDay prev,next",
        };
    } else {
        var headerToolbar = options.headerToolbar;
    }

    // Merge options with default
    options = $.extend({}, {
        navLinks: false,
        initialView: 'dayGridMonth',
        showNonCurrentDates: false,
        allDaySlot: false,
        editable: true,
        firstDay: 1,
        buttonText: {
            today: "Today",
            month: "Month",
        },

        headerToolbar: headerToolbar,
        views: {
            dayGridMonth: {
                titleFormat: { year: 'numeric', month: 'long' },
                dayHeaderFormat: { weekday: 'short', omitCommas: true },
            },
        },

        eventOrder: "start,-duration,allDay,title",
        eventOrderStrict: true,

        eventDrop: function(info) {
            // Prevent dragging of events with iframe href
            var extendedProps = info.event.extendedProps;
            if (!extendedProps || extendedProps.draggable == undefined) {
                info.revert();
                return;
            }

            var projectId = info.event.id;
            var date = moment(info.event.start).format('YYYY-MM-DD');

            swal({
                type: 'warning',
                title: 'Attention',
                text: "Are you sure about this change?",
                allowEscapeKey: false,
                allowOutsideClick: false,
                allowEnterKey: false,
                showCancelButton: true,
                confirmButtonText: 'Confirm',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                dangerMode: true,
                preConfirm: function() {
                    return new Promise(function(resolve) {
                        resolve();
                        $.ajax({
                            url: '/projects/' + projectId + '/update-vessel-eta',
                            method: 'PUT',
                            data: { 
                                vessel_eta: date,
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function() {
                                resolve();
                            },
                            error: function() {
                                location.reload();
                            }
                        });
                    });
                }
            }).then(function(result) {
                if (result.dismiss === 'cancel') {
                    info.revert(); // Revert the drag
                }
            });
        },

        height: "auto",
        events: feedUrl,
        eventDidMount: function(info) {
            info.el.setAttribute('title', info.event.title);
        },
        eventClick: eventClick,
    }, options);

    var calendar = new FullCalendar.Calendar(container, options);
    calendar.render();
    return calendar;
}

$(document).ready(function() {
    
    $('form:not([data-ajax=false])').each(function() {
        CommonFunctions.setupAjaxForm(this);
    });

    // Sidebar Toggle
    var currentUrl = window.location.href;
    $('#sidebar a.nav-link').each(function() {
        var $link = $(this);
        var href = $link.attr('href');
        if (!href.endsWith("/")) {
            href += '/';
        }
        if (currentUrl.startsWith(href)) {
            $link.addClass('active');
            var $parentGroup = $link.closest('.nav-group');
            if ($parentGroup.length > 0) {
                $parentGroup.addClass('show');
                $parentGroup.children('a').addClass('active');
            }
        }
    });

    // DataTables
    $('table.data_table, table.data-table').each(function() {
        var $table = $(this);
        
        var options = {
            order: [],
            pageLength: 10,
            bLengthChange: true,
            autoWidth: false,
        };

        // If table have class no-paginate then disable pagination
        if ($table.hasClass('no-paginate')) {
            options.bPaginate = false;
            options.bInfo = false;
        }

        // If table have class no-search then disable search
        if ($table.hasClass('no-search')) {
            options.bFilter = false;
        }

        // If table have class no-info then disable info
        if ($table.hasClass('no-info')) {
            options.bInfo = false;
        }
        
        if ($table.data('ajax-url')) {
            options.processing = true;
            options.serverSide = true;
            options.fixedHeader = true;
            options.ajax = {
                url: $table.data('ajax-url'),
                type: "POST",
                data: function(d) {
                    d._token = $('meta[name="csrf-token"]').attr('content');
                },
                error: function(xhr, status, error) {
                    var tableText = 'An error occurred. Please try again later.';
                    if (xhr.status == 422) {
                        var errors = xhr.responseJSON.validation;
                        if (errors) {
                            // Find the nearest form and display the error messages
                            var $form = $table.closest('body').find('form');
                            CommonFunctions.validation($form, errors);
                        }

                        tableText = 'No data available in table.';
                    }
                    
                    $table.find('tbody').empty();
                    $table.append('<tr class="odd"><td valign="top" class="dataTables_empty" colspan="' +
                        $table.find('thead th').length + '">' + tableText + '</td></tr>');
                    $('.dataTables_processing').remove();
                }
            };
            options.createdRow = function(row, data, dataIndex) {
                var $this = $(row);
                if ($this.data('href')) {
                    $(row).attr('data-href', $this.data('href'));
                }
                if ($this.data('target')) {
                    $(row).attr('data-target', $this.data('target'));
                }
                if ($this.data('data-iframe-href')) {
                    $(row).attr('data-iframe-href', $this.data('data-iframe-href'));
                }
                if ($this.data('data-iframe-classes')) {
                    $(row).attr('data-iframe-classes', $this.data('data-iframe-classes'));
                }
                if ($this.data('data-iframe-title')) {
                    $(row).attr('data-iframe-title', $this.data('data-iframe-title'));
                }
            };
        }
        
        $table.DataTable(options);

        // Column filters
        if (!($table.hasClass('no-search') || $table.hasClass('no-column-search'))) {
            var $thead = $table.children('thead');
            var $tr = $thead.children('tr');
            var $tfoot = $('<tfoot><tr></tr></tfoot>');
            $tr.children('th').each(function() {
                var $footTr = $tfoot.children('tr');
                var $th = $('<th></th>');
                if (!$table.hasClass('no-search') && !$(this).hasClass('no-search')) {
                    if ($(this).hasClass('project-status-filter') || $(this).hasClass('project-action-filter')) {
                        var $filter = $('<select class="form-select form-select-sm"><option value="">All</option></select>');
                        var statuses = [];
                        if ($(this).hasClass('project-status-filter')) {
                            statuses = ['New enquiry', 'Active', 'Completed', 'Order Lost', 'Enquiry Lost'];
                        } else if ($(this).hasClass('project-action-filter')) {
                            statuses = ['Pending Request Quote to Operator', 'Pending Quote from Operator', 'Pending Quote from Scamp',
                                'Pending Quote Confirmation from Customer', 'Pending To Send Order To The Operator',
                                'Pending Attendance Confirmation From Operator', 'Pending Attendance Confirmation To Customer',
                                'Pending Jobs Completion Confirmation', 'Pending Advice Job Completion To Customer',
                                'Pending Report from Operator', 'Pending Scamp Report', 'Pending Invoice from Operator',
                                'Pending Invoice Breakdown', 'Pending Invoice Review', 'Pending To Send Invoice To The Customer',
                                'Pending Project Completion', 'Outstanding Invoices', 'Pending Archiving'];
                        }
                        statuses.forEach(function(status) {
                            $filter.append('<option value="' + status + '">' + status + '</option>');
                        });
                        $th.append($filter);
        
                        $filter.change(function() {
                            $table.DataTable().column($th.index()).search($filter.val()).draw();
                        });
                    } else {
                        var $filter = $('<input type="text" class="form-control form-control-sm" placeholder="Search" />');
                        $th.append($filter);
        
                        var timer;
                        $filter.keyup(function() {
                            clearTimeout(timer);
                            timer = setTimeout(function() {
                                $table.DataTable().column($th.index()).search($filter.val()).draw();
                            }, 500);
                        });
                        $filter.click(function(e) {
                            e.stopPropagation();
                        });
                    }
                }
                $footTr.append($th);
            });
            $table.append($tfoot);
        }
    });

    // Select2
    $('.select2').each(function() {
        var $this = $(this);
        $this.select2({
            theme: "bootstrap-5",
        });

        // Auto focus on select2 input when dropdown is opened
        $this.on('select2:open', function() {
            $box = $('.select2-container--open');
            $input = $box.find('.select2-search__field');
            if ($input.length > 0) {
                // Select the last input field
                $input = $input[$input.length - 1];
                $input.focus();
            }
        });
    });

    // Tempus Dominus Datepicker
    $('.date-time-picker').each(function() {
        $this = $(this);

        var components = {
            decades: true,
            year: true,
            month: true,
            date: true,
            hours: true,
            minutes: true,
            seconds: false
        };
        var viewMode = 'calendar';
        var format = 'dd/MM/yyyy HH:mm';
        if ($this.data('type') == 'date') {
            components.hours = false;
            components.minutes = false;
            format = 'dd/MM/yyyy';
        }
        else if ($this.data('type') == 'time') {
            components.decades = false;
            components.year = false;
            components.month = false;
            components.date = false;
            viewMode = 'clock';
            format = 'HH:mm';
        }
        new tempusDominus.TempusDominus($this[0], {
            display: {
                icons: {
                    time: 'fas fa-clock',
                    date: 'fas fa-calendar',
                    up: 'fas fa-arrow-up',
                    down: 'fas fa-arrow-down',
                    previous: 'fas fa-chevron-left',
                    next: 'fas fa-chevron-right',
                    today: 'fas fa-calendar-check',
                    clear: 'fas fa-trash',
                    close: 'fas fa-times'
                },
                viewMode: viewMode,
                components: components,
                buttons: {
                    today: true,
                    clear: false,
                    close: false
                },
            },
            localization: {
                locale: 'gb',
                startOfTheWeek: 1,
                dayViewHeaderFormat: { month: 'long', year: 'numeric' },
                format: format,
            },
        });
    });

    // Save last tab visited in local storage and restore it on page load
    $('.nav-tabs a').on('shown.coreui.tab', function (e) {
        var selectedTabId = e.target.getAttribute('id');
        var $navTab = $(e.target).closest('.nav-tabs');
        if ($navTab.length > 0 && $navTab.attr('id')) {
            var navTabId = $navTab.attr('id');
            localStorage.setItem(navTabId, selectedTabId);
        }
    });
    var $navTabs = $('.nav-tabs');
    if ($navTabs.length > 0) {
        $navTabs.each(function() {
            var navTabId = $(this).attr('id');
            var lastSelectedTabId = localStorage.getItem(navTabId);
            if (lastSelectedTabId) {
                $('.nav-tabs a[id="' + lastSelectedTabId + '"]').tab('show');
            }
        });
    }

    function syncSwitchButtonClass($button) {
        $input = $button.children('input:first');
        if ($input.val() == 1) {
            $button.removeClass('off');
            $button.addClass('on');
        }
        else {
            $button.removeClass('on');
            $button.addClass('off');
        }
    }
    $('.switch-button').each(function() {
        syncSwitchButtonClass($(this));
    });
    $('.switch-button').click(function() {
        var $this = $(this);
        $input = $this.children('input:first');
        $input.val($input.val() == 1 ? 0 : 1);
        syncSwitchButtonClass($this);
    });

    $(document).on('click', '[data-href]', function(e) {
        var $this = $(this);
        if ($(e.target).is(".no-redirect") || $(e.target).closest("td:has(.no-redirect)").length) {
            return;
        }
        if ($this.data('target') == '_blank') {
            window.open($this.data('href'));
        }
        else {
            window.location = $this.data('href');
        }
    });
    $(document).on('click', '[data-iframe-href]', function(e) {
        var $this = $(this);
        if ($(e.target).is(".no-redirect") || $(e.target).closest("td.no-redirect").length) {
            return;
        }

        CommonFunctions.openIframePopup($this.data('iframe-href'), $this.data('iframe-classes'),
            $this.data('iframe-title'));
    });

    // Remove autocomplete from all inputs with calendar
    $('div.date-time-picker').each(function() {
        // Find the input element inside the date-time-picker div
        var $input = $(this).find('input');
        if ($input.length > 0) {
            $input.attr('autocomplete', 'off');
        }
    });
});
