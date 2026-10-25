<?php

declare(strict_types=1);

/**
 * HTML tags and attributes allowed inside table cell content.
 *
 * Shared by the metabox save handler and the shortcode renderer so stored
 * and rendered cell content pass through the same wp_kses allow-list.
 *
 * @return array<string, array<string, array>> The wp_kses allow-list.
 */
function jtrt_get_allowed_cell_html(): array {
	return array(
		'a'      => array(
			'href'   => array(),
			'title'  => array(),
			'target' => array(),
		),
		'br'     => array(),
		'em'     => array(),
		'strong' => array(),
		'b'      => array(),
		'i'      => array(),
		'u'      => array(),
		'big'    => array(),
		'span'   => array(
			'style' => array(),
			'class' => array(),
		),
		'img'    => array(
			'src'    => array(),
			'alt'    => array(),
			'width'  => array(),
			'height' => array(),
			'class'  => array(),
		),
		'ul'     => array(),
		'li'     => array(),
		'ol'     => array(),
		'hr'     => array(),
		'p'      => array(
			'style' => array(),
			'class' => array(),
		),
		'div'    => array(
			'style' => array(),
			'class' => array(),
		),
		'table'  => array(
			'class' => array(),
			'style' => array(),
		),
		'thead'  => array(),
		'tbody'  => array(),
		'tr'     => array(),
		'td'     => array(
			'colspan' => array(),
			'rowspan' => array(),
			'style'   => array(),
			'class'   => array(),
		),
		'th'     => array(
			'colspan' => array(),
			'rowspan' => array(),
			'style'   => array(),
			'class'   => array(),
		),
	);
}

/**
 * Index table cell entries (cell meta or formula results) by their position.
 *
 * Entries without integer `row` and `col` cannot be placed on a cell and are skipped.
 *
 * @param array $entries List of entries, each with `row` and `col` keys.
 * @return array<string, array> Entries keyed by "{row}:{col}".
 */
function jtrt_index_cells_by_position( array $entries ): array {
	$indexed = array();
	foreach ( $entries as $entry ) {
		if ( ! is_array( $entry ) || ! is_int( $entry['row'] ?? null ) || ! is_int( $entry['col'] ?? null ) ) {
			continue;
		}
		$indexed[ $entry['row'] . ':' . $entry['col'] ] = $entry;
	}
	return $indexed;
}

/**
 * Build the escaped HTML attribute string for a single table cell from its editor meta.
 *
 * Produces the same classes, inline styles and FooTable data attributes the editor meta
 * describes: `className` tokens, a `border-{side}` class per visible border side with
 * the last visible side's width and color, `jtcellstyle` declarations, `data-breakpoints`
 * with `data-title`, and `data-type`.
 *
 * @param array  $meta    Cell meta entry from the table data (index 1), or an empty array.
 * @param string $content Final cell HTML, used for the FooTable `data-title`.
 * @return string Attribute string with a leading space, or an empty string.
 */
function jtrt_build_cell_attributes( array $meta, string $content ): string {
	$classes = preg_split( '/\s+/', is_string( $meta['className'] ?? null ) ? $meta['className'] : '', -1, PREG_SPLIT_NO_EMPTY );
	$styles  = array();

	$borders = $meta['borders'] ?? null;
	if ( is_array( $borders ) ) {
		foreach ( $borders as $side => $border ) {
			if ( ! in_array( $side, array( 'top', 'right', 'bottom', 'left' ), true ) || ! is_array( $border ) || array() === $border || ! empty( $border['hide'] ) ) {
				continue;
			}
			$classes[]              = 'border-' . $side;
			$styles['border-width'] = is_scalar( $border['width'] ?? null ) ? (string) $border['width'] : '';
			$styles['border-color'] = is_scalar( $border['color'] ?? null ) ? (string) $border['color'] : '';
		}
	}

	$cell_style = $meta['jtcellstyle'] ?? null;
	if ( is_array( $cell_style ) ) {
		foreach ( $cell_style as $property => $value ) {
			if ( is_scalar( $value ) ) {
				$styles[ (string) $property ] = (string) $value;
			}
		}
	}

	$attributes = array();

	$class_attr = implode( ' ', array_unique( array_filter( array_map( 'sanitize_html_class', $classes ) ) ) );
	if ( '' !== $class_attr ) {
		$attributes['class'] = $class_attr;
	}

	$declarations = array();
	foreach ( $styles as $property => $value ) {
		if ( '' !== $value ) {
			$declarations[] = $property . ':' . $value;
		}
	}
	$style_attr = safecss_filter_attr( implode( ';', $declarations ) );
	if ( '' !== $style_attr ) {
		$attributes['style'] = $style_attr;
	}

	$breakpoints = $meta['jtfootablebps'] ?? null;
	if ( is_array( $breakpoints ) ) {
		$breakpoint_names               = array_map( 'strval', array_filter( $breakpoints, 'is_scalar' ) );
		$attributes['data-breakpoints'] = implode( ' ', array_filter( $breakpoint_names, static fn( string $name ): bool => '' !== $name ) );
		$attributes['data-title']       = $content;
	}

	$column_type = $meta['jtfootabcoltype'] ?? null;
	if ( is_scalar( $column_type ) && '' !== (string) $column_type ) {
		$attributes['data-type'] = (string) $column_type;
	}

	$output = '';
	foreach ( $attributes as $name => $value ) {
		$output .= ' ' . $name . '="' . esc_attr( $value ) . '"';
	}
	return $output;
}

/**
 * Version of the shortcode HTML output, part of the transient cache key.
 *
 * Bump it whenever the rendered markup changes so HTML cached by a previous
 * renderer is never served.
 */
const JTRT_TABLE_RENDER_VERSION = '3';

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

	$table_id = (int) $jtrt_settings['id'];
	if ( $table_id <= 0 ) {
		return '';
	}

	$table_post_meta = get_post_meta( $table_id, 'jtrt_data_settings', true ); // get the table meta options

	if ( ! is_array( $table_post_meta ) || ! isset( $table_post_meta['tabledata'] ) ) {
		return 'Unfortunately we could not locate the table you\'re looking for.';
	}

	// Generate a unique cache key based on ID, filters, and cache version.
	$cache_version = get_post_meta( $table_id, '_jtrt_cache_version', true );
	$cache_key     = 'jtrt_cache_' . JTRT_TABLE_RENDER_VERSION . '_' . $table_id . '_' . $cache_version . '_' . md5( (string) $jtrt_settings['filterrows'] . (string) $jtrt_settings['filtercols'] );
	$html          = get_transient( $cache_key );

	if ( $html === false ) {
		$table_data_json = json_decode( (string) $table_post_meta['tabledata'], true );
		$table_data      = $table_data_json[0] ?? null;
		$cell_meta       = jtrt_index_cells_by_position( is_array( $table_data_json[1] ?? null ) ? $table_data_json[1] : array() );
		$cell_values     = jtrt_index_cells_by_position( is_array( $table_data_json[3] ?? null ) ? $table_data_json[3] : array() );

		if ( ! $table_data ) {
			return 'Unfortunately we could not locate the table you\'re looking for.';
		}

		$showTableTitle    = isset( $table_post_meta['jtShowTableTitle'] ) ? 'true' : 'false';
		$showTableTitlePos = explode( ',', (string) ( $table_post_meta['jtShowTableTitlePos'] ?? '' ) );
		$myTableTitle      = get_the_title( $table_id );

		$myTableResponsiveStyle = (string) ( $table_post_meta['jtTableResponsiveStyle'] ?? '' );
		$myTableStackPrefWidth  = (string) ( $table_post_meta['jtStackPrefWidth'] ?? '' );

		$myTableCustomClass = esc_textarea( (string) ( $table_post_meta['jtTableCustomClass'] ?? '' ) );

		$myTableHoverRows = isset( $table_post_meta['jtTableEnableRowHighlight'] ) ? 'highlightRows' : '';
		$myTableHoverCols = isset( $table_post_meta['jtTableEnableColHighlight'] ) ? 'highlightCols' : '';

		$myjttableFiltering = isset( $table_post_meta['jtTableEnableFilters'] ) ? 'true' : 'false';
		$myjttableSorting   = isset( $table_post_meta['jtTableEnableSorting'] ) ? 'true' : 'false';
		$myjttablePaging    = isset( $table_post_meta['jtTableEnablePaging'] ) ? 'true' : 'false';
		$myjttablePagingCnt = (string) ( $table_post_meta['jtTableEnablePagingCnt'] ?? '10' );

		$paging_menu_raw     = (string) ( $table_post_meta['jtTablePagingMenu'] ?? '' );
		$myjttablePagingMenu = ( $paging_menu_raw !== '' && is_array( explode( ',', $paging_menu_raw ) ) ) ? $paging_menu_raw : '10,25,50,100';

		$myjtbpfootab = array();
		if ( $myTableResponsiveStyle === 'footable' ) {
			$myjtbpfootab['xlarge'] = (string) ( $table_post_meta['jtFootableBPxlarge'] ?? '' );
			$myjtbpfootab['large']  = (string) ( $table_post_meta['jtFootableBPlarge'] ?? '' );
			$myjtbpfootab['medium'] = (string) ( $table_post_meta['jtFootableBPmedium'] ?? '' );
			$myjtbpfootab['small']  = (string) ( $table_post_meta['jtFootableBPsmall'] ?? '' );
			$myjtbpfootab['xsmall'] = (string) ( $table_post_meta['jtFootableBPxsmall'] ?? '' );
		}

		$html = "<div class='jtrt_table_MotherShipContainer'>";

		if ( $showTableTitle === 'true' && isset( $showTableTitlePos[0] ) && $showTableTitlePos[0] === 'top' ) {
			$html .= "<div id='jtHeaderHolder-" . $table_id . "'><h3 style='margin-top:24px;margin-bottom:14px;text-align:" . ( $showTableTitlePos[1] ?? '' ) . ";'>" . $myTableTitle . '</h3>';
			$html .= '</div>';
		}

		$html .= "<div class='jtTableContainer jtrespo-" . $myTableResponsiveStyle . ' ' . $myTableHoverRows . ' ' . $myTableHoverCols . "' " . ( $myTableResponsiveStyle === 'stack' ? "data-jtrt-stack-width='" . $myTableStackPrefWidth . "'" : '' ) . '>';

		// We can only return once, so let's build our html!
		$html .= "<table id='jtrt_table_" . $table_id . "' data-sorting='" . $myjttableSorting . "' data-paging='" . $myjttablePaging . "' data-paging-size='" . $myjttablePagingCnt . "' data-paging-menu='" . $myjttablePagingMenu . "' data-filtering='" . $myjttableFiltering . "' data-jtrt-table-id='" . $table_id . "' data-jtrt-breakpoints='" . esc_attr( wp_json_encode( $myjtbpfootab ) ) . "' class='jtrt-table " . $myTableCustomClass . " ' >";

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

		$allowed_cell_html = jtrt_get_allowed_cell_html();

		/**
		 * Resolve the final HTML of a cell: the computed formula result when present, else the processed raw value.
		 *
		 * @param int   $row  Original row index.
		 * @param int   $col  Original column index.
		 * @param mixed $cell Raw cell value.
		 * @return string Cell HTML.
		 */
		$resolve_content = static function ( int $row, int $col, $cell ) use ( $cell_values, $allowed_cell_html, $process_cell ): string {
			$value = $cell_values[ $row . ':' . $col ]['val'] ?? null;
			if ( is_scalar( $value ) ) {
				return wp_kses( (string) $value, $allowed_cell_html );
			}
			return $process_cell( $cell );
		};

		$stack_header_titles = array();
		if ( 'stack' === $myTableResponsiveStyle && is_array( $table_data[0] ?? null ) ) {
			foreach ( $table_data[0] as $cellindx => $cell ) {
				$stack_header_titles[ $cellindx ] = $resolve_content( 0, (int) $cellindx, $cell );
			}
		}

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
						$content = $resolve_content( (int) $indx, (int) $cellindx, $cell );
						$html   .= '<th' . jtrt_build_cell_attributes( $cell_meta[ $indx . ':' . $cellindx ] ?? array(), $content ) . '>' . $content . '</th>';
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
						$content    = $resolve_content( (int) $indx, (int) $cellindx, $cell );
						$attributes = jtrt_build_cell_attributes( $cell_meta[ $indx . ':' . $cellindx ] ?? array(), $content );
						if ( $myTableResponsiveStyle === 'stack' ) {
							$html .= '<td' . $attributes . "><span class='stackedheadtitlejt' style='font-weight:bold;'>" . ( $stack_header_titles[ $cellindx ] ?? '' ) . ':</span><br>' . $content . '</td>';
						} else {
							$html .= '<td' . $attributes . '>' . $content . '</td>';
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
			$html .= "<div id='jtFooterHolder-" . $table_id . "'>";
			$html .= "<h3 style='margin-top:0;margin-bottom:14px;text-align:" . ( $showTableTitlePos[1] ?? '' ) . ";'>" . $myTableTitle . '</h3>';
			$html .= '</div>';
		}

		$html .= '</div>';

		// Set transient for 12 hours.
		set_transient( $cache_key, $html, 12 * HOUR_IN_SECONDS );
	}

	// Always enqueue necessary assets.
	$plugin_name            = 'jtrt-responsive-tables';
	$myTableResponsiveStyle = (string) ( $table_post_meta['jtTableResponsiveStyle'] ?? '' );
	$myjttableFiltering     = isset( $table_post_meta['jtTableEnableFilters'] ) ? 'true' : 'false';
	$myjttableSorting       = isset( $table_post_meta['jtTableEnableSorting'] ) ? 'true' : 'false';
	$myjttablePaging        = isset( $table_post_meta['jtTableEnablePaging'] ) ? 'true' : 'false';

	if ( $myTableResponsiveStyle === 'footable' ) {
		wp_enqueue_script( $plugin_name . '-footable' );
		wp_enqueue_style( $plugin_name . '-font-awesome' );
		wp_enqueue_style( $plugin_name . '-footable' );
	} elseif ( $myTableResponsiveStyle === 'scroll' || $myTableResponsiveStyle === 'stack' ) {
		if ( $myjttableFiltering === 'true' || $myjttablePaging === 'true' || $myjttableSorting === 'true' ) {
			wp_enqueue_style( $plugin_name . '-datatables' );

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
			wp_localize_script( $plugin_name . '-datatables', 'translation_for_frontend', $translation_array );
			wp_enqueue_script( $plugin_name . '-datatables' );
		}
	}
	wp_enqueue_style( $plugin_name );
	wp_enqueue_script( $plugin_name );

	$custom_css = '';
	if ( isset( $table_post_meta['jtTableEnableColHighlight'] ) ) {
		$custom_css .= '.highlightCols #jtrt_table_' . $table_id . ' tbody td.jtrt-col-hoveredOver { background:' . ( $table_post_meta['jtTableEnableColHighlightcol'] ?? '' ) . ' !important; }';
	}
	if ( isset( $table_post_meta['jtTableEnableRowHighlight'] ) ) {
		$custom_css .= '.highlightRows #jtrt_table_' . $table_id . ' tbody tr:hover td { background:' . ( $table_post_meta['jtTableEnableRowHighlightcol'] ?? '' ) . ' !important; }';
	}
	wp_add_inline_style( $plugin_name, $custom_css );

	return $html;
}

// Add our nifty little shortcode for use.
add_shortcode( 'jtrt_tables', 'jtrt_shortcode_table' );
