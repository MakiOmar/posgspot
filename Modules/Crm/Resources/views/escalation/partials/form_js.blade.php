<script type="text/javascript">
    $(document).ready(function() {
        $('.select2').select2();

        $('.datetimepicker').datetimepicker({
            format: moment_date_format + ' ' + moment_time_format,
            ignoreReadonly: true,
        });

        var userSearchUrl = '{{ action([\Modules\Crm\Http\Controllers\EscalationController::class, "searchUsers"]) }}';
        var invoiceSearchUrl = '{{ action([\Modules\Crm\Http\Controllers\EscalationController::class, "searchInvoices"]) }}';

        function initUserSelect(selector, allowClear) {
            $(selector).select2({
                ajax: {
                    url: userSearchUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { q: params.term };
                    },
                    processResults: function(data) {
                        return { results: data };
                    },
                },
                minimumInputLength: 1,
                allowClear: allowClear || false,
                placeholder: '{{ __("messages.please_select") }}',
            });
        }

        initUserSelect('#escalation_employee_id');
        initUserSelect('#escalation_observer_id', true);
        initUserSelect('#escalation_auditor_id', true);

        $('#escalation_contact_id').select2({
            ajax: {
                url: '/contacts/customers',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { q: params.term };
                },
                processResults: function(data) {
                    return { results: data };
                },
            },
            minimumInputLength: 1,
            placeholder: '{{ __("messages.please_select") }}',
            templateResult: function(data) {
                if (!data.id) {
                    return data.text;
                }
                var markup = '';
                if (data.supplier_business_name) {
                    markup += data.supplier_business_name + '<br>';
                }
                markup += data.text;
                if (data.mobile) {
                    markup += '<br>' + (typeof LANG !== 'undefined' ? LANG.mobile : 'Mobile') + ': ' + data.mobile;
                }
                return markup;
            },
            escapeMarkup: function(markup) {
                return markup;
            },
        }).on('select2:select', function(e) {
            var data = e.params.data;
            if (data.mobile) {
                $('#escalation_phone').val(data.mobile);
            }
            $('#escalation_transaction_id').val(null).trigger('change');
        });

        $('#escalation_transaction_id').select2({
            ajax: {
                url: invoiceSearchUrl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term,
                        contact_id: $('#escalation_contact_id').val()
                    };
                },
                processResults: function(data) {
                    return { results: data };
                },
            },
            minimumInputLength: 0,
            allowClear: true,
            placeholder: '{{ __("messages.please_select") }}',
        });
    });
</script>
