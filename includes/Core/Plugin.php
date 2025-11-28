<?php
namespace FCSI\Core;

class Plugin {
    public function __construct() {
        add_filter( 'fluent_cart_payment_gateways', [ $this, 'register_gateways' ] );
        add_action( 'fluent_cart_order_status_paid', [ $this, 'on_paid' ], 10, 1 );
    }

    public function register_gateways( array $list ): array {
        $list['fcsi_payfast'] = '\FCSI\Gateways\PayFast';
        return $list;
    }

    public function on_paid( $order_id ): void {
        // fire WhatsApp “payment confirmed”
        $order = fluentcart_get_order( $order_id );
        $phone = $this->get_customer_wa( $order );
        if ( ! $phone ) { return; }

        $msg = sprintf(
            "✅ Payment confirmed for order #%s\nHi %s, we received your payment and will ship soon.",
            $order->get_id(),
            $order->get_billing_first_name()
        );
        try {
            $token   = get_option( 'fcsi_wa_access_token' );
            $phoneId = get_option( 'fcsi_wa_phone_id' );
            ( new \FCSI\WhatsApp\CloudClient( $token, $phoneId ) )->sendText( $phone, $msg );
        } catch ( \Exception $e ) {
            error_log( 'FCSI WA: ' . $e->getMessage() );
        }
    }

    private function get_customer_wa( $order ): ?string {
        // fallback chain: custom meta → billing phone
        $cc = '27'; // South-Africa
        $raw = get_post_meta( $order->get_id(), '_fcsi_whatsapp', true ) ?: $order->get_billing_phone();
        $digits = preg_replace( '/\D+/', '', $raw );
        if ( strlen( $digits ) === 10 && $digits[0] === '0' ) {
            $digits = substr( $digits, 1 );
        }
        return strlen( $digits ) === 9 ? $cc . $digits : null;
    }
}
