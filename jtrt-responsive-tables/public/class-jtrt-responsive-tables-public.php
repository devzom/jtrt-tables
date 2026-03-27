<?php

declare(strict_types=1);

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://johntendik.github.io
 * @since      1.0.0
 *
 * @package    Jtrt_Responsive_Tables
 * @subpackage Jtrt_Responsive_Tables/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Jtrt_Responsive_Tables
 * @subpackage Jtrt_Responsive_Tables/public
 * @author     John Tendik <johntendik@hotmail.com>
 */
class Jtrt_Responsive_Tables_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private string $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private string $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( string $plugin_name, string $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles(): void {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Jtrt_Responsive_Tables_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Jtrt_Responsive_Tables_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_register_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/jtrt-responsive-tables-public.css', array(), $this->version, 'all' );
		wp_register_style( $this->plugin_name . '-footable', plugin_dir_url( __FILE__ ) . 'css/footable.standalone.min.css', array( 'dashicons' ), '2.0.3', 'all' );
		wp_register_style( $this->plugin_name . '-datatables', 'https://cdn.datatables.net/1.10.12/css/jquery.dataTables.min.css', array(), '1.10.12', 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts(): void {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Jtrt_Responsive_Tables_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Jtrt_Responsive_Tables_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_register_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/jtrt-responsive-tables-public.js', array( 'jquery' ), $this->version, true );
		wp_register_script( $this->plugin_name . '-footable', plugin_dir_url( __FILE__ ) . 'js/vendor/footable.min.js', array( 'jquery' ), '2.0.3', true );
		wp_register_script( $this->plugin_name . '-datatables', 'https://cdn.datatables.net/1.10.12/js/jquery.dataTables.min.js', array( 'jquery' ), '1.10.12', true );
	}
}
