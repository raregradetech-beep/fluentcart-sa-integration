<?php
namespace FCSI\Gateways;

use FluentCart\Abstracts\PaymentGateway;

class PayFast extends PaymentGateway {
    protected $id = 'fcsi_payfast';

    public function __construct() {
        parent::__construct();
        $this->title        = 'PayFast (SA)';
        $this->description  = 'Instant EFT, card, wallet – South Africa.';
        $this->method_title = 'PayFast';
        add_action( 'wp_loaded', [ $this, 'maybe_handle_itn' ] );
    }

    /* ---------- front-end ---------- */
    public function process_payment( $order_id ): array {
        $order = fluentcart_get_order( $order_id );
        $url   = $this->get_payfast_url( $order );
        return [
            'result'   => 'success',
            'redirect' => $url,
        ];
    }

    private function get_payfast_url( $order ): string {
        $set  = [
            'merchant_id' => $this->get_option( 'merchant_id' ),
            'merchant_key'=> $this->get_option( 'merchant_key' ),
            'return_url'  => $this->get_return_url( $order ),
            'cancel_url'  => $order->get_cancel_order_url_raw(),
            'notify_url'  => add_query_arg( 'fc-listener', 'payfast-itn', home_url( '/' ) ),
            'name_first'  => $order->get_billing_first_name(),
            'name_last'   => $order->get_billing_last_name(),
            'email_address'=> $order->get_billing_email(),
            'm_payment_id'=> $order->get_id(),
            'amount'      => number_format( $order->get_total(), 2, '.', '' ),
            'item_name'   => sprintf( 'Order #%s', $order->get_id() ),
        ];
        $set['signature'] = $this->sign( $set );
        return ( $this->get_option( 'sandbox' ) === 'yes'
                ? 'https://sandbox.payfast.co.za/eng/process'
                : 'https://www.payfast.co.za/eng/process'
               ) . '?' . http_build_query( $set );
    }

    /* ---------- IPN ---------- */
    public function maybe_handle_itn() {
        if ( ! isset( $_GET['fc-listener'] ) || $_GET['fc-listener'] !== 'payfast-itn' ) { return; }
        $pf = stripslashes_deep( $_POST );
        if ( ! $this->valid_itn( $pf ) ) { wp_die( 'ITN signature fail' ); }

        $order = fluentcart_get_order( (int) $pf['m_payment_id'] );
        if ( ! $order ) { wp_die( 'Order not found' ); }

        switch ( strtolower( $pf['payment_status'] ) ) {
            case 'complete':
                $order->payment_complete( $pf['pf_payment_id'] );
                break;
            case 'failed':
                $order->update_status( 'failed', 'PayFast ITN: failed' );
                break;
        }
        echo 'OK';
        exit;
    }

    /* ---------- helpers ---------- */
    private function sign( array $set ): string {
        $flat = '';
        foreach ( $set as $k => $v ) {
            if ( in_array( $k, [ 'signature', 'encoding', 'btnSubmit' ], true ) ) { continue; }
            $flat .= $k . '=' . urlencode( trim( $v ) ) . '&';
        }
        $pass = $this->get_option( 'passphrase' );
        if ( $pass ) { $flat .= 'passphrase=' . urlencode( $pass ); } else { $flat = rtrim( $flat, '&' ); }
        return md5( $flat );
    }

    private function valid_itn( array $pf ): bool {
        return hash_equals( $pf['signature'], $this->sign( $pf ) );
    }

    /* ---------- form fields (admin) ---------- */
    public function init_form_fields(): void {
        $this->form_fields = [
            'enabled'     => [
                'title'   => 'Enable PayFast',
                'type'    => 'checkbox',
                'label'   => 'Enable',
                'default' => 'no',
            ],
            'merchant_id' => [
                'title' => 'Merchant ID',
                'type'  => 'text',
            ],
            'merchant_key'=> [
                'title' => 'Merchant Key',
                'type'  => 'text',
            ],
            'passphrase'  => [
                'title' => 'Passphrase (optional)',
                'type'  => 'password',
            ],
            'sandbox'     => [
                'title'   => 'Sandbox',
                'type'    => 'checkbox',
                'label'   => 'Use sandbox',
                'default' => 'yes',
            ],
        ];
    }
}
