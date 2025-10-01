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
        
        // ========== PERBAIKI INI: UPDATE SUPPORTS ARRAY ========== //
        $this->supports = array(
            'products',
            // HPOS compatibility - tambahkan refunds jika mau support
            // 'refunds',
        );
        // ========== END OF UPDATE ========== //

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
        
        // ========== TAMBAHKAN HOOK UNTUK ORDER DETAILS ========== //
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_whatsapp_button_order_details' ), 10, 1 );
        // ======================================================== //
        
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
            'description' => array(
                'title'       => __( 'Description', 'wc-whatsapp-payment' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-whatsapp-payment' ),
                'default'     => __( 'Pay via WhatsApp. You will be redirected to WhatsApp to complete your payment.', 'wc-whatsapp-payment' ),
                'desc_tip'    => true,
            ),
            'instructions' => array(
                'title'       => __( 'Instructions', 'wc-whatsapp-payment' ),
                'type'        => 'textarea',
                'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-whatsapp-payment' ),
                'default'     => __( 'Please contact us via WhatsApp to complete your payment.', 'wc-whatsapp-payment' ),
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
    private function generate_whatsapp_message( $order ) {
        $order_items = $order->get_items();
        $website_name = get_bloginfo('name');
        
        $message = "Halo, saya ingin memesan dari {$website_name}:%0A%0A";
        
        foreach ( $order_items as $item ) {
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();
            $total = $item->get_total();
            $formatted_total = number_format( $total, 0, ',', '.' );
            
            $message .= "• {$product_name} x{$quantity} - Rp {$formatted_total}%0A ";
        }
        
        $order_total = $order->get_total();
        $formatted_order_total = number_format( $order_total, 0, ',', '.' );
        
        $message .= " Total: Rp {$formatted_order_total} ";
        $message .= " Order ID: " . $order->get_order_number();
        $message .= " Nama: " . $order->get_billing_first_name() . " " . $order->get_billing_last_name();
        $message .= " Email: " . $order->get_billing_email();
        $message .= " Telepon: " . $order->get_billing_phone();
        $message .= " Alamat: " . $order->get_billing_address_1();
        
        return $message;
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
     * Display WhatsApp button on order details page
     */
    public function display_whatsapp_button_order_details( $order_id ) {
        $order = wc_get_order( $order_id );
        
        // Only show for WhatsApp payment method
        if ( $order->get_payment_method() !== $this->id ) {
            return;
        }
        
        // Only show for specific statuses (pending, on-hold, failed)
        $allowed_statuses = array( 'pending', 'on-hold', 'failed', 'checkout-draft' );
        if ( ! in_array( $order->get_status(), $allowed_statuses ) ) {
            return;
        }
        
        $whatsapp_number = $this->whatsapp_number;
        $message = $order->get_meta( '_whatsapp_payment_message' );
        
        // Generate message if not exists
        if ( ! $message ) {
            $message = $this->generate_whatsapp_message( $order );
            $order->update_meta_data( '_whatsapp_payment_message', $message );
            $order->save();
        }
        
        if ( $message && $whatsapp_number ) {
            $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . $message;
            
            echo '<div class="woocommerce-whatsapp-payment-order-details" style="background: #f8f8f8; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #25D366;">';
            echo '<h3>' . __( 'Complete Your Payment via WhatsApp', 'wc-whatsapp-payment' ) . '</h3>';
            echo '<p>' . __( 'You haven\'t completed your payment yet. Click the button below to contact us via WhatsApp and complete your payment:', 'wc-whatsapp-payment' ) . '</p>';
            echo '<a href="' . esc_url( $whatsapp_url ) . '" target="_blank" class="button alt" style="background: #25D366; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; border: none;">';
            echo __( '💬 Chat via WhatsApp to Pay', 'wc-whatsapp-payment' );
            echo '</a>';
            echo '<p style="margin-top: 10px; font-size: 0.9em; color: #666;">';
            echo __( 'If you already paid via WhatsApp, please wait for confirmation.', 'wc-whatsapp-payment' );
            echo '</p>';
            echo '</div>';
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