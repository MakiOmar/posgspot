<script type="text/javascript">
	/**
	 * Re-query the sales order list(s) on this page with the current filters, keeping the page position,
	 * so rows whose status no longer matches a filter drop out after an inline change.
	 */
	function refreshSalesOrderTable() {
		if (typeof(sell_table) != 'undefined') {
			sell_table.ajax.reload(null, false);
		}
		if (typeof(sales_order_table) != 'undefined') {
			sales_order_table.ajax.reload(null, false);
		}
	}

	$(document).ready( function(){
		$(document).on('click', '.edit-so-status', function () {
			var url  = $(this).data('href');
		    $.ajax({
		        method: "GET",
		        dataType: "html",
		        url: url,
		        success: function(result){
		            $('.edit_pso_status_modal').html(result).modal("show");
		        }
		    });
		});

		$(document).on('submit', 'form#update_so_status_form', function(e){
		    e.preventDefault();
		    var form = $(this);
		    var ladda = Ladda.create(form.find('.ladda-button')[0]);
		    ladda.start();
		    $.ajax({
		        method: form.attr('method'),
		        dataType: "json",
		        url: form.attr('action'),
		        data: form.serialize(),
		        success: function(result){
		            if (result.success) {
		                $('.edit_pso_status_modal').modal("hide");
		                toastr.success(result.msg);
		                refreshSalesOrderTable();
		            } else {
		                toastr.error(result.msg);
		            }
		        },
		        error: function(){
		            toastr.error(LANG.something_went_wrong);
		        },
		        complete: function(){
		            ladda.stop();
		        }
		    });
		});

		// Retry: create final invoice from a fully paid SO (e.g. after stock was short)
		$(document).on('click', 'a.so-create-invoice', function(e){
		    e.preventDefault();
		    var url = $(this).attr('href');
		    $.ajax({
		        method: 'POST',
		        dataType: 'json',
		        url: url,
		        data: { _token: $('meta[name="csrf-token"]').attr('content') },
		        success: function(result){
		            if (result.success) {
		                toastr.success(result.msg);
		                refreshSalesOrderTable();
		                if (result.print_url) {
		                    window.open(result.print_url, '_blank');
		                }
		            } else {
		                toastr.error(result.msg);
		            }
		        }
		    });
		});
	});	
</script>
