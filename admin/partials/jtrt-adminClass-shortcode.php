<?php

declare(strict_types=1);

/**
 * Shortcode for displaying the JTRT table.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string|void HTML output of the table.
 */
function jtrt_shortcode_table( $atts ) {
	global $wpdb;

	$jtrt_settings = shortcode_atts(
		array(
			'id'         => '',
			'filterrows' => '',
			'filtercols' => '',
		),
		$atts
	);

	$table_post_meta = get_post_meta( (int) $jtrt_settings['id'], 'jtrt_data_settings', true ); // get the table meta options

	if ( ! is_array( $table_post_meta ) || ! isset( $table_post_meta['tabledata'] ) ) {
		echo 'Unfortunately we could not locate the table you\'re looking for.';
		return;
	}

	$table_data_json = json_decode( (string) $table_post_meta['tabledata'], true );
	$table_data      = $table_data_json[0] ?? null;
	$table_cell_data = $table_data_json[1] ?? array();

	if ( ! $table_data ) {
		echo 'Unfortunately we could not locate the table you\'re looking for.';
		return;
	}

	$showTableTitle    = isset( $table_post_meta['jtShowTableTitle'] ) ? 'true' : 'false';
	$showTableTitlePos = explode( ',', (string) ( $table_post_meta['jtShowTableTitlePos'] ?? '' ) );
	$myTableTitle      = get_the_title( (int) $jtrt_settings['id'] );

	$myTableResponsiveStyle = (string) ( $table_post_meta['jtTableResponsiveStyle'] ?? '' );
	$myTableStackPrefWidth  = (string) ( $table_post_meta['jtStackPrefWidth'] ?? '' );

	$myTableCustomClass = esc_textarea( (string) ( $table_post_meta['jtTableCustomClass'] ?? '' ) );

	$myTableHoverRows    = isset( $table_post_meta['jtTableEnableRowHighlight'] ) ? 'highlightRows' : '';
	$myTableHoverRowsCol = isset( $table_post_meta['jtTableEnableRowHighlight'] ) ? "data-jtrt-rowhighligh-color='" . ( $table_post_meta['jtTableEnableRowHighlightcol'] ?? '' ) . "'" : '';
	$myTableHoverCols    = isset( $table_post_meta['jtTableEnableColHighlight'] ) ? 'highlightCols' : '';
	$myTableHoverColsCol = isset( $table_post_meta['jtTableEnableColHighlight'] ) ? "data-jtrt-colhighligh-color='" . ( $table_post_meta['jtTableEnableColHighlightcol'] ?? '' ) . "'" : '';

	$myjtbpfootab = array();

	if ( $myTableResponsiveStyle === 'footable' ) {
		$myjtbpfootab['xlarge'] = (string) ( $table_post_meta['jtFootableBPxlarge'] ?? '' );
		$myjtbpfootab['large']  = (string) ( $table_post_meta['jtFootableBPlarge'] ?? '' );
		$myjtbpfootab['medium'] = (string) ( $table_post_meta['jtFootableBPmedium'] ?? '' );
		$myjtbpfootab['small']  = (string) ( $table_post_meta['jtFootableBPsmall'] ?? '' );
		$myjtbpfootab['xsmall'] = (string) ( $table_post_meta['jtFootableBPxsmall'] ?? '' );
	}

	$myjttableFiltering = isset( $table_post_meta['jtTableEnableFilters'] ) ? 'true' : 'false';
	$myjttableSorting   = isset( $table_post_meta['jtTableEnableSorting'] ) ? 'true' : 'false';
	$myjttablePaging    = isset( $table_post_meta['jtTableEnablePaging'] ) ? 'true' : 'false';
	$myjttablePagingCnt = (string) ( $table_post_meta['jtTableEnablePagingCnt'] ?? '10' );

	$paging_menu_raw     = (string) ( $table_post_meta['jtTablePagingMenu'] ?? '' );
	$myjttablePagingMenu = ( $paging_menu_raw !== '' && is_array( explode( ',', $paging_menu_raw ) ) ) ? $paging_menu_raw : '10,25,50,100';

	$html = "<div class='jtrt_table_MotherShipContainer'>";

	if ( $showTableTitle === 'true' && isset( $showTableTitlePos[0] ) && $showTableTitlePos[0] === 'top' ) {
		$html .= "<div id='jtHeaderHolder-" . $jtrt_settings['id'] . "'><h3 style='margin-top:24px;margin-bottom:14px;text-align:" . ( $showTableTitlePos[1] ?? '' ) . ";'>" . $myTableTitle . '</h3>';
		$html .= '</div>';
	}

	$html .= "<div class='jtTableContainer jtrespo-" . $myTableResponsiveStyle . ' ' . $myTableHoverRows . ' ' . $myTableHoverCols . "' " . ( $myTableResponsiveStyle === 'stack' ? "data-jtrt-stack-width='" . $myTableStackPrefWidth . "'" : '' ) . '>';

	// We can only return once, so let's build our html!
	$html .= "<div class='jtsettingcontainer' style='display:none;position:absolute;left:-9999px;'><textarea data-jtrt-table-id='" . $jtrt_settings['id'] . "' id='jtrt_table_settings_" . $jtrt_settings['id'] . "' cols='30' rows='10'>" . json_encode( $table_data_json ) . "</textarea><textarea data-jtrt-table-id='" . $jtrt_settings['id'] . "' id='jtrt_table_bps_" . $jtrt_settings['id'] . "' cols='30' rows='10'>" . json_encode( $myjtbpfootab ) . "</textarea></div><table id='jtrt_table_" . $jtrt_settings['id'] . "' data-sorting='" . $myjttableSorting . "' data-paging='" . $myjttablePaging . "' data-paging-size='" . $myjttablePagingCnt . "' data-paging-menu='" . $myjttablePagingMenu . "' data-filtering='" . $myjttableFiltering . "' data-jtrt-table-id='" . $jtrt_settings['id'] . "' class='jtrt-table " . $myTableCustomClass . " ' >";

	$filteredRows = $jtrt_settings['filterrows'] !== '' ? array_flip( explode( ',', (string) $jtrt_settings['filterrows'] ) ) : array();
	$filteredCols = $jtrt_settings['filtercols'] !== '' ? array_flip( explode( ',', (string) $jtrt_settings['filtercols'] ) ) : array();

	/**
	 * Helper function to process cell content efficiently.
	 *
	 * @param mixed $cell The cell content.
	 * @return string Processed content.
	 */
	$process_cell = static function ( $cell ) {
		$cell_str = (string) $cell;
		if ( $cell_str === '' ) {
			return '';
		}

		// Only replace newlines if they exist.
		if ( str_contains( $cell_str, "\n" ) || str_contains( $cell_str, "\r" ) ) {
			$cell_str = str_replace( array( "\r\n", "\r", "\n" ), '<br>', $cell_str );
		}

		// Only run do_shortcode if shortcodes are likely present.
		if ( str_contains( $cell_str, '[' ) ) {
			return do_shortcode( $cell_str );
		}

		return $cell_str;
	};

	// For each loop to loop through the table data, the first loop is the rows.
	foreach ( $table_data as $indx => $row ) {
		$row_num_str = (string) ( $indx + 1 );
		if ( isset( $filteredRows[ $row_num_str ] ) ) {
			continue;
		}

		if ( $indx === 0 ) {
			$html .= '<thead><tr>';
			foreach ( $row as $cellindx => $cell ) {
				$col_num_str = (string) ( $cellindx + 1 );
				// For each col item, insert the table data tag and put the data inside it.
				if ( ! isset( $filteredCols[ $col_num_str ] ) ) {
					$html .= '<th>' . $process_cell( $cell ) . '</th>';
				}
			}
			$html .= '</tr></thead><tbody>';
		} else {
			// For each row, add the table row tag
			$html .= '<tr>';
			// Start another loop just for good measure. just kidding, we need this loop for the columns within the rows.
			foreach ( $row as $cellindx => $cell ) {
				$col_num_str = (string) ( $cellindx + 1 );
				// For each col item, insert the table data tag and put the data inside it.
				if ( ! isset( $filteredCols[ $col_num_str ] ) ) {
					if ( $myTableResponsiveStyle === 'stack' ) {
						$header_title = $process_cell( $table_data[0][ $cellindx ] ?? '' );
						$html        .= "<td><span class='stackedheadtitlejt' style='font-weight:bold;'>" . $header_title . ':</span><br>' . $process_cell( $cell ) . '</td>';
					} else {
						$html .= '<td>' . $process_cell( $cell ) . '</td>';
					}
				}
			}
			// close our tr so the HTML inspectors happy. Just kidding this is important.
			$html .= '</tr>';
		}
	}

	$html .= '</tbody>';
	// Finalize our HTML
	$html .= '</table>';

	$html .= '</div>';

	if ( $showTableTitle === 'true' && isset( $showTableTitlePos[0] ) && $showTableTitlePos[0] === 'bottom' ) {
		$html .= "<div id='jtFooterHolder-" . $jtrt_settings['id'] . "'>";
		$html .= "<h3 style='margin-top:0;margin-bottom:14px;text-align:" . ( $showTableTitlePos[1] ?? '' ) . ";'>" . $myTableTitle . '</h3>';
		$html .= '</div>';
	}

	$html .= '</div>';

	if ( $myTableResponsiveStyle === 'footable' ) {
		wp_enqueue_script( 'jtbackendfrontendfoo-js', plugin_dir_url( __FILE__ ) . '../../public/js/vendor/footable.min.js', array( 'jquery' ), '4.0', false );
		wp_enqueue_style( 'jtbackendfrontendss-jskka12', plugin_dir_url( __FILE__ ) . '../../public/css/font-awesome.min.css', array(), '4.0', 'all' );
		wp_enqueue_style( 'jtbackendfrontendss-jskk', plugin_dir_url( __FILE__ ) . '../../public/css/footable.standalone.min.css', array(), '4.0', 'all' );
	} elseif ( $myTableResponsiveStyle === 'scroll' || $myTableResponsiveStyle === 'stack' ) {
		if ( $myjttableFiltering === 'true' || $myjttablePaging === 'true' || $myjttableSorting === 'true' ) {
			wp_enqueue_style( 'jtbackendfrontendss-jskka', 'https://cdn.datatables.net/1.10.12/css/jquery.dataTables.min.css', array(), '4.0', 'all' );
			wp_register_script( 'jtbackendfrontend-js-dtb', 'https://cdn.datatables.net/1.10.12/js/jquery.dataTables.min.js', array( 'jquery' ), '4.0', false );

			$translation_array = array(
				'next_string'   => __( 'Next', 'jtrt-responsive-tables' ),
				'prev_string'   => __( 'Prev', 'jtrt-responsive-tables' ),
				'search_string' => __( 'Search', 'jtrt-responsive-tables' ),
				'emptyTable'    => __( 'No data available in table', 'jtrt-responsive-tables' ),
				'info'          => __( 'Showing _START_ to _END_ of _TOTAL_ entries', 'jtrt-responsive-tables' ),
				'infoEmpty'     => __( 'Showing 0 to 0 of 0 entries', 'jtrt-responsive-tables' ),
				'infoFiltered'  => __( '(filtered from _MAX_ total entries)', 'jtrt-responsive-tables' ),
				'lengthMenu'    => __( 'Show _MENU_ entries', 'jtrt-responsive-tables' ),
				'zeroRecords'   => __( 'No matching records found', 'jtrt-responsive-tables' ),
				'last'          => __( 'Last', 'jtrt-responsive-tables' ),
				'first'         => __( 'First', 'jtrt-responsive-tables' ),
			);
			wp_localize_script( 'jtbackendfrontend-js-dtb', 'translation_for_frontend', $translation_array );
			wp_enqueue_script( 'jtbackendfrontend-js-dtb' );
		}
	}
	wp_enqueue_style( 'jtbackendfrontend-css', plugin_dir_url( __FILE__ ) . '../../public/css/jtrt-responsive-tables-public.css', array(), '4.0', 'all' );
	wp_enqueue_script( 'jtbackendfrontend-js', plugin_dir_url( __FILE__ ) . '../../public/js/jtrt-responsive-tables-public.js', array( 'jquery' ), '4.0', false );

	$custom_css = '';

	if ( isset( $table_post_meta['jtTableEnableColHighlight'] ) ) {
		$custom_css .= '.highlightCols #jtrt_table_' . $jtrt_settings['id'] . ' tbody td.jtrt-col-hoveredOver { background:' . ( $table_post_meta['jtTableEnableColHighlightcol'] ?? '' ) . ' !important; }';
	}

	if ( isset( $table_post_meta['jtTableEnableRowHighlight'] ) ) {
		$custom_css .= '.highlightRows #jtrt_table_' . $jtrt_settings['id'] . ' tbody tr:hover td { background:' . ( $table_post_meta['jtTableEnableRowHighlightcol'] ?? '' ) . ' !important; }';
	}

	wp_add_inline_style( 'jtbackendfrontend-css', $custom_css );

	// Blast off! We've done our part here in the server, Javascript will handle the rest.
	return $html;
}

// Add our nifty little shortcode for use.
add_shortcode( 'jtrt_tables', 'jtrt_shortcode_table' );
