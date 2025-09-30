<?php
/**
 * Plugin Name: WooCommerce WhatsApp Payment
 * Plugin URI: https://example.com
 * Description: Custom payment gateway for WhatsApp payments - HPOS Compatible
 * Version: 1.1.0
 * Author: Violet Gallery
 * Author URI: https://violetgalleryofficial.com
 * Text Domain: wc-whatsapp-payment
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 *
 * @package WooCommerce_WhatsApp_Payment
 */

// ========== TAMBAHKAN INI: HPOS COMPATIBILITY DECLARATION ========== //
defined( 'ABSPATH' ) || exit;

// Define plugin constants
define( 'WC_WHATSAPP_PAYMENT_VERSION', '1.1.0' );
define( 'WC_WHATSAPP_PAYMENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_WHATSAPP_PAYMENT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// HPOS Compatibility Declaration
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
// ========== END OF HPOS COMPATIBILITY ========== //

// Check if WooCommerce is active
add_action( 'plugins_loaded', 'wc_whatsapp_payment_init' );

function wc_whatsapp_payment_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    require_once WC_WHATSAPP_PAYMENT_PLUGIN_PATH . 'includes/class-wc-whatsapp-payment-gateway.php';
    
    // Add the gateway to WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'wc_whatsapp_payment_add_gateway' );
    
    function wc_whatsapp_payment_add_gateway( $gateways ) {
        $gateways[] = 'WC_WhatsApp_Payment_Gateway';
        return $gateways;
    }
}

// Activation hook
register_activation_hook( __FILE__, 'wc_whatsapp_payment_activate' );

function wc_whatsapp_payment_activate() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( __( 'Please install and activate WooCommerce before using WhatsApp Payment Gateway.', 'wc-whatsapp-payment' ) );
    }
}

// Add settings link
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_whatsapp_payment_settings_link' );

function wc_whatsapp_payment_settings_link( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=whatsapp_payment' ) . '">' . __( 'Settings', 'wc-whatsapp-payment' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
?>