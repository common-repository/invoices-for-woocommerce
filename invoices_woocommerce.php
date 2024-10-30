<?php defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
/*
Plugin Name: Invoices for WooCommerce
Plugin URI: http://wordpress.org/plugins/invoices-for-woocommerce/
Description: Easily add functionality related to taxes and issuing invoices
Author: RapidDev | Polish technology company
Author URI: https://rapiddev.pl/
License: MIT
License URI: https://opensource.org/licenses/MIT
Version: 1.0.0
Text Domain: invoices_woocommerce
Domain Path: /languages
*/
/**
 * @package WordPress
 * @subpackage Invoices for WooCommerce
 *
 * @author Leszek Pomianowski
 * @copyright Copyright (c) 2018, RapidDev
 * @link https://www.rapiddev.pl/woocommerce_tax
 * @license http://opensource.org/licenses/MIT
 */

/* ====================================================================
 * Constant
 * ==================================================================*/
	define('INVOICES_WOOCOMMERCE_VERSION', '1.0.0');
	define('INVOICES_WOOCOMMERCE_NAME', 'Invoices for WooCommerce');
	define('INVOICES_WOOCOMMERCE_PATH', plugin_dir_path( __FILE__ ));
	define('INVOICES_WOOCOMMERCE_URL', plugin_dir_url(__FILE__));
	define('INVOICES_WOOCOMMERCE_WP_VERSION', '4.9.0');
	define('INVOICES_WOOCOMMERCE_WC_VERSION', '3.4.3');

/* ====================================================================
 * Define language files
 * ==================================================================*/
	function invoices_woocommerce_languages(){
		load_plugin_textdomain( 'invoices_woocommerce', FALSE, basename(INVOICES_WOOCOMMERCE_PATH) . '/languages/' );
	}
	add_action('plugins_loaded', 'invoices_woocommerce_languages');

/* ====================================================================
 * WordPress version check
 * ==================================================================*/
	global $wp_version;
	if (version_compare($wp_version, INVOICES_WOOCOMMERCE_WP_VERSION, '>=')){

/* ====================================================================
 * If WooCommerce is Active
 * ==================================================================*/
	if (!function_exists( 'get_plugins')){
		include_once(ABSPATH.'wp-admin/includes/plugin.php');
	}
	if (is_plugin_active('woocommerce/woocommerce.php')) {

/* ====================================================================
 * WooCommerce version check
 * ==================================================================*/
		if(version_compare(get_plugins('/'.'woocommerce')['woocommerce.php']['Version'], INVOICES_WOOCOMMERCE_WC_VERSION, '>=' ) == true){

/* ====================================================================
 * Add tax field
 * ==================================================================*/
				function invoices_woocommerce_billing_tax($fields)
				{
					$fields['billing']['billing_tax'] = array(
						'label' => __('Tax number', 'invoices_woocommerce'), // Add custom field label
						'placeholder' => null, // Add custom field placeholder
						'required' => false, // if field is required or not
						'clear' => false, // add clear or not
						'type' => 'text', // add field type
					);
					return $fields;
				}
				add_filter('woocommerce_checkout_fields', 'invoices_woocommerce_billing_tax');

/* ====================================================================
 * Reorder fields
 * ==================================================================*/
				function invoices_woocommerce_order_field($fields) {
					$order = array(
							'billing_first_name', 
							'billing_last_name', 
							'billing_company',
							'billing_tax',
							'billing_country',
							'billing_address_1', 
							'billing_address_2', 
							'billing_postcode',
							'billing_city',
							'billing_phone',
							'billing_email'
					);
					foreach($order as $field)
					{
						$ordered_fields[$field] = $fields['billing'][$field];
					}
					$fields['billing'] = $ordered_fields;
					return $fields;
				}
				add_filter('woocommerce_checkout_fields', 'invoices_woocommerce_order_field');

/* ====================================================================
 * In order display
 * ==================================================================*/
				function invoices_woocommerce_show_on_orderpage($order){
					if (get_post_meta($order->get_id(), '_invoice', true) == 1) {
						$vat = __('Customer', 'invoices_woocommerce').' <span style="color:green !important;">'.__('wants', 'invoices_woocommerce').'</span> '.__('to receive an invoice', 'invoices_woocommerce');
					}else{
						$vat = __('Customer', 'invoices_woocommerce').' <span style="color:red !important;">'.__('does not want', 'invoices_woocommerce').'</span> '.__('to receive invoice', 'invoices_woocommerce');
					}
					$ret = '<div>';
					$billing_tax = get_post_meta($order->get_id(), '_billing_tax', true);
					if ($billing_tax != NULL) {
						$ret .= '<p><strong>'.__('Tax number', 'invoices_woocommerce').':</strong><br/>'.$billing_tax.'<br /></p>';
					}
					$ret .= '<p><strong>'.__('Invoice', 'invoices_woocommerce').':</strong><br/>'.$vat.'<br /></p>';
					$ret .= '</div>';
					echo $ret;
				}
				add_action( 'woocommerce_admin_order_data_after_billing_address', 'invoices_woocommerce_show_on_orderpage', 10, 1 );

/* ====================================================================
 * Order list column
 * ==================================================================*/
				function invoices_woocommerce_orders_column($columns)
				{
					$reordered_columns = array();
					foreach( $columns as $key => $column){
						$reordered_columns[$key] = $column;
						if( $key ==  'order_status' ){
							$reordered_columns['tax'] = __('Invoice', 'invoices_woocommerce');
						}
					}
					return $reordered_columns;
				}
				add_filter( 'manage_edit-shop_order_columns', 'invoices_woocommerce_orders_column', 20 );

/* ====================================================================
 * Order list content
 * ==================================================================*/
				function invoices_woocommerce_column_tax($column, $post_id)
				{
					if ('tax' != $column) return;
					if(get_post_meta($post_id, '_invoice', true) == 1) {
						$billing_tax = get_post_meta($post_id, '_billing_tax', true);
						if ($billing_tax == '') {
							echo '<span style="color:red !important;">'.__('Invalid tax number', 'invoices_woocommerce').'</span>';
						}else{
							echo '<span style="color:green !important;">'.get_post_meta($post_id, '_billing_tax', true).'</span>';
						}
						
					}else{
						echo '<span style="color:red !important;">'.__('No').'</span>';
					}
				}
				add_action( 'manage_shop_order_posts_custom_column' , 'invoices_woocommerce_column_tax', 20, 2 );

/* ====================================================================
 * Additional checkbox
 * ==================================================================*/
				add_action('woocommerce_checkout_before_terms_and_conditions', 'invoices_woocommerce_checkout_additional_checkboxes');
				function invoices_woocommerce_checkout_additional_checkboxes( ){
					?>
					<p class="form-row custom-checkboxes">
						<label class="woocommerce-form__label checkbox custom-two">
							<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"  value="1" name="vat_invoice" ><span><?php _e('I want to receive an invoice', 'invoices_woocommerce') ?></span>
						</label>
					</p>
					<?php
				}

/* ====================================================================
 * Checkout meta check
 * ==================================================================*/
				function invoices_woocommerce_custom_checkout_meta( $order_id ) {
					if ($_POST['billing_tax']){
						update_post_meta($order_id, '_billing_tax', sanitize_text_field($_POST['billing_tax']));
					}
					if ($_POST['vat_invoice']){
						if (sanitize_text_field($_POST['vat_invoice']) == '1') {
							$ret = 1;
						}else{
							$ret = 0;
						}
						update_post_meta($order_id, '_invoice', $ret);
					}
				}
				add_action('woocommerce_checkout_update_order_meta', 'invoices_woocommerce_custom_checkout_meta');

/* ====================================================================
 * WooCommerce version error
 * ==================================================================*/
		}else{
			if (!function_exists('invoices_woocommerce_wc_version_error')){
				function invoices_woocommerce_wc_version_error(){
					echo '<div class="notice notice-error"><p><strong>'.__('ERROR', 'invoices_woocommerce').'!</strong><br />'.__('The', 'invoices_woocommerce').' <i>'.INVOICES_WOOCOMMERCE_NAME.'</i> '.__('requires at least', 'invoices_woocommerce').' WooCommerce '.INVOICES_WOOCOMMERCE_WC_VERSION.'<br />'.__('You need to update your WooCommerce plugin', 'invoices_woocommerce').'.<br /><small><i>'.__('ERROR ID', 'invoices_woocommerce').': 3</i></small></p></div>';
				}
				add_action('admin_notices', 'invoices_woocommerce_wc_version_error');
			}
		}

/* ====================================================================
 * WooCommerce active error
 * ==================================================================*/
	}else{
		if (!function_exists('invoices_woocommerce_wc_active_error')){
			function invoices_woocommerce_wc_active_error(){
				echo '<div class="notice notice-error"><p><strong>'.__('ERROR', 'invoices_woocommerce').'!</strong><br />'.__('The', 'invoices_woocommerce').' <i>'.INVOICES_WOOCOMMERCE_NAME.'</i> '.__('requires an active WooCommerce plugin', 'invoices_woocommerce').'.<br />'.__('You must install or enable the WooCommerce plugin', 'invoices_woocommerce').'.<br /><small><i>'.__('ERROR ID', 'invoices_woocommerce').': 2</i></small></p></div>';
			}
			add_action('admin_notices', 'invoices_woocommerce_wc_active_error');
		}
	}

/* ====================================================================
 * WordPress version error
 * ==================================================================*/
	}else{
		if (!function_exists('invoices_woocommerce_wp_version_error')){
			function invoices_woocommerce_wp_version_error(){
				echo '<div class="notice notice-error"><p><strong>'.__('ERROR', 'invoices_woocommerce').'!</strong><br />'.__('The', 'invoices_woocommerce').' <i>'.INVOICES_WOOCOMMERCE_NAME.'</i> '.__('requires at least', 'invoices_woocommerce').' WordPress '.INVOICES_WOOCOMMERCE_WP_VERSION.'<br />'.__('You need to update your WordPress site', 'invoices_woocommerce').'.<br /><small><i>'.__('ERROR ID', 'invoices_woocommerce').': 1</i></small></p></div>';
			}
			add_action('admin_notices', 'invoices_woocommerce_wp_version_error');
		}
	}