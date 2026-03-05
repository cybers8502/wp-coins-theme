<?php

namespace Coins\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class CoinCollectionController
{
    // GET /collection
    public function getCollection(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = get_current_user_id();

        $query = new \WP_Query([
            'post_type'      => 'coin_collection',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => 'user_id',
                    'value' => $user_id,
                    'type'  => 'NUMERIC',
                ],
            ],
        ]);

        $items = [];
        foreach ($query->posts as $post) {
            $items[] = $this->formatItem($post->ID);
        }

        return new WP_REST_Response($items, 200);
    }

    // POST /collection  { coin_id, quantity, purchase_price }
    public function addItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id  = get_current_user_id();
        $coin_id  = (int) $request->get_param('coin_id');
        $quantity = (int) ($request->get_param('quantity') ?? 1);
        $price    = $request->get_param('purchase_price');

        if (!get_post($coin_id) || get_post_type($coin_id) !== 'coins') {
            return new WP_Error('invalid_coin', 'Coin not found.', ['status' => 404]);
        }

        // Check if this coin is already in the user's collection
        $existing = $this->findExistingItem($user_id, $coin_id);
        if ($existing) {
            return new WP_Error('already_exists', 'This coin is already in your collection. Use PATCH to update it.', ['status' => 409]);
        }

        $post_id = wp_insert_post([
            'post_type'   => 'coin_collection',
            'post_status' => 'publish',
            'post_title'  => "User {$user_id} — Coin {$coin_id}",
            'post_author' => $user_id,
        ]);

        if (is_wp_error($post_id)) {
            return new WP_Error('insert_failed', 'Failed to create collection item.', ['status' => 500]);
        }

        update_field('user_id', $user_id, $post_id);
        update_field('coin_id', $coin_id, $post_id);
        update_field('quantity', $quantity, $post_id);

        if ($price !== null) {
            update_field('purchase_price', (float) $price, $post_id);
        }

        return new WP_REST_Response($this->formatItem($post_id), 201);
    }

    // PATCH /collection/{id}  { quantity?, purchase_price? }
    public function updateItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id = get_current_user_id();
        $item_id = (int) $request->get_param('id');

        $error = $this->authorizeItem($item_id, $user_id);
        if ($error) {
            return $error;
        }

        if ($request->has_param('quantity')) {
            $quantity = (int) $request->get_param('quantity');
            if ($quantity < 1) {
                return new WP_Error('invalid_quantity', 'Quantity must be at least 1.', ['status' => 422]);
            }
            update_field('quantity', $quantity, $item_id);
        }

        if ($request->has_param('purchase_price')) {
            update_field('purchase_price', (float) $request->get_param('purchase_price'), $item_id);
        }

        return new WP_REST_Response($this->formatItem($item_id), 200);
    }

    // DELETE /collection/{id}
    public function deleteItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id = get_current_user_id();
        $item_id = (int) $request->get_param('id');

        $error = $this->authorizeItem($item_id, $user_id);
        if ($error) {
            return $error;
        }

        wp_delete_post($item_id, true);

        return new WP_REST_Response(['deleted' => true, 'id' => $item_id], 200);
    }

    private function formatItem(int $post_id): array
    {
        return [
            'id'             => $post_id,
            'coin_id'        => (int) get_field('coin_id', $post_id),
            'quantity'       => (int) get_field('quantity', $post_id),
            'purchase_price' => get_field('purchase_price', $post_id) !== '' && get_field('purchase_price', $post_id) !== false
                ? (float) get_field('purchase_price', $post_id)
                : null,
        ];
    }

    private function findExistingItem(int $user_id, int $coin_id): ?int
    {
        $query = new \WP_Query([
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

        return $query->posts[0] ?? null;
    }

    private function authorizeItem(int $item_id, int $user_id): ?WP_Error
    {
        $post = get_post($item_id);

        if (!$post || $post->post_type !== 'coin_collection' || $post->post_status !== 'publish') {
            return new WP_Error('not_found', 'Collection item not found.', ['status' => 404]);
        }

        $owner = (int) get_field('user_id', $item_id);
        if ($owner !== $user_id) {
            return new WP_Error('forbidden', 'You do not have access to this item.', ['status' => 403]);
        }

        return null;
    }
}