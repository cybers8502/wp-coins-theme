<?php

namespace Coins\Admin\PostTypes;

class CoinPostTypeRegistrar
{
    public function boot(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('init', [$this, 'registerCoinTaxonomies']);
    }

    public function registerPostType(): void
    {
        register_post_type('coins', [
            'labels' => [
                'name'               => 'Coins',
                'singular_name'      => 'Coin',
                'menu_name'          => 'Coins',
                'all_items'          => 'All Coins',
                'view_item'          => 'View Coin',
                'add_new_item'       => 'Add Coin',
                'add_new'            => 'New Coin',
                'edit_item'          => 'Edit Coin',
                'update_item'        => 'Update Coin',
                'search_items'       => 'Search Coins',
            ],
            'supports'            => ['title', 'editor', 'thumbnail'],
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-star-filled',
            'show_in_admin_bar'   => true,
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => true,
            'taxonomies'          => ['category'],
            'show_in_rest'        => true,
            'capability_type'     => 'post',
            'rewrite'             => ['with_front' => true],
        ]);
    }

    public function registerCoinTaxonomies(): void
    {
        $taxonomies = [
            'coin_denomination' => [
                'label' => 'Denomination',
                'hierarchical' => false,
            ],
            'coin_quality' => [
                'label' => 'Quality',
                'hierarchical' => false,
            ],
            'coin_material' => [
                'label' => 'Material',
                'hierarchical' => false,
            ],
            'coin_series' => [
                'label' => 'Series',
                'hierarchical' => false,
            ],
            'coin_edge' => [
                'label' => 'Edge',
                'hierarchical' => false,
            ],
            'coin_diameter' => [
                'label' => 'Diameter',
                'hierarchical' => false,
            ],
            'coin_mintage_declared' => [
                'label' => 'Mintage (declared)',
                'hierarchical' => false,
            ],
            'coin_mintage_actual' => [
                'label' => 'Mintage (actual)',
                'hierarchical' => false,
            ],
        ];

        foreach ($taxonomies as $slug => $config) {
            register_taxonomy($slug, ['coins'], [
                'label'             => $config['label'],
                'public'            => true,
                'hierarchical'      => $config['hierarchical'],
                'show_ui'           => true,
                'show_in_rest'      => true,
                'rewrite'           => ['slug' => $slug],
            ]);
        }
    }
}
