<?php

namespace Coins\GraphQL;

use Coins\GraphQL\Types\CustomTypesRegistrar;
use Coins\GraphQL\Fields\CoinFieldsRegistrar;
use Coins\GraphQL\Fields\DesignerFieldsRegistrar;
use Coins\GraphQL\Queries\CollectionQueriesRegistrar;
use Coins\GraphQL\Mutations\CollectionMutationsRegistrar;
use Coins\GraphQL\Mutations\AuthMutationsRegistrar;

class GraphQLRegistrar
{
    public function boot(): void
    {
        add_action('graphql_register_types', [$this, 'register']);
    }

    public function register(): void
    {
        (new CustomTypesRegistrar())->register();

        (new CoinFieldsRegistrar())->register();
        (new DesignerFieldsRegistrar())->register();

        (new CollectionQueriesRegistrar())->register();

        (new CollectionMutationsRegistrar())->register();
        (new AuthMutationsRegistrar())->register();
    }
}