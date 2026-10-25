(function( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	$(function(){

		$('.jtrespo-scroll table, .jtrespo-stack table').each(function(infxx,tableee){
			var mytablr = $(tableee),
				isfiltered = mytablr.attr('data-filtering'),
				isSorted = mytablr.attr('data-sorting'),
				isPaged = mytablr.attr('data-paging'),
				isPagedCtn = mytablr.attr('data-paging-size'),
				paginationMenu = mytablr.attr('data-paging-menu');

			if(isfiltered == "true" || isSorted == "true" || isPaged == "true"){
				var jtrtDTcopy = mytablr.DataTable({
					"paging":isPaged,
					"pageLength": isPagedCtn,
					"lengthMenu": paginationMenu.split(','),
					"ordering":isSorted,
					"order": [],
					"searching":isfiltered,
					"info":false,
					"language": {
						"emptyTable":     translation_for_frontend.emptyTable,
						"info":           translation_for_frontend.info,
						"infoEmpty":      translation_for_frontend.infoEmpty,
						"infoFiltered":   translation_for_frontend.infoFiltered,
						"lengthMenu":     translation_for_frontend.lengthMenu,
						"search":         translation_for_frontend.search_string,
						"zeroRecords":    translation_for_frontend.zeroRecords,
						"paginate": {
							"first":      translation_for_frontend.first,
							"last":       translation_for_frontend.last,
							"next":       translation_for_frontend.next_string,
							"previous":   translation_for_frontend.prev_string
						},
					}
				});

			}
		});


		function checkTableWidth(table){
			if(table.outerWidth(true) < table.parents('.jtrespo-stack').attr('data-jtrt-stack-width')){
				table.addClass('stackMeNowJT');		
			}else{
				table.removeClass('stackMeNowJT');
			}
		}

		checkTableWidth($('.jtrespo-stack table'));

		$(window).resize(function(){
			checkTableWidth($('.jtrespo-stack table'));
		});

		$('.highlightCols').on('mouseenter', 'tbody td', function(){
			var eq = $(this).index() + 1;
			$('.highlightCols tbody td:nth-child('+eq+')').addClass('jtrt-col-hoveredOver');
		});
		$('.highlightCols').on('mouseleave', 'tbody td', function(){
			var eq = $(this).index() + 1;
			$('.highlightCols tbody td:nth-child('+eq+')').removeClass('jtrt-col-hoveredOver');
		});

		

		$('.jtrespo-footable table').each(function(idx,elem){
			var id = $(elem).attr('data-jtrt-table-id');
			var footablBreaksjt = JSON.parse($('#jtrt_table_bps_'+id).html());
			$(elem).footable({
				"breakpoints": footablBreaksjt,
				"useParentWidth": true,
				"showToggle": true
				
			});
		});


		

	});
		
})( jQuery );
