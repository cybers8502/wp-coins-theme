<?php

namespace Coins\Admin\PostTypes;

class CoinPricePostTypeRegistrar
{
    public function boot(): void
    {
        add_action('init', [$this, 'registerPostType']);
    }

    public function registerPostType(): void
    {
        register_post_type('coin_price', [
            'labels' => [
                'name'          => 'Price History',
                'singular_name' => 'Price Entry',
                'menu_name'     => 'Price History',
                'all_items'     => 'All Price Entries',
                'add_new_item'  => 'Add Price Entry',
                'add_new'       => 'New Price Entry',
                'edit_item'     => 'Edit Price Entry',
                'update_item'   => 'Update Price Entry',
                'search_items'  => 'Search Price Entries',
            ],
            'supports'            => ['title'],
            'hierarchical'        => false,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-chart-line',
            'show_in_admin_bar'   => false,
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'show_in_rest'        => true,
            'capability_type'     => 'post',
        ]);
    }
}