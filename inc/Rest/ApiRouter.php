<?php

namespace Coins\Rest;

class ApiRouter
{

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $priceController      = new Controllers\CoinPriceController();
        $collectionController = new Controllers\CoinCollectionController();

        register_rest_route('coins/v1', '/coins/(?P<id>\d+)/price-history', [
            'methods'             => 'GET',
            'callback'            => [$priceController, 'getPriceHistory'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => fn($v) => is_numeric($v) && $v > 0,
                ],
            ],
        ]);

        // Collection — list & add
        register_rest_route('coins/v1', '/collection', [
            [
                'methods'             => 'GET',
                'callback'            => [$collectionController, 'getCollection'],
                'permission_callback' => fn() => is_user_logged_in(),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$collectionController, 'addItem'],
                'permission_callback' => fn() => is_user_logged_in(),
                'args'                => [
                    'coin_id' => [
                        'required'          => true,
                        'validate_callback' => fn($v) => is_numeric($v) && $v > 0,
                    ],
                    'quantity' => [
                        'required'          => false,
                        'default'           => 1,
                        'validate_callback' => fn($v) => is_numeric($v) && $v >= 1,
                    ],
                    'purchase_price' => [
                        'required'          => false,
                        'validate_callback' => fn($v) => is_numeric($v) && $v >= 0,
                    ],
                ],
            ],
        ]);

        // Collection — update & delete single item
        register_rest_route('coins/v1', '/collection/(?P<id>\d+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [$collectionController, 'updateItem'],
                'permission_callback' => fn() => is_user_logged_in(),
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'validate_callback' => fn($v) => is_numeric($v) && $v > 0,
                    ],
                    'quantity' => [
                        'required'          => false,
                        'validate_callback' => fn($v) => is_numeric($v) && $v >= 1,
                    ],
                    'purchase_price' => [
                        'required'          => false,
                        'validate_callback' => fn($v) => is_numeric($v) && $v >= 0,
                    ],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$collectionController, 'deleteItem'],
                'permission_callback' => fn() => is_user_logged_in(),
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'validate_callback' => fn($v) => is_numeric($v) && $v > 0,
                    ],
                ],
            ],
        ]);
    }
}