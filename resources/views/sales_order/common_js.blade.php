<script type="text/javascript">
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
		    var url = $('form#update_so_status_form').attr('action');
		    var method = $('form#update_so_status_form').attr('method');
		    var data = $('form#update_so_status_form').serialize();
		    var ladda = Ladda.create(document.querySelector('.ladda-button'));
		    ladda.start();
		    $.ajax({
		        method: method,
		        dataType: "json",
		        url: url,
		        data:data,
		        success: function(result){
		            ladda.stop();
		            if (result.success) {
		                $('.edit_pso_status_modal').modal("hide");
		                toastr.success(result.msg);
		                if (typeof(sell_table) != 'undefined') {
		                    sell_table.ajax.reload();
		                }
		                if (typeof(sales_order_table) != 'undefined') {
		                    sales_order_table.ajax.reload();
		                }
		            } else {
		                toastr.error(result.msg);
		            }
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
		                if (typeof(sell_table) != 'undefined') {
		                    sell_table.ajax.reload();
		                }
		                if (typeof(sales_order_table) != 'undefined') {
		                    sales_order_table.ajax.reload();
		                }
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