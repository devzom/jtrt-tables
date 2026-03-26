<?php

declare(strict_types=1);

/**
 * Check if the current screen is the JTRT tables post type.
 *
 * @return bool
 */
function CheckIfJTRTExists(): bool {
	$currentPage = get_current_screen();
	return ( ( $currentPage->id ?? '' ) === 'jtrt_tables_post' );
}

/**
 * Callback for the JTRT meta box.
 *
 * @param WP_Post $post The post object.
 * @return void
 */
function jtrt_meta_box_html_callback( WP_Post $post ): void {
	require_once plugin_dir_path( __FILE__ ) . 'templates/jtrt-responsive-tables-post-meta-1-display.php';
}

require_once plugin_dir_path( __FILE__ ) . 'jtrt-adminClass-shortcode.php';

// functions to display custom columns on our custom post type table
add_filter( 'manage_jtrt_tables_post_posts_columns', 'bs_event_table_head' );

/**
 * Add shortcode column to the tables list.
 *
 * @param array $defaults Default columns.
 * @return array
 */
function bs_event_table_head( array $defaults ): array {
	$defaults['short_code_jt'] = 'Shortcode';
	return $defaults;
}

add_action( 'manage_jtrt_tables_post_posts_custom_column', 'bs_event_table_content', 10, 2 );

/**
 * Display the shortcode in the custom column.
 *
 * @param string $column_name Column name.
 * @param int    $post_id Post ID.
 * @return void
 */
function bs_event_table_content( string $column_name, int $post_id ): void {
	if ( $column_name === 'short_code_jt' ) {
		echo "[jtrt_tables id='" . $post_id . "']";
	}
}

