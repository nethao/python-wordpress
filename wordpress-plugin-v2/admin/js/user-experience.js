/**
 * 用户体验优化JavaScript
 * 
 * 提供二次确认对话框、错误信息显示和操作指引功能
 */

(function($) {
    'use strict';
    
    // 用户体验管理器
    window.AMS_V2_UX = {
        
        /**
         * 初始化用户体验功能
         */
        init: function() {
            this.initConfirmDialogs();
            this.initErrorHandling();
            this.initTooltips();
            this.initProgressIndicators();
            this.initNavigationHelp();
        },
        
        /**
         * 初始化确认对话框
         */
        initConfirmDialogs: function() {
            // 危险操作确认
            $(document).on('click', '.ams-confirm-action', function(e) {
                e.preventDefault();
                
                var $this = $(this);
                var message = $this.data('confirm-message') || '确定要执行此操作吗？';
                var title = $this.data('confirm-title') || '确认操作';
                var action = $this.data('action') || 'continue';
                
                AMS_V2_UX.showConfirmDialog(title, message, function() {
                    // 用户确认后执行原始操作
                    if ($this.is('form')) {
                        $this.off('click').submit();
                    } else if ($this.attr('href')) {
                        window.location.href = $this.attr('href');
                    } else if ($this.data('callback')) {
                        window[$this.data('callback')]();
                    }
                });
            });
            
            // 批量操作确认
            $(document).on('click', '.ams-bulk-action', function(e) {
                e.preventDefault();
                
                var selectedItems = $('.ams-bulk-checkbox:checked').length;
                if (selectedItems === 0) {
                    AMS_V2_UX.showError('请先选择要操作的项目');
                    return;
                }
                
                var action = $(this).data('action');
                var message = '确定要对 ' + selectedItems + ' 个项目执行 "' + action + '" 操作吗？';
                
                AMS_V2_UX.showConfirmDialog('批量操作确认', message, function() {
                    // 执行批量操作
                    AMS_V2_UX.executeBulkAction(action);
                });
            });
        },
        
        /**
         * 显示确认对话框
         */
        showConfirmDialog: function(title, message, onConfirm, onCancel) {
            var dialogHtml = `
                <div id="ams-confirm-dialog" class="ams-modal">
                    <div class="ams-modal-content">
                        <div class="ams-modal-header">
                            <h3>${title}</h3>
                            <span class="ams-modal-close">&times;</span>
                        </div>
                        <div class="ams-modal-body">
                            <p>${message}</p>
                        </div>
                        <div class="ams-modal-footer">
                            <button type="button" class="button button-secondary ams-modal-cancel">取消</button>
                            <button type="button" class="button button-primary ams-modal-confirm">确认</button>
                        </div>
                    </div>
                </div>
            `;
            
            // 移除现有对话框
            $('#ams-confirm-dialog').remove();
            
            // 添加新对话框
            $('body').append(dialogHtml);
            
            var $dialog = $('#ams-confirm-dialog');
            $dialog.show();
            
            // 绑定事件
            $dialog.find('.ams-modal-confirm').on('click', function() {
                $dialog.hide().remove();
                if (onConfirm) onConfirm();
            });
            
            $dialog.find('.ams-modal-cancel, .ams-modal-close').on('click', function() {
                $dialog.hide().remove();
                if (onCancel) onCancel();
            });
            
            // 点击背景关闭
            $dialog.on('click', function(e) {
                if (e.target === this) {
                    $dialog.hide().remove();
                    if (onCancel) onCancel();
                }
            });
        },
        
        /**
         * 初始化错误处理
         */
        initErrorHandling: function() {
            // 全局AJAX错误处理
            $(document).ajaxError(function(event, xhr, settings, error) {
                var message = '操作失败';
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.status === 403) {
                    message = '权限不足，请联系管理员';
                } else if (xhr.status === 404) {
                    message = '请求的资源不存在';
                } else if (xhr.status === 500) {
                    message = '服务器内部错误，请稍后重试';
                } else if (xhr.status === 0) {
                    message = '网络连接失败，请检查网络设置';
                }
                
                AMS_V2_UX.showError(message);
            });
            
            // 表单验证错误处理
            $(document).on('submit', 'form.ams-form', function(e) {
                var $form = $(this);
                var isValid = true;
                
                // 清除之前的错误
                $form.find('.ams-error').removeClass('ams-error');
                $form.find('.ams-error-message').remove();
                
                // 验证必填字段
                $form.find('[required]').each(function() {
                    var $field = $(this);
                    if (!$field.val().trim()) {
                        isValid = false;
                        AMS_V2_UX.showFieldError($field, '此字段为必填项');
                    }
                });
                
                // 验证邮箱格式
                $form.find('input[type="email"]').each(function() {
                    var $field = $(this);
                    var email = $field.val().trim();
                    if (email && !AMS_V2_UX.isValidEmail(email)) {
                        isValid = false;
                        AMS_V2_UX.showFieldError($field, '请输入有效的邮箱地址');
                    }
                });
                
                // 验证URL格式
                $form.find('input[type="url"]').each(function() {
                    var $field = $(this);
                    var url = $field.val().trim();
                    if (url && !AMS_V2_UX.isValidUrl(url)) {
                        isValid = false;
                        AMS_V2_UX.showFieldError($field, '请输入有效的URL地址');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    AMS_V2_UX.showError('请修正表单中的错误后重试');
                }
            });
        },
        
        /**
         * 显示字段错误
         */
        showFieldError: function($field, message) {
            $field.addClass('ams-error');
            $field.after('<div class="ams-error-message">' + message + '</div>');
        },
        
        /**
         * 显示成功消息
         */
        showSuccess: function(message) {
            this.showNotification(message, 'success');
        },
        
        /**
         * 显示错误消息
         */
        showError: function(message) {
            this.showNotification(message, 'error');
        },
        
        /**
         * 显示警告消息
         */
        showWarning: function(message) {
            this.showNotification(message, 'warning');
        },
        
        /**
         * 显示通知消息
         */
        showNotification: function(message, type) {
            type = type || 'info';
            
            var notificationHtml = `
                <div class="ams-notification ams-notification-${type}">
                    <span class="ams-notification-message">${message}</span>
                    <span class="ams-notification-close">&times;</span>
                </div>
            `;
            
            var $notification = $(notificationHtml);
            $('#ams-notifications').append($notification);
            
            // 自动消失
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $notification.remove();
                });
            }, 5000);
            
            // 手动关闭
            $notification.find('.ams-notification-close').on('click', function() {
                $notification.fadeOut(function() {
                    $notification.remove();
                });
            });
        },
        
        /**
         * 初始化工具提示
         */
        initTooltips: function() {
            // 为带有data-tooltip属性的元素添加工具提示
            $(document).on('mouseenter', '[data-tooltip]', function() {
                var $this = $(this);
                var message = $this.data('tooltip');
                
                if (!message) return;
                
                var tooltipHtml = '<div class="ams-tooltip">' + message + '</div>';
                var $tooltip = $(tooltipHtml);
                
                $('body').append($tooltip);
                
                var offset = $this.offset();
                $tooltip.css({
                    top: offset.top - $tooltip.outerHeight() - 5,
                    left: offset.left + ($this.outerWidth() - $tooltip.outerWidth()) / 2
                });
                
                $this.data('tooltip-element', $tooltip);
            });
            
            $(document).on('mouseleave', '[data-tooltip]', function() {
                var $tooltip = $(this).data('tooltip-element');
                if ($tooltip) {
                    $tooltip.remove();
                    $(this).removeData('tooltip-element');
                }
            });
        },
        
        /**
         * 初始化进度指示器
         */
        initProgressIndicators: function() {
            // 为长时间操作显示进度指示器
            $(document).on('click', '.ams-long-operation', function() {
                AMS_V2_UX.showProgress('正在处理，请稍候...');
            });
            
            // AJAX请求时显示加载指示器
            $(document).ajaxStart(function() {
                AMS_V2_UX.showLoading();
            }).ajaxStop(function() {
                AMS_V2_UX.hideLoading();
            });
        },
        
        /**
         * 显示进度指示器
         */
        showProgress: function(message) {
            var progressHtml = `
                <div id="ams-progress" class="ams-modal">
                    <div class="ams-modal-content ams-progress-content">
                        <div class="ams-progress-spinner"></div>
                        <p>${message}</p>
                    </div>
                </div>
            `;
            
            $('#ams-progress').remove();
            $('body').append(progressHtml);
            $('#ams-progress').show();
        },
        
        /**
         * 隐藏进度指示器
         */
        hideProgress: function() {
            $('#ams-progress').hide().remove();
        },
        
        /**
         * 显示加载指示器
         */
        showLoading: function() {
            if ($('#ams-loading').length === 0) {
                $('body').append('<div id="ams-loading" class="ams-loading-overlay"><div class="ams-loading-spinner"></div></div>');
            }
            $('#ams-loading').show();
        },
        
        /**
         * 隐藏加载指示器
         */
        hideLoading: function() {
            $('#ams-loading').hide();
        },
        
        /**
         * 初始化导航帮助
         */
        initNavigationHelp: function() {
            // 添加帮助按钮
            $('.ams-page-header').each(function() {
                var $header = $(this);
                if (!$header.find('.ams-help-button').length) {
                    $header.append('<button type="button" class="button ams-help-button" data-tooltip="查看页面帮助">?</button>');
                }
            });
            
            // 帮助按钮点击事件
            $(document).on('click', '.ams-help-button', function() {
                var page = $(this).closest('.wrap').attr('id') || 'general';
                AMS_V2_UX.showHelp(page);
            });
            
            // 添加面包屑导航
            this.addBreadcrumb();
        },
        
        /**
         * 显示帮助信息
         */
        showHelp: function(page) {
            var helpContent = this.getHelpContent(page);
            
            var helpHtml = `
                <div id="ams-help-dialog" class="ams-modal">
                    <div class="ams-modal-content ams-help-content">
                        <div class="ams-modal-header">
                            <h3>页面帮助</h3>
                            <span class="ams-modal-close">&times;</span>
                        </div>
                        <div class="ams-modal-body">
                            ${helpContent}
                        </div>
                        <div class="ams-modal-footer">
                            <button type="button" class="button button-primary ams-modal-close">关闭</button>
                        </div>
                    </div>
                </div>
            `;
            
            $('#ams-help-dialog').remove();
            $('body').append(helpHtml);
            $('#ams-help-dialog').show();
            
            // 关闭事件
            $('#ams-help-dialog').find('.ams-modal-close').on('click', function() {
                $('#ams-help-dialog').hide().remove();
            });
        },
        
        /**
         * 获取帮助内容
         */
        getHelpContent: function(page) {
            var helpTexts = {
                'dashboard': `
                    <h4>仪表板使用说明</h4>
                    <ul>
                        <li>查看系统概览和关键统计信息</li>
                        <li>快速访问常用功能</li>
                        <li>查看最近的操作记录</li>
                    </ul>
                `,
                'articles': `
                    <h4>文章管理使用说明</h4>
                    <ul>
                        <li>创建新文章：点击"添加新文章"按钮</li>
                        <li>编辑文章：点击文章标题或"编辑"链接</li>
                        <li>删除文章：选择文章后点击"删除"（需要确认）</li>
                        <li>批量操作：选择多个文章后使用批量操作功能</li>
                    </ul>
                `,
                'users': `
                    <h4>用户管理使用说明</h4>
                    <ul>
                        <li>添加用户：点击"添加新用户"按钮</li>
                        <li>分配角色：在用户编辑页面选择用户角色</li>
                        <li>权限管理：不同角色具有不同的系统权限</li>
                        <li>用户状态：可以启用或禁用用户账户</li>
                    </ul>
                `,
                'sites': `
                    <h4>站点管理使用说明</h4>
                    <ul>
                        <li>添加站点：配置WordPress站点连接信息</li>
                        <li>测试连接：验证站点配置是否正确</li>
                        <li>默认栏目：设置文章发布的默认栏目</li>
                        <li>安全存储：站点认证信息经过加密存储</li>
                    </ul>
                `,
                'general': `
                    <h4>系统使用说明</h4>
                    <ul>
                        <li>使用左侧菜单导航到不同功能页面</li>
                        <li>重要操作会要求二次确认</li>
                        <li>错误信息会在页面顶部显示</li>
                        <li>如需帮助，请点击页面右上角的"?"按钮</li>
                    </ul>
                `
            };
            
            return helpTexts[page] || helpTexts['general'];
        },
        
        /**
         * 添加面包屑导航
         */
        addBreadcrumb: function() {
            var currentPage = window.location.href;
            var breadcrumbs = [];
            
            // 根据URL构建面包屑
            if (currentPage.indexOf('page=ams-v2-dashboard') > -1) {
                breadcrumbs = ['仪表板'];
            } else if (currentPage.indexOf('page=ams-v2-articles') > -1) {
                breadcrumbs = ['仪表板', '文章管理'];
            } else if (currentPage.indexOf('page=ams-v2-users') > -1) {
                breadcrumbs = ['仪表板', '用户管理'];
            } else if (currentPage.indexOf('page=ams-v2-sites') > -1) {
                breadcrumbs = ['仪表板', '站点管理'];
            }
            
            if (breadcrumbs.length > 1) {
                var breadcrumbHtml = '<div class="ams-breadcrumb">';
                for (var i = 0; i < breadcrumbs.length; i++) {
                    if (i > 0) breadcrumbHtml += ' > ';
                    breadcrumbHtml += '<span>' + breadcrumbs[i] + '</span>';
                }
                breadcrumbHtml += '</div>';
                
                $('.wrap h1').after(breadcrumbHtml);
            }
        },
        
        /**
         * 执行批量操作
         */
        executeBulkAction: function(action) {
            var selectedIds = [];
            $('.ams-bulk-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (selectedIds.length === 0) {
                AMS_V2_UX.showError('请选择要操作的项目');
                return;
            }
            
            AMS_V2_UX.showProgress('正在执行批量操作...');
            
            // 发送AJAX请求
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ams_v2_bulk_action',
                    bulk_action: action,
                    ids: selectedIds,
                    nonce: $('#ams_v2_nonce').val()
                },
                success: function(response) {
                    AMS_V2_UX.hideProgress();
                    if (response.success) {
                        AMS_V2_UX.showSuccess(response.data.message);
                        location.reload(); // 刷新页面
                    } else {
                        AMS_V2_UX.showError(response.data.message);
                    }
                },
                error: function() {
                    AMS_V2_UX.hideProgress();
                    AMS_V2_UX.showError('批量操作失败，请重试');
                }
            });
        },
        
        /**
         * 验证邮箱格式
         */
        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },
        
        /**
         * 验证URL格式
         */
        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        }
    };
    
    // 页面加载完成后初始化
    $(document).ready(function() {
        // 添加通知容器
        if ($('#ams-notifications').length === 0) {
            $('body').append('<div id="ams-notifications"></div>');
        }
        
        // 初始化用户体验功能
        AMS_V2_UX.init();
    });
    
})(jQuery);