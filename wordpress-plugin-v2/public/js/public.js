            var data = {
                action: 'ams_v2_delete_article',
                nonce: ams_v2_public.nonce,
                article_id: articleId
            };
            
            $.post(ams_v2_public.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        AMS_V2_Public.showAlert('success', '文章已删除');
                        
                        // 移除文章行或重新加载页面
                        $btn.closest('.ams-v2-article-item, tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        AMS_V2_Public.showAlert('error', response.data.message);
                    }
                })
                .fail(function() {
                    AMS_V2_Public.showAlert('error', ams_v2_public.strings.error);
                })
                .always(function() {
                    AMS_V2_Public.hideLoading();
                });
        },

        /**
         * 打开模态框
         */
        openModal: function(e) {
            e.preventDefault();
            
            var modalId = $(this).data('modal');
            var $modal = $('#' + modalId);
            
            if ($modal.length) {
                $modal.fadeIn(200);
                $('body').addClass('modal-open');
            }
        },

        /**
         * 关闭模态框
         */
        closeModal: function(e) {
            if (e && $(e.target).closest('.ams-v2-modal-content').length) {
                return;
            }
            
            $('.ams-v2-modal').fadeOut(200);
            $('body').removeClass('modal-open');
        },

        /**
         * 处理分页
         */
        handlePagination: function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var url = $link.attr('href');
            
            AMS_V2_Public.showLoading();
            
            // 使用AJAX加载新页面内容
            $.get(url)
                .done(function(data) {
                    var $newContent = $(data).find('.ams-v2-content');
                    $('.ams-v2-content').html($newContent.html());
                    
                    // 更新URL
                    history.pushState(null, null, url);
                })
                .fail(function() {
                    // 如果AJAX失败，直接跳转
                    window.location.href = url;
                })
                .always(function() {
                    AMS_V2_Public.hideLoading();
                });
        },

        /**
         * 处理搜索
         */
        handleSearch: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var query = $form.find('input[name="search"]').val();
            
            if (!query.trim()) {
                return;
            }
            
            AMS_V2_Public.showLoading();
            
            var data = {
                action: 'ams_v2_search_articles',
                nonce: ams_v2_public.nonce,
                query: query
            };
            
            $.post(ams_v2_public.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        $('.ams-v2-search-results').html(response.data.html);
                    } else {
                        AMS_V2_Public.showAlert('error', response.data.message);
                    }
                })
                .fail(function() {
                    AMS_V2_Public.showAlert('error', ams_v2_public.strings.error);
                })
                .always(function() {
                    AMS_V2_Public.hideLoading();
                });
        },

        /**
         * 处理文件上传
         */
        handleFileUpload: function(e) {
            var file = this.files[0];
            var $input = $(this);
            var maxSize = $input.data('max-size') || 10485760; // 10MB
            var allowedTypes = $input.data('allowed-types') || '';
            
            if (!file) return;
            
            // 检查文件大小
            if (file.size > maxSize) {
                AMS_V2_Public.showAlert('error', '文件大小超过限制');
                $input.val('');
                return;
            }
            
            // 检查文件类型
            if (allowedTypes) {
                var fileExt = file.name.split('.').pop().toLowerCase();
                var allowed = allowedTypes.split(',');
                
                if (allowed.indexOf(fileExt) === -1) {
                    AMS_V2_Public.showAlert('error', '不支持的文件类型');
                    $input.val('');
                    return;
                }
            }
            
            // 显示文件信息
            var $preview = $input.siblings('.file-preview');
            if ($preview.length) {
                $preview.html('<span class="file-name">' + file.name + '</span> <span class="file-size">(' + AMS_V2_Public.formatFileSize(file.size) + ')</span>');
            }
        },

        /**
         * 验证表单
         */
        validateForm: function($form) {
            var isValid = true;
            
            $form.find('.ams-v2-form-control[required]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (!value) {
                    AMS_V2_Public.showFieldError($field, '此字段为必填项');
                    isValid = false;
                } else {
                    AMS_V2_Public.clearFieldError($field);
                }
            });
            
            // 邮箱验证
            $form.find('input[type="email"]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (value && !AMS_V2_Public.isValidEmail(value)) {
                    AMS_V2_Public.showFieldError($field, '请输入有效的邮箱地址');
                    isValid = false;
                }
            });
            
            return isValid;
        },

        /**
         * 验证单个字段
         */
        validateField: function() {
            var $field = $(this);
            var value = $field.val().trim();
            
            // 必填验证
            if ($field.prop('required') && !value) {
                AMS_V2_Public.showFieldError($field, '此字段为必填项');
                return;
            }
            
            // 邮箱验证
            if ($field.attr('type') === 'email' && value && !AMS_V2_Public.isValidEmail(value)) {
                AMS_V2_Public.showFieldError($field, '请输入有效的邮箱地址');
                return;
            }
            
            AMS_V2_Public.clearFieldError($field);
        },

        /**
         * 显示字段错误
         */
        showFieldError: function($field, message) {
            $field.addClass('is-invalid');
            
            var $error = $field.siblings('.ams-v2-form-error');
            if ($error.length === 0) {
                $error = $('<div class="ams-v2-form-error"></div>');
                $field.after($error);
            }
            
            $error.text(message);
        },

        /**
         * 清除字段错误
         */
        clearFieldError: function($field) {
            $field.removeClass('is-invalid');
            $field.siblings('.ams-v2-form-error').remove();
        },

        /**
         * 显示加载状态
         */
        showLoading: function() {
            if ($('.ams-v2-loading-overlay').length === 0) {
                $('body').append('<div class="ams-v2-loading-overlay"><div class="ams-v2-loading"></div></div>');
            }
        },

        /**
         * 隐藏加载状态
         */
        hideLoading: function() {
            $('.ams-v2-loading-overlay').remove();
        },

        /**
         * 显示警告
         */
        showAlert: function(type, message) {
            var alertClass = 'ams-v2-alert ams-v2-alert-' + type;
            var alert = $('<div class="' + alertClass + '">' + message + '</div>');
            
            // 查找容器
            var $container = $('.ams-v2-alerts');
            if ($container.length === 0) {
                $container = $('<div class="ams-v2-alerts"></div>');
                $('.ams-v2-container').first().prepend($container);
            }
            
            $container.append(alert);
            
            // 自动隐藏
            setTimeout(function() {
                alert.fadeOut(function() {
                    alert.remove();
                });
            }, 5000);
        },

        /**
         * 工具函数：验证邮箱
         */
        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        /**
         * 工具函数：格式化文件大小
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * 工具函数：格式化日期
         */
        formatDate: function(date) {
            return new Date(date).toLocaleDateString();
        },

        /**
         * 工具函数：截断文本
         */
        truncateText: function(text, length) {
            if (text.length <= length) return text;
            return text.substring(0, length) + '...';
        }
    };

    /**
     * 文档就绪时初始化
     */
    $(document).ready(function() {
        AMS_V2_Public.init();
    });

    /**
     * 全局暴露对象
     */
    window.AMS_V2_Public = AMS_V2_Public;

})(jQuery);