<?php

namespace Coins\GraphQL;

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
    }
}