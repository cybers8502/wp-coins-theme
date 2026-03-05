<?php

namespace Coins\Security;

class CorsService
{
    public function __construct() {
        add_action('rest_pre_serve_request', [$this, 'handleCors']);
    }

    public function handleCors(): void
    {

        $origin = get_http_origin();

        $allowed = [
            'https://brutmaps.com',
            'https://brutmapsdev.cybers.pro',
            'http://localhost:3033',
        ];

        if (in_array($origin, $allowed)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Methods: POST, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
        }
    }
}
