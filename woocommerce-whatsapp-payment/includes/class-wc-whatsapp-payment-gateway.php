<?php
/**
 * WhatsApp Payment Gateway
 *
 * @package WooCommerce_WhatsApp_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_WhatsApp_Payment_Gateway extends WC_Payment_Gateway {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'whatsapp_payment';
        $this->icon               = '';
        $this->has_fields         = false;
        $this->method_title       = __( 'WhatsApp Payment', 'wc-whatsapp-payment' );
        $this->method_description = __( 'Allow customers to pay via WhatsApp', 'wc-whatsapp-payment' );
        $this->supports           = array( 'products' );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option( 'title' );
        $this->description  = $this->get_option( 'description' );
        $this->whatsapp_number = $this->get_option( 'whatsapp_number' );
        $this->instructions = $this->get_option( 'instructions' );

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
        
        // Customer emails
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
public function init_form_fields() {
    $this->form_fields = array(
        'enabled' => array(
            'title'   => __( 'Enable/Disable', 'wc-whatsapp-payment' ),
            'type'    => 'checkbox',
            'label'   => __( 'Enable WhatsApp Payment', 'wc-whatsapp-payment' ),
            'default' => 'yes'
        ),
        'title' => array(
            'title'       => __( 'Title', 'wc-whatsapp-payment' ),
            'type'        => 'text',
            'description' => __( 'This controls the title which the user sees during checkout.', 'wc-whatsapp-payment' ),
            'default'     => __( 'WhatsApp Payment', 'wc-whatsapp-payment' ),
            'desc_tip'    => true,
        ),
        'whatsapp_number' => array(
            'title'       => __( 'WhatsApp Number', 'wc-whatsapp-payment' ),
            'type'        => 'text',
            'description' => __( 'Enter your WhatsApp number with country code (e.g., 6281234567890)', 'wc-whatsapp-payment' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'message_template' => array(
            'title'       => __( 'Message Template', 'wc-whatsapp-payment' ),
            'type'        => 'textarea',
            'description' => __( 'Customize the WhatsApp message template. Use {website_name}, {order_id}, {customer_name}, {total}', 'wc-whatsapp-payment' ),
            'default'     => __( 'Halo, saya ingin memesan dari {website_name}:\n\n{order_items}\n\nTotal: {total}\nOrder ID: {order_id}\nNama: {customer_name}\nTelepon: {customer_phone}\nAlamat: {customer_address}', 'wc-whatsapp-payment' ),
            'desc_tip'    => true,
        ),
        'description' => array(
            'title'       => __( 'Description', 'wc-whatsapp-payment' ),
            'type'        => 'textarea',
            'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-whatsapp-payment' ),
            'default'     => __( 'Pay via WhatsApp. You will be redirected to WhatsApp to complete your payment.', 'wc-whatsapp-payment' ),
            'desc_tip'    => true,
        ),
    );
}

    /**
     * Process the payment
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        // Mark as on-hold (we're awaiting the payment)
        $order->update_status( 'on-hold', __( 'Awaiting WhatsApp payment', 'wc-whatsapp-payment' ) );

        // Reduce stock levels
        wc_reduce_stock_levels( $order_id );

        // Remove cart
        WC()->cart->empty_cart();

        // Generate WhatsApp message
        $whatsapp_message = $this->generate_whatsapp_message( $order );
        
        // Store WhatsApp message in order meta
        $order->update_meta_data( '_whatsapp_payment_message', $whatsapp_message );
        $order->save();

        // Return thankyou redirect
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order )
        );
    }

    /**
     * Generate WhatsApp message
     */
/**
 * Generate WhatsApp message
 */
private function generate_whatsapp_message( $order ) {
    $website_name = get_bloginfo('name');
    $website_url = get_site_url();
    
    // Get message template from settings
    $message_template = $this->get_option( 'message_template', 
        "Halo, saya ingin memesan dari {website_name}:\n\n{order_items}\n\nTotal: {total}\nOrder ID: {order_id}\nNama: {customer_name}\nTelepon: {customer_phone}\nAlamat: {customer_address}"
    );
    
    // Build order items
    $order_items_text = "";
    foreach ( $order->get_items() as $item ) {
        $product_name = $item->get_name();
        $quantity = $item->get_quantity();
        $total = $item->get_total();
        $formatted_total = number_format( $total, 0, ',', '.' );
        
        $order_items_text .= "• {$product_name} x{$quantity} - Rp {$formatted_total}\n";
    }
    
    // Replace placeholders
    $message = str_replace(
        array(
            '{website_name}',
            '{website_url}', 
            '{order_id}',
            '{customer_name}',
            '{customer_phone}',
            '{customer_email}',
            '{customer_address}',
            '{total}',
            '{order_items}'
        ),
        array(
            $website_name,
            $website_url,
            $order->get_order_number(),
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            $order->get_billing_phone(),
            $order->get_billing_email(),
            $order->get_billing_address_1(),
            'Rp ' . number_format( $order->get_total(), 0, ',', '.' ),
            $order_items_text
        ),
        $message_template
    );
    
    return urlencode( $message );
}

/**
 * Format price without HTML tags
 */
private function format_price_clean( $price ) {
    // Clean number formatting tanpa HTML
    $clean_price = number_format( $price, 0, ',', '.' );
    return $clean_price;
}

    /**
     * Output for the order received page.
     */
    public function thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );
        $whatsapp_number = $this->whatsapp_number;
        $message = $order->get_meta( '_whatsapp_payment_message' );
        
        if ( $message && $whatsapp_number ) {
            $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . $message;
            
            echo '<div class="woocommerce-whatsapp-payment-instructions" style="background: #f8f8f8; padding: 20px; border-radius: 5px; margin: 20px 0;">';
            echo '<h3>' . __( 'Complete Your Payment via WhatsApp', 'wc-whatsapp-payment' ) . '</h3>';
            echo '<p>' . __( 'Please click the button below to contact us via WhatsApp and complete your payment:', 'wc-whatsapp-payment' ) . '</p>';
            echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" class="button alt" style="background: #25D366; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">';
            echo __( 'Chat via WhatsApp', 'wc-whatsapp-payment' );
            echo '</a>';
            echo '</div>';
        }
        
        if ( $this->instructions ) {
            echo wpautop( wptexturize( $this->instructions ) );
        }
    }

    /**
     * Add content to the WC emails.
     */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
        if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
            echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
        }
    }
}
?>