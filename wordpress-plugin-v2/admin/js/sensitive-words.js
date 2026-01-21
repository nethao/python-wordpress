/**
 * 敏感词管理页面JavaScript
 *
 * @package Article_Management_V2
 * @subpackage Admin/JS
 */

(function($) {
    'use strict';

    // 页面加载完成后初始化
    $(document).ready(function() {
        initSensitiveWordsPage();
    });

    /**
     * 初始化敏感词管理页面
     */
    function initSensitiveWordsPage() {
        // 全选/取消全选
        $('#cb-select-all').on('change', function() {
            $('.word-checkbox:not(:disabled)').prop('checked', this.checked);
            updateBulkActions();
        });

        // 单个复选框变化
        $(document).on('change', '.word-checkbox', function() {
            updateBulkActions();
            updateSelectAll();
        });

        // 批量删除
        $('#bulk-delete-btn').on('click', function() {
            var selectedIds = getSelectedWordIds();
            if (selectedIds.length === 0) {
                alert('请选择要删除的敏感词');
                return;
            }

            if (confirm('确定要删除选中的 ' + selectedIds.length + ' 个敏感词吗？此操作不可撤销。')) {
                bulkDeleteWords(selectedIds);
            }
        });

        // 编辑敏感词
        $(document).on('click', '.edit-word-btn', function() {
            var wordId = $(this).data('word-id');
            openEditModal(wordId);
        });

        // 删除单个敏感词
        $(document).on('click', '.delete-word-btn', function() {
            var wordId = $(this).data('word-id');
            if (confirm('确定要删除这个敏感词吗？')) {
                deleteWord(wordId);
            }
        });

        // 切换状态
        $(document).on('click', '.toggle-status-btn', function() {
            var wordId = $(this).data('word-id');
            var currentStatus = $(this).data('current-status') === '1';
            toggleWordStatus(wordId, !currentStatus);
        });

        // 导出敏感词
        $('#export-words-btn').on('click', function() {
            exportWords();
        });

        // 批量导入
        $('#import-words-btn').on('click', function() {
            $('#import-modal').show();
        });

        // 模态框事件
        initModalEvents();

        // 实时搜索
        var searchTimeout;
        $('input[name="search"]').on('input', function() {
            clearTimeout(searchTimeout);
            var keyword = $(this).val();
            searchTimeout = setTimeout(function() {
                if (keyword.length >= 2 || keyword.length === 0) {
                    // 自动提交搜索表单
                    $(this).closest('form').submit();
                }
            }.bind(this), 500);
        });
    }

    /**
     * 更新批量操作按钮状态
     */
    function updateBulkActions() {
        var selectedCount = $('.word-checkbox:checked').length;
        $('#bulk-delete-btn').prop('disabled', selectedCount === 0);
    }

    /**
     * 更新全选复选框状态
     */
    function updateSelectAll() {
        var totalCheckboxes = $('.word-checkbox:not(:disabled)').length;
        var checkedCheckboxes = $('.word-checkbox:checked').length;
        
        $('#cb-select-all').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
        $('#cb-select-all').prop('checked', checkedCheckboxes === totalCheckboxes && totalCheckboxes > 0);
    }

    /**
     * 获取选中的敏感词ID
     */
    function getSelectedWordIds() {
        var ids = [];
        $('.word-checkbox:checked').each(function() {
            ids.push($(this).val());
        });
        return ids;
    }

    /**
     * 批量删除敏感词
     */
    function bulkDeleteWords(wordIds) {
        showLoading('正在删除敏感词...');

        $.ajax({
            url: ams_v2_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ams_v2_bulk_delete_words',
                word_ids: wordIds,
                nonce: ams_v2_admin.nonce
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    // 刷新页面
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage(response.data || '删除失败', 'error');
                }
            },
            error: function() {
                hideLoading();
                showMessage('网络错误，请重试', 'error');
            }
        });
    }

    /**
     * 删除单个敏感词
     */
    function deleteWord(wordId) {
        showLoading('正在删除敏感词...');

        $.ajax({
            url: ams_v2_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ams_v2_delete_word',
                word_id: wordId,
                nonce: ams_v2_admin.nonce
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showMessage('敏感词删除成功', 'success');
                    // 移除表格行
                    $('tr[data-word-id="' + wordId + '"]').fadeOut(function() {
                        $(this).remove();
                        updateBulkActions();
                        updateSelectAll();
                    });
                } else {
                    showMessage(response.data || '删除失败', 'error');
                }
            },
            error: function() {
                hideLoading();
                showMessage('网络错误，请重试', 'error');
            }
        });
    }

    /**
     * 切换敏感词状态
     */
    function toggleWordStatus(wordId, newStatus) {
        showLoading('正在更新状态...');

        $.ajax({
            url: ams_v2_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ams_v2_toggle_word_status',
                word_id: wordId,
                is_active: newStatus ? 1 : 0,
                nonce: ams_v2_admin.nonce
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showMessage('状态更新成功', 'success');
                    
                    // 更新界面
                    var $row = $('tr[data-word-id="' + wordId + '"]');
                    var $statusBadge = $row.find('.status-badge');
                    var $toggleBtn = $row.find('.toggle-status-btn');
                    
                    if (newStatus) {
                        $statusBadge.removeClass('status-inactive').addClass('status-active').text('激活');
                        $toggleBtn.text('停用').data('current-status', '1');
                    } else {
                        $statusBadge.removeClass('status-active').addClass('status-inactive').text('停用');
                        $toggleBtn.text('激活').data('current-status', '0');
                    }
                } else {
                    showMessage(response.data || '状态更新失败', 'error');
                }
            },
            error: function() {
                hideLoading();
                showMessage('网络错误，请重试', 'error');
            }
        });
    }

    /**
     * 打开编辑模态框
     */
    function openEditModal(wordId) {
        showLoading('正在加载敏感词信息...');

        $.ajax({
            url: ams_v2_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ams_v2_get_word',
                word_id: wordId,
                nonce: ams_v2_admin.nonce
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    var word = response.data;
                    
                    // 填充表单
                    $('#edit-word-id').val(word.id);
                    $('#edit-word').val(word.word);
                    $('#edit-type').val(word.type);
                    $('#edit-replacement').val(word.replacement);
                    $('#edit-is-active').prop('checked', word.is_active);
                    
                    // 显示模态框
                    $('#edit-word-modal').show();
                } else {
                    showMessage(response.data || '加载失败', 'error');
                }
            },
            error: function() {
                hideLoading();
                showMessage('网络错误，请重试', 'error');
            }
        });
    }

    /**
     * 保存编辑的敏感词
     */
    function saveEditedWord() {
        var formData = {
            action: 'ams_v2_update_word',
            word_id: $('#edit-word-id').val(),
            word: $('#edit-word').val(),
            type: $('#edit-type').val(),
            replacement: $('#edit-replacement').val(),
            is_active: $('#edit-is-active').is(':checked') ? 1 : 0,
            nonce: ams_v2_admin.nonce
        };

        // 验证表单
        if (!formData.word.trim()) {
            showMessage('请输入敏感词内容', 'error');
            return;
        }

        showLoading('正在保存...');

        $.ajax({
            url: ams_v2_admin.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showMessage('敏感词更新成功', 'success');
                    $('#edit-word-modal').hide();
                    
                    // 刷新页面
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage(response.data || '更新失败', 'error');
                }
            },
            error: function() {
                hideLoading();
                showMessage('网络错误，请重试', 'error');
            }
        });
    }

    /**
     * 导出敏感词
     */
    function exportWords() {
        var params = new URLSearchParams();
        params.append('action', 'ams_v2_export_words');
        params.append('nonce', ams_v2_admin.nonce);
        
        // 添加当前的过滤条件
        var filterType = $('select[name="filter_type"]').val();
        if (filterType) {
            params.append('filter_type', filterType);
        }
        
        var searchKeyword = $('input[name="search"]').val();
        if (searchKeyword) {
            params.append('search', searchKeyword);
        }

        // 创建下载链接
        var downloadUrl = ams_v2_admin.ajax_url + '?' + params.toString();
        
        // 创建隐藏的下载链接并点击
        var $link = $('<a>').attr({
            href: downloadUrl,
            download: 'sensitive-words-' + new Date().toISOString().split('T')[0] + '.csv'
        }).appendTo('body');
        
        $link[0].click();
        $link.remove();
        
        showMessage('导出已开始，请稍候...', 'info');
    }

    /**
     * 开始导入敏感词
     */
    function startImport() {
        var fileInput = $('#import-file')[0];
        var file = fileInput.files[0];
        
        if (!file) {
            showMessage('请选择要导入的文件', 'error');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'ams_v2_import_words');
        formData.append('import_file', file);
        formData.append('import_type', $('#import-type').val());
        formData.append('nonce', ams_v2_admin.nonce);

        showLoading('正在导入敏感词，请稍候...');

        $.ajax({
            url: ams_v2_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                hideLoading();
                if (response.success) {
                    var result = response.data;
                    var message = '导入完成！成功: ' + result.success + 
                                 ', 失败: ' + result.failed + 
                                 ', 跳过: ' + result.skipped;
                    
                    showMessage(message, 'success');
                    $('#import-modal').hide();
                    
                    // 刷新页面
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showMessage(response.data || '导入失败', 'error');
                }
            },
            error: function() {
                hideLoading();
                showMessage('网络错误，请重试', 'error');
            }
        });
    }

    /**
     * 初始化模态框事件
     */
    function initModalEvents() {
        // 关闭模态框
        $('.ams-modal-close, #cancel-edit-btn, #cancel-import-btn').on('click', function() {
            $('.ams-modal').hide();
        });

        // 点击模态框外部关闭
        $('.ams-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });

        // 保存编辑
        $('#save-word-btn').on('click', function() {
            saveEditedWord();
        });

        // 开始导入
        $('#start-import-btn').on('click', function() {
            startImport();
        });

        // ESC键关闭模态框
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27) { // ESC键
                $('.ams-modal').hide();
            }
        });
    }

    /**
     * 显示加载状态
     */
    function showLoading(message) {
        message = message || '加载中...';
        
        // 移除现有的加载提示
        $('.ams-loading').remove();
        
        // 创建加载提示
        var $loading = $('<div class="ams-loading">' +
            '<div class="ams-loading-backdrop"></div>' +
            '<div class="ams-loading-content">' +
                '<div class="ams-loading-spinner"></div>' +
                '<div class="ams-loading-text">' + message + '</div>' +
            '</div>' +
        '</div>');
        
        $('body').append($loading);
    }

    /**
     * 隐藏加载状态
     */
    function hideLoading() {
        $('.ams-loading').remove();
    }

    /**
     * 显示消息
     */
    function showMessage(message, type) {
        type = type || 'info';
        
        // 移除现有消息
        $('.ams-message').remove();
        
        // 创建消息元素
        var $message = $('<div class="ams-message ams-message-' + type + '">' +
            '<p>' + message + '</p>' +
            '<button type="button" class="ams-message-close">&times;</button>' +
        '</div>');
        
        // 插入到页面顶部
        $('.wrap h1').after($message);
        
        // 自动隐藏
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // 点击关闭
        $message.find('.ams-message-close').on('click', function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        });
    }

})(jQuery);

// 添加CSS样式
jQuery(document).ready(function($) {
    if ($('#ams-sensitive-words-styles').length === 0) {
        $('<style id="ams-sensitive-words-styles">' +
            '.ams-loading {' +
                'position: fixed;' +
                'top: 0;' +
                'left: 0;' +
                'width: 100%;' +
                'height: 100%;' +
                'z-index: 999999;' +
            '}' +
            '.ams-loading-backdrop {' +
                'position: absolute;' +
                'top: 0;' +
                'left: 0;' +
                'width: 100%;' +
                'height: 100%;' +
                'background: rgba(0, 0, 0, 0.5);' +
            '}' +
            '.ams-loading-content {' +
                'position: absolute;' +
                'top: 50%;' +
                'left: 50%;' +
                'transform: translate(-50%, -50%);' +
                'background: white;' +
                'padding: 30px;' +
                'border-radius: 4px;' +
                'text-align: center;' +
                'box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);' +
            '}' +
            '.ams-loading-spinner {' +
                'width: 40px;' +
                'height: 40px;' +
                'border: 4px solid #f3f3f3;' +
                'border-top: 4px solid #0073aa;' +
                'border-radius: 50%;' +
                'animation: ams-spin 1s linear infinite;' +
                'margin: 0 auto 15px;' +
            '}' +
            '@keyframes ams-spin {' +
                '0% { transform: rotate(0deg); }' +
                '100% { transform: rotate(360deg); }' +
            '}' +
            '.ams-loading-text {' +
                'color: #333;' +
                'font-weight: 500;' +
            '}' +
            '.ams-message {' +
                'position: relative;' +
                'margin: 15px 0;' +
                'padding: 12px 15px;' +
                'border-left: 4px solid;' +
                'background: white;' +
                'box-shadow: 0 1px 1px 0 rgba(0, 0, 0, 0.1);' +
            '}' +
            '.ams-message p {' +
                'margin: 0;' +
                'padding-right: 30px;' +
            '}' +
            '.ams-message-close {' +
                'position: absolute;' +
                'top: 8px;' +
                'right: 10px;' +
                'background: none;' +
                'border: none;' +
                'font-size: 18px;' +
                'cursor: pointer;' +
                'color: #666;' +
            '}' +
            '.ams-message-close:hover {' +
                'color: #000;' +
            '}' +
            '.ams-message-success {' +
                'border-left-color: #46b450;' +
                'background: #f7fff7;' +
            '}' +
            '.ams-message-error {' +
                'border-left-color: #dc3232;' +
                'background: #fff7f7;' +
            '}' +
            '.ams-message-info {' +
                'border-left-color: #00a0d2;' +
                'background: #f7fcfe;' +
            '}' +
        '</style>').appendTo('head');
    }
});