<?php

namespace Coins\GraphQL\Types;

class CustomTypesRegistrar
{
    public function register(): void
    {
        register_graphql_object_type('CoinGalleryImage', [
            'description' => 'Gallery image of a coin',
            'fields'      => [
                'id'     => ['type' => 'Int',    'description' => 'Attachment ID'],
                'url'    => ['type' => 'String', 'description' => 'Full-size URL'],
                'medium' => ['type' => 'String', 'description' => 'Medium-size URL'],
            ],
        ]);

        register_graphql_object_type('CoinPriceEntry', [
            'description' => 'Historical price entry for a coin',
            'fields'      => [
                'id'     => ['type' => 'Int'],
                'date'   => ['type' => 'String'],
                'price'  => ['type' => 'Float'],
                'source' => ['type' => 'String'],
            ],
        ]);

        register_graphql_object_type('CollectionItem', [
            'description' => 'A coin in a user\'s collection',
            'fields'      => [
                'id'            => ['type' => 'Int'],
                'coinId'        => ['type' => 'Int'],
                'coinTitle'     => ['type' => 'String'],
                'coinThumbnail' => ['type' => 'String'],
                'quantity'      => ['type' => 'Int'],
                'purchasePrice' => ['type' => 'Float'],
            ],
        ]);

        register_graphql_object_type('CollectionStats', [
            'description' => 'Aggregated stats of a user\'s collection',
            'fields'      => [
                'uniqueCoins'   => ['type' => 'Int'],
                'totalQuantity' => ['type' => 'Int'],
                'totalSpent'    => ['type' => 'Float'],
            ],
        ]);

        register_graphql_object_type('AddToCollectionPayload', [
            'description' => 'Result of addToCollection mutation',
            'fields'      => [
                'success' => ['type' => 'Boolean'],
            ],
        ]);
    }
}