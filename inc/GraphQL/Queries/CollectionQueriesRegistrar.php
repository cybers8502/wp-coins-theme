<?php

namespace Coins\GraphQL\Queries;

class CollectionQueriesRegistrar
{
    public function register(): void
    {
        $this->registerMyCollection();
        $this->registerMyCollectionStats();
    }

    private function registerMyCollection(): void
    {
        register_graphql_field('RootQuery', 'myCollection', [
            'type'        => ['list_of' => 'CollectionItem'],
            'description' => 'Coins in the current user\'s collection. Requires authentication.',
            'resolve'     => function () {
                if (!is_user_logged_in()) {
                    throw new \GraphQL\Error\UserError('You must be logged in to view your collection.');
                }

                $query = new \WP_Query([
                    'post_type'      => 'coin_collection',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'meta_query'     => [[
                        'key'   => 'user_id',
                        'value' => get_current_user_id(),
                        'type'  => 'NUMERIC',
                    ]],
                ]);

                return array_map(function ($post) {
                    $coin_id = (int) get_field('coin_id', $post->ID);
                    $price   = get_field('purchase_price', $post->ID);

                    return [
                        'id'            => $post->ID,
                        'coinId'        => $coin_id,
                        'coinTitle'     => get_the_title($coin_id),
                        'coinThumbnail' => get_the_post_thumbnail_url($coin_id, 'medium') ?: null,
                        'quantity'      => (int) get_field('quantity', $post->ID),
                        'purchasePrice' => $price !== '' && $price !== false ? (float) $price : null,
                    ];
                }, $query->posts);
            },
        ]);
    }

    private function registerMyCollectionStats(): void
    {
        register_graphql_field('RootQuery', 'myCollectionStats', [
            'type'        => 'CollectionStats',
            'description' => 'Aggregated stats for the current user\'s collection. Requires authentication.',
            'resolve'     => function () {
                if (!is_user_logged_in()) {
                    throw new \GraphQL\Error\UserError('You must be logged in to view collection stats.');
                }

                $query = new \WP_Query([
                    'post_type'      => 'coin_collection',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_query'     => [[
                        'key'   => 'user_id',
                        'value' => get_current_user_id(),
                        'type'  => 'NUMERIC',
                    ]],
                ]);

                $unique   = 0;
                $quantity = 0;
                $spent    = 0.0;

                foreach ($query->posts as $post_id) {
                    $qty   = (int) get_field('quantity', $post_id);
                    $price = get_field('purchase_price', $post_id);

                    $unique++;
                    $quantity += $qty;

                    if ($price !== '' && $price !== false) {
                        $spent += (float) $price * $qty;
                    }
                }

                return [
                    'uniqueCoins'   => $unique,
                    'totalQuantity' => $quantity,
                    'totalSpent'    => round($spent, 2),
                ];
            },
        ]);
    }
}
