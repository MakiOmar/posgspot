/**
 * Sell / sales order "Attach Documents": pick several files, remove any before saving,
 * and delete already-saved attachments on the edit form.
 */
(function ($) {
    'use strict';

    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }

    function initField($field) {
        var picker = $field.find('.sell-documents-picker')[0];
        var input = $field.find('.sell-documents-input')[0];
        var $list = $field.find('.sell-documents-selected');
        var maxFiles = parseInt($field.data('max-files'), 10) || 10;
        var maxBytes = parseInt($field.data('max-bytes'), 10) || 0;
        var files = [];

        // Without DataTransfer the picker itself carries the files (no per-file remove).
        if (typeof DataTransfer === 'undefined') {
            picker.setAttribute('name', 'sell_documents[]');
            input.removeAttribute('name');
            return;
        }

        function syncInput() {
            var transfer = new DataTransfer();
            files.forEach(function (file) {
                transfer.items.add(file);
            });
            input.files = transfer.files;
        }

        function render() {
            $list.empty();
            files.forEach(function (file, index) {
                $list.append(
                    '<li style="margin-bottom: 4px;">' +
                        '<i class="fa fa-paperclip"></i> ' + escapeHtml(file.name) +
                        ' <small class="text-muted">(' + (file.size / 1024).toFixed(0) + ' KB)</small> ' +
                        '<button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error sell-documents-remove" data-index="' + index + '">' +
                            '<i class="fas fa-times"></i> ' + escapeHtml($field.data('msg-remove')) +
                        '</button>' +
                    '</li>'
                );
            });
        }

        $(picker).on('change', function () {
            Array.prototype.forEach.call(picker.files, function (file) {
                if (maxBytes && file.size > maxBytes) {
                    toastr.error(file.name + ': ' + $field.data('msg-too-large'));
                    return;
                }
                if (files.length >= maxFiles) {
                    toastr.warning($field.data('msg-max-files'));
                    return;
                }
                files.push(file);
            });
            picker.value = '';
            syncInput();
            render();
        });

        $list.on('click', '.sell-documents-remove', function () {
            files.splice(parseInt($(this).data('index'), 10), 1);
            syncInput();
            render();
        });
    }

    $(function () {
        $('.sell-documents-field').each(function () {
            initField($(this));
        });

        $(document).on('click', '.delete-sell-document', function () {
            var $button = $(this);
            swal({
                title: LANG.sure,
                icon: 'warning',
                buttons: true,
                dangerMode: true,
            }).then(function (willDelete) {
                if (!willDelete) {
                    return;
                }
                $button.prop('disabled', true);
                $.ajax({
                    url: $button.data('href'),
                    method: 'DELETE',
                    dataType: 'json',
                    success: function (result) {
                        if (result.success) {
                            $button.closest('tr').remove();
                            toastr.success(result.msg);
                        } else {
                            toastr.error(result.msg);
                            $button.prop('disabled', false);
                        }
                    },
                    error: function (xhr) {
                        var msg = xhr.responseJSON && xhr.responseJSON.msg ? xhr.responseJSON.msg : LANG.something_went_wrong;
                        toastr.error(msg);
                        $button.prop('disabled', false);
                    },
                });
            });
        });
    });
})(jQuery);
