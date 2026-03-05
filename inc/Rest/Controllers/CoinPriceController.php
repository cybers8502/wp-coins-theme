<?php

namespace Coins\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class CoinPriceController
{
    public function getPriceHistory(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $coin_id = (int) $request->get_param('id');

        if (!get_post($coin_id) || get_post_type($coin_id) !== 'coins') {
            return new WP_Error('not_found', 'Coin not found.', ['status' => 404]);
        }

        $query = new \WP_Query([
            'post_type'      => 'coin_price',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => 'coin_id',
                    'value' => $coin_id,
                    'type'  => 'NUMERIC',
                ],
            ],
            'meta_key'  => 'price_date',
            'orderby'   => 'meta_value',
            'order'     => 'ASC',
        ]);

        $entries = [];
        foreach ($query->posts as $post) {
            $entries[] = [
                'id'     => $post->ID,
                'date'   => get_field('price_date', $post->ID),
                'price'  => (float) get_field('price', $post->ID),
                'source' => get_field('source', $post->ID) ?: null,
            ];
        }

        return new WP_REST_Response($entries, 200);
    }
}