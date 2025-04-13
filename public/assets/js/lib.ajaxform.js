(function($) {
    
    function AjaxForm($form, options) {
        var defaultOptions = {
            notify: null,
            validate: null,
            redirect: null,
            custom: null,
            stopMultiple: true,
            includeCsrfToken: true,
            disableButtons: true,
            loadingButton: true,
            beforeInit: null,
            beforeSerialize: null,
            beforeSubmit: null,
            onCancel: null,
            onSuccess: null,
            onError: null,
            onComplete: null
        };
        
        this.get = function(option) {
            return $form.data('ajaxForm')[option];
        }
        this.set = function(option, value) {
            $form.data('ajaxForm')[option] = value;
        }
        
        function processResponse(response, statusCode) {
            if (response.validation) {
                $form.data('ajaxForm').validate($form, response.validation);
            }
            else if (response.alert) {
                $form.data('ajaxForm').notify(response.alert.type, response.alert.message);
                if (response.alert.redirection !== null) {
                    setTimeout(() => {
                        window.location = response.alert.redirection || window.location.href;
                    }, 1700);
                }
            }
            else if (response.redirection) {
                window.location = response.redirection.url;
            }
            else if (response.iframeRedirection) {
                // If the iframeRedirection is empty, then we just reload the page
                if (response.iframeRedirection.url === '') {
                    parent.window.location.reload();
                } else {
                    parent.window.location = response.iframeRedirection.url;
                }
            }
            else if (response.custom) {
                $form.data('ajaxForm').custom(response.custom);
            }
            else if (response.message) {
                var type = statusCode >= 500 ? 'error' : 'warning';
                $form.data('ajaxForm').notify(type, response.message);
            }
        }

        function started() {
            if ($form.data('ajaxForm').disableButtons) {
                $form.find('button, input[type=submit], input[type=button]').each(function() {
                    var $this = $(this);
                    $this.data('was-disabled', $this.prop('disabled'));
                    $this.prop('disabled', true);
                });
            }
            
            if ($form.data('ajaxForm').loadingButton) {
                var $loadingButton = $form.data('ajaxForm').lastButtonClicked;
                if ($loadingButton && !$form.data('ajaxForm').currentLoadingButton &&
                        $loadingButton.data('loading-text')) {
                    if ($loadingButton.prop("tagName") == 'INPUT') {
                        $loadingButton.data('default-text', $loadingButton.val());
                        $loadingButton.val($loadingButton.data('loading-text'));
                    }
                    else {
                        $loadingButton.data('default-text', $loadingButton.html());
                        $loadingButton.html($loadingButton.data('loading-text'));
                    }
                    $form.data('ajaxForm').currentLoadingButton = $loadingButton;
                }
            }
            
            $form.data('ajaxForm').processing = true;
        }
        function finished(completed) {
            if ($form.data('ajaxForm').currentLoadingButton) {
                if ($form.data('ajaxForm').currentLoadingButton.prop("tagName") == 'INPUT') {
                    $form.data('ajaxForm').currentLoadingButton.val(
                        $form.data('ajaxForm').currentLoadingButton.data('default-text')
                    );
                }
                else {
                    $form.data('ajaxForm').currentLoadingButton.html(
                        $form.data('ajaxForm').currentLoadingButton.data('default-text')
                    );
                }
                $form.data('ajaxForm').currentLoadingButton = null;
            }
            
            if ($form.data('ajaxForm').disableButtons) {
                $form.find('button, input[type=submit], input[type=button]').each(function() {
                    var $this = $(this);
                    $this.prop('disabled', $this.data('was-disabled'))
                });
            }
            
            $form.data('ajaxForm').processing = false;
            $form.data('ajaxForm').lastButtonClicked = null;
            
            if (completed && $form.data('ajaxForm').onComplete) {
                $form.data('ajaxForm').onComplete();
            }
            else if (!completed && $form.data('ajaxForm').onCancel) {
                $form.data('ajaxForm').onCancel();
            }
        }
        
        if (!$form.data('ajaxForm')) {
            $form.data('ajaxForm', $.extend({}, defaultOptions, options));
            
            $form.on('click', 'button, input[type=submit]', function() {
                $form.data('ajaxForm').lastButtonClicked = $(this);
            });
            
            $form.on('submit', function(e) {
                var submit = function() {
                    $form.ajaxSubmit({
                        dataType: 'json',
                        beforeSerialize: function($formElement, options) {
                            if ($form.data('ajaxForm').beforeSerialize) {
                                var proceed = $form.data('ajaxForm').beforeSerialize($formElement, options);
                                if (proceed === false) {
                                    finished(false);
                                    return false;
                                }
                            }
                        },
                        beforeSubmit: function(formData, $formElement, options) {
                            if ($form.data('ajaxForm').includeCsrfToken) {
                                var $csrf = $('meta[name="csrf-token"]');
                                if ($csrf.length > 0) {
                                    formData.push({ name: '_token', value: $csrf.attr('content') });
                                }
                            }
                            if ($form.data('ajaxForm').lastButtonClicked) {
                                var fieldName = $form.data('ajaxForm').lastButtonClicked.attr('name');
                                if (fieldName) {
                                    formData.push({
                                        name: fieldName,
                                        value: $form.data('ajaxForm').lastButtonClicked.val()
                                    });
                                }
                            }
                            
                            if ($form.data('ajaxForm').beforeSubmit) {
                                var proceed = $form.data('ajaxForm').beforeSubmit(formData, $formElement, options);
                                if (proceed === false) {
                                    finished(false);
                                    return false;
                                }
                            }
                        },
                        success: function(response, statusText, xhr, $formElement) {
                            if ($form.data('ajaxForm').onSuccess) {
                                var proceed = $form.data('ajaxForm').onSuccess(response, statusText, xhr, $formElement);
                                if (proceed === false) {
                                    finished(false);
                                    return false;
                                }
                            }
                            
                            processResponse(response, xhr.status);
                            finished(true);
                        },
                        error: function(xhr, statusText, errorThrown) {
                            if ($form.data('ajaxForm').onError) {
                                var proceed = $form.data('ajaxForm').onError(xhr, statusText, errorThrown);
                                if (proceed === false) {
                                    finished(false);
                                    return false;
                                }
                            }
                            
                            var response = null;
                            try {
                                response = JSON.parse(xhr.responseText);
                            } catch(e) {
                                $form.data('ajaxForm').notify('error', 'An unknown error occurred');
                            }
                            
                            if (response) {
                                processResponse(response, xhr.status);
                            }
                            finished(true);
                        }
                    });
                }
                
                if ($form.data('ajaxForm').stopMultiple && $form.data('ajaxForm').processing) {
                    return false;
                }

                started();
                
                if ($form.data('ajaxForm').beforeInit) {
                    $form.data('ajaxForm').beforeInit().then(
                        function() {
                            submit();
                        },
                        function() {
                            finished(false);
                        }
                    );
                }
                else {
                    submit();
                }
                
                e.preventDefault();
                return false;
			});
        }
    }
    
    $.ajaxForm = function(selector, options) {
        return new AjaxForm($(selector), options);
    };
    
    $.fn.ajaxForm = function(options) {
        return this.each(function() {
            $.ajaxForm(this, options);
        });
    };
    
})(jQuery);
