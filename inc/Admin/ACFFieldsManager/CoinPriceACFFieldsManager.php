<?php

namespace Coins\Admin\ACFFieldsManager;

class CoinPriceACFFieldsManager
{
    public function boot(): void
    {
        add_action('acf/init', [$this, 'register']);
    }

    public function register(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key'   => 'group_coin_price_fields',
            'title' => 'Price Entry',
            'fields' => $this->getFields(),
            'location' => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'coin_price',
                    ],
                ],
            ],
            'menu_order'          => 0,
            'position'            => 'acf_after_title',
            'style'               => 'default',
            'label_placement'     => 'top',
            'instruction_placement' => 'label',
        ]);
    }

    private function getFields(): array
    {
        return [
            [
                'key'           => 'field_cp_coin',
                'label'         => 'Coin',
                'name'          => 'coin_id',
                'type'          => 'post_object',
                'instructions'  => 'Монета, до якої належить цей запис ціни.',
                'required'      => 1,
                'post_type'     => ['coins'],
                'taxonomy'      => '',
                'allow_null'    => 0,
                'multiple'      => 0,
                'return_format' => 'id',
                'ui'            => 1,
                'wrapper'       => ['width' => '50'],
            ],
            [
                'key'            => 'field_cp_date',
                'label'          => 'Date',
                'name'           => 'price_date',
                'type'           => 'date_picker',
                'instructions'   => 'Дата фіксації ціни.',
                'required'       => 1,
                'display_format' => 'd.m.Y',
                'return_format'  => 'Y-m-d',
                'first_day'      => 1,
                'wrapper'        => ['width' => '25'],
            ],
            [
                'key'          => 'field_cp_price',
                'label'        => 'Price (UAH)',
                'name'         => 'price',
                'type'         => 'number',
                'instructions' => 'Ціна монети в гривнях.',
                'required'     => 1,
                'min'          => 0,
                'step'         => 0.01,
                'wrapper'      => ['width' => '25'],
            ],
            [
                'key'          => 'field_cp_source',
                'label'        => 'Source',
                'name'         => 'source',
                'type'         => 'text',
                'instructions' => 'Джерело ціни (наприклад: NBU, auction, shop).',
                'required'     => 0,
                'wrapper'      => ['width' => '50'],
            ],
        ];
    }
}