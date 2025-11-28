<?php
namespace FCSI\WhatsApp;

class CloudClient {
    private string $token;
    private string $phone_id;
    private string $api_base = 'https://graph.facebook.com/v20.0';

    public function __construct( string $access_token, string $phone_number_id ) {
        $this->token   = $access_token;
        $this->phone_id = $phone_number_id;
    }

    /**
     * Send a text message.
     *
     * @param string $to   E.164 (e.g. 27821234567)
     * @param string $text ≤ 4096 chars
     * @return string      message id
     * @throws \Exception
     */
    public function sendText( string $to, string $text ): string {
        $body = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => [ 'body' => $text ],
        ];
        return $this->post( "/{$this->phone_id}/messages", $body )['messages'][0]['id'];
    }

    /* ---------- internals ---------- */
    private function post( string $endpoint, array $json ): array {
        $resp = wp_remote_post(
            $this->api_base . $endpoint,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( $json ),
                'timeout' => 20,
            ]
        );

        if ( is_wp_error( $resp ) ) {
            throw new \Exception( $resp->get_error_message() );
        }
        $code = wp_remote_retrieve_response_code( $resp );
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );

        if ( $code >= 300 ) {
            throw new \Exception( $body['error']['message'] ?? "HTTP $code" );
        }
        return $body;
    }
}
