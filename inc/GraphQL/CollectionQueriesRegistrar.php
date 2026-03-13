<?php

namespace Coins\GraphQL;

class CollectionQueriesRegistrar
{
    public function register(): void
    {
        $this->registerMyCollection();
        $this->registerMyCollectionStats();
        $this->registerAddToCollection();
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

    private function registerAddToCollection(): void
    {
        register_graphql_mutation('addToCollection', [
            'description'  => 'Add a coin to the current user\'s collection. Requires authentication.',
            'inputFields'  => [
                'coinId' => [
                    'type'        => ['non_null' => 'Int'],
                    'description' => 'Post ID of the coin to add.',
                ],
            ],
            'outputFields' => [
                'success' => ['type' => 'Boolean'],
            ],
            'mutateAndGetPayload' => function ($input) {
                if (!is_user_logged_in()) {
                    throw new \GraphQL\Error\UserError('You must be logged in to add coins to your collection.');
                }

                $coin_id = (int) $input['coinId'];
                $user_id = get_current_user_id();

                if (get_post_type($coin_id) !== 'coins') {
                    throw new \GraphQL\Error\UserError('Invalid coin ID.');
                }

                // If the coin is already in the collection — increment quantity
                $existing = get_posts([
                    'post_type'      => 'coin_collection',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'meta_query'     => [
                        'relation' => 'AND',
                        [
                            'key'   => 'user_id',
                            'value' => $user_id,
                            'type'  => 'NUMERIC',
                        ],
                        [
                            'key'   => 'coin_id',
                            'value' => $coin_id,
                            'type'  => 'NUMERIC',
                        ],
                    ],
                ]);

                if (!empty($existing)) {
                    $post_id  = $existing[0];
                    $quantity = (int) get_field('quantity', $post_id) ?: 0;
                    update_field('quantity', $quantity + 1, $post_id);

                    return ['success' => true];
                }

                // Create new collection entry
                $post_id = wp_insert_post([
                    'post_type'   => 'coin_collection',
                    'post_status' => 'publish',
                    'post_title'  => 'Collection item',
                ]);

                if (is_wp_error($post_id)) {
                    throw new \GraphQL\Error\UserError('Failed to create collection entry.');
                }

                update_field('user_id', $user_id, $post_id);
                update_field('coin_id', $coin_id, $post_id);
                update_field('quantity', 1, $post_id);

                return ['success' => true];
            },
        ]);
    }
}