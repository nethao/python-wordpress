/**
 * 管理后台JavaScript
 *
 * @package Article_Management_V2
 * @subpackage Admin/JS
 */

(function($) {
    'use strict';

    // 全局变量
    var AMS_V2_Admin = {
        init: function() {
            this.bindEvents();
            this.initModals();
            this.initTooltips();
            this.initBulkActions();
        },

        bindEvents: function() {
            // 模态框事件
            $(document).on('click', '.ams-modal-close, .ams-modal-cancel', this.closeModal);
            $(document).on('click', '.ams-modal', function(e) {
                if (e.target === this) {
                    AMS_V2_Admin.closeModal();
                }
            });

            // 表单提交事件
            $(document).on('submit', '.ams-ajax-form', this.handleAjaxForm);

            // 确认删除事件
            $(document).on('click', '.ams-confirm-delete', this.confirmDelete);

            // 批量操作事件
            $(document).on('click', '.ams-bulk-apply', this.handleBulkAction);

            // 全选/取消全选
            $(document).on('change', '#cb-select-all-1, #cb-select-all-2', this.toggleSelectAll);
        },

        initModals: function() {
            // 初始化模态框
            $('.ams-modal').hide();
        },

        initTooltips: function() {
            // 初始化工具提示
            $('[data-tooltip]').each(function() {
                $(this).addClass('ams-tooltip');
            });
        },

        initBulkActions: function() {
            // 初始化批量操作
            $('.bulkactions select').on('change', function() {
                var action = $(this).val();
                var $button = $(this).siblings('.button');
                
                if (action === '-1') {
                    $button.prop('disabled', true);
                } else {
                    $button.prop('disabled', false);
                }
            });
        },

        showModal: function(modalId) {
            $('#' + modalId).fadeIn(300);
            $('body').addClass('modal-open');
        },

        closeModal: function() {
            $('.ams-modal').fadeOut(300);
            $('body').removeClass('modal-open');
        },

        showNotice: function(message, type) {
            type = type || 'info';
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after($notice);
            
            // 自动隐藏通知
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        showLoading: function($element) {
            $element.addClass('loading').prop('disabled', true);
        },

        hideLoading: function($element) {
            $element.removeClass('loading').prop('disabled', false);
        },

        handleAjaxForm: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitButton = $form.find('[type="submit"]');
            var formData = new FormData(this);
            
            // 添加nonce
            formData.append('nonce', ams_v2_admin.nonce);
            
            AMS_V2_Admin.showLoading($submitButton);
            
            $.ajax({
                url: ams_v2_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    AMS_V2_Admin.hideLoading($submitButton);
                    
                    if (response.success) {
                        AMS_V2_Admin.showNotice(response.data.message || ams_v2_admin.strings.success, 'success');
                        AMS_V2_Admin.closeModal();
                        
                        // 刷新页面或更新内容
                        if ($form.data('refresh') !== false) {
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        }
                    } else {
                        AMS_V2_Admin.showNotice(response.data || ams_v2_admin.strings.error, 'error');
                    }
                },
                error: function() {
                    AMS_V2_Admin.hideLoading($submitButton);
                    AMS_V2_Admin.showNotice(ams_v2_admin.strings.error, 'error');
                }
            });
        },

        confirmDelete: function(e) {
            e.preventDefault();
            
            var $this = $(this);
            var message = $this.data('confirm') || ams_v2_admin.strings.confirm_delete;
            
            if (confirm(message)) {
                var action = $this.data('action');
                var itemId = $this.data('id');
                
                AMS_V2_Admin.showLoading($this);
                
                $.ajax({
                    url: ams_v2_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: action,
                        id: itemId,
                        nonce: ams_v2_admin.nonce
                    },
                    success: function(response) {
                        AMS_V2_Admin.hideLoading($this);
                        
                        if (response.success) {
                            AMS_V2_Admin.showNotice(response.data || ams_v2_admin.strings.success, 'success');
                            
                            // 移除表格行
                            $this.closest('tr').fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            AMS_V2_Admin.showNotice(response.data || ams_v2_admin.strings.error, 'error');
                        }
                    },
                    error: function() {
                        AMS_V2_Admin.hideLoading($this);
                        AMS_V2_Admin.showNotice(ams_v2_admin.strings.error, 'error');
                    }
                });
            }
        },

        handleBulkAction: function(e) {
            e.preventDefault();
            
            var $this = $(this);
            var $select = $this.siblings('select');
            var action = $select.val();
            
            if (action === '-1') {
                return;
            }
            
            var $checkboxes = $('tbody input[type="checkbox"]:checked');
            if ($checkboxes.length === 0) {
                alert('请选择要操作的项目');
                return;
            }
            
            var ids = [];
            $checkboxes.each(function() {
                ids.push($(this).val());
            });
            
            var message = '确定要对选中的 ' + ids.length + ' 个项目执行 "' + $select.find('option:selected').text() + '" 操作吗？';
            
            if (confirm(message)) {
                AMS_V2_Admin.showLoading($this);
                
                $.ajax({
                    url: ams_v2_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ams_v2_bulk_' + action,
                        ids: ids,
                        nonce: ams_v2_admin.nonce
                    },
                    success: function(response) {
                        AMS_V2_Admin.hideLoading($this);
                        
                        if (response.success) {
                            AMS_V2_Admin.showNotice(response.data || ams_v2_admin.strings.success, 'success');
                            location.reload();
                        } else {
                            AMS_V2_Admin.showNotice(response.data || ams_v2_admin.strings.error, 'error');
                        }
                    },
                    error: function() {
                        AMS_V2_Admin.hideLoading($this);
                        AMS_V2_Admin.showNotice(ams_v2_admin.strings.error, 'error');
                    }
                });
            }
        },

        toggleSelectAll: function() {
            var $this = $(this);
            var checked = $this.prop('checked');
            
            $('tbody input[type="checkbox"]').prop('checked', checked);
        },

        // 用户管理相关方法
        User: {
            showCreateModal: function() {
                $('#user-modal #modal-title').text('添加新用户');
                $('#user-modal #form-action').val('ams_v2_create_user');
                $('#user-modal #user-id').val('');
                $('#user-modal form')[0].reset();
                $('#user-modal #username').prop('readonly', false);
                $('#user-modal #password-row').show();
                $('#user-modal #password').prop('required', true);
                $('#user-modal #is_active').prop('checked', true);
                AMS_V2_Admin.showModal('user-modal');
            },

            showEditModal: function(userId) {
                $.ajax({
                    url: ams_v2_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ams_v2_get_user',
                        user_id: userId,
                        nonce: ams_v2_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var user = response.data;
                            $('#user-modal #modal-title').text('编辑用户');
                            $('#user-modal #form-action').val('ams_v2_update_user');
                            $('#user-modal #user-id').val(user.id);
                            $('#user-modal #username').val(user.username).prop('readonly', true);
                            $('#user-modal #email').val(user.email);
                            $('#user-modal #display_name').val(user.display_name);
                            $('#user-modal #is_active').prop('checked', user.is_active);
                            $('#user-modal #password-row').hide();
                            $('#user-modal #password').prop('required', false);
                            AMS_V2_Admin.showModal('user-modal');
                        } else {
                            AMS_V2_Admin.showNotice(response.data || '获取用户信息失败', 'error');
                        }
                    },
                    error: function() {
                        AMS_V2_Admin.showNotice('获取用户信息失败', 'error');
                    }
                });
            },

            delete: function(userId, username) {
                var message = '确定要删除用户 "' + username + '" 吗？此操作不可撤销。';
                
                if (confirm(message)) {
                    $.ajax({
                        url: ams_v2_admin.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'ams_v2_delete_user',
                            user_id: userId,
                            nonce: ams_v2_admin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                AMS_V2_Admin.showNotice('用户删除成功', 'success');
                                location.reload();
                            } else {
                                AMS_V2_Admin.showNotice(response.data || '删除用户失败', 'error');
                            }
                        },
                        error: function() {
                            AMS_V2_Admin.showNotice('删除用户失败', 'error');
                        }
                    });
                }
            }
        },

        // 工具方法
        Utils: {
            formatDate: function(dateString) {
                if (!dateString) return '-';
                
                var date = new Date(dateString);
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
            },

            escapeHtml: function(text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            },

            debounce: function(func, wait, immediate) {
                var timeout;
                return function() {
                    var context = this, args = arguments;
                    var later = function() {
                        timeout = null;
                        if (!immediate) func.apply(context, args);
                    };
                    var callNow = immediate && !timeout;
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                    if (callNow) func.apply(context, args);
                };
            }
        }
    };

    // 页面加载完成后初始化
    $(document).ready(function() {
        AMS_V2_Admin.init();

        // 文章管理页面特定事件
        if ($('body').hasClass('article-management-v2_page_article-management-v2-articles')) {
            // 批量操作处理
            $(document).on('click', '.ams-bulk-apply', function(e) {
                e.preventDefault();
                
                var $this = $(this);
                var $select = $this.siblings('select');
                var action = $select.val();
                
                if (action === '-1') {
                    return;
                }
                
                var $checkboxes = $('tbody input[type="checkbox"]:checked');
                if ($checkboxes.length === 0) {
                    alert('请选择要操作的文章');
                    return;
                }
                
                var ids = [];
                $checkboxes.each(function() {
                    ids.push($(this).val());
                });
                
                var actionLabels = {
                    'approve': '批准',
                    'reject': '拒绝',
                    'publish': '发布',
                    'archive': '归档',
                    'delete': '删除'
                };
                
                var message = '确定要对选中的 ' + ids.length + ' 篇文章执行 "' + actionLabels[action] + '" 操作吗？';
                if (action === 'delete') {
                    message += '\n\n此操作不可撤销！';
                }
                
                if (confirm(message)) {
                    AMS_V2_Admin.showLoading($this);
                    
                    $.ajax({
                        url: ams_v2_admin.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'ams_v2_bulk_moderate',
                            bulk_action: action,
                            ids: ids,
                            nonce: ams_v2_admin.nonce
                        },
                        success: function(response) {
                            AMS_V2_Admin.hideLoading($this);
                            
                            if (response.success) {
                                AMS_V2_Admin.showNotice(response.data, 'success');
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);
                            } else {
                                AMS_V2_Admin.showNotice(response.data || '批量操作失败', 'error');
                            }
                        },
                        error: function() {
                            AMS_V2_Admin.hideLoading($this);
                            AMS_V2_Admin.showNotice('批量操作失败', 'error');
                        }
                    });
                }
            });
        }

        // 用户管理页面特定事件
        if ($('body').hasClass('article-management-v2_page_article-management-v2-users')) {
            // 添加新用户按钮
            $(document).on('click', '#add-new-user', function(e) {
                e.preventDefault();
                AMS_V2_Admin.User.showCreateModal();
            });

            // 编辑用户按钮
            $(document).on('click', '.edit-user', function(e) {
                e.preventDefault();
                var userId = $(this).data('user-id');
                AMS_V2_Admin.User.showEditModal(userId);
            });

            // 删除用户按钮
            $(document).on('click', '.delete-user', function(e) {
                e.preventDefault();
                var userId = $(this).data('user-id');
                var username = $(this).data('username');
                AMS_V2_Admin.User.delete(userId, username);
            });

            // 用户表单验证
            $(document).on('submit', '#user-form', function(e) {
                var password = $('#password').val();
                if ($('#password').prop('required') && password.length < 6) {
                    e.preventDefault();
                    alert('密码长度至少6位');
                    return false;
                }
            });
        }
    });

    // 暴露全局对象
    window.AMS_V2_Admin = AMS_V2_Admin;

})(jQuery);