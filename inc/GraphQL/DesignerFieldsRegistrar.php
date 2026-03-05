<?php

namespace Coins\GraphQL;

class DesignerFieldsRegistrar
{
    public function register(): void
    {
        register_graphql_field('Designer', 'fullName', [
            'type'    => 'String',
            'resolve' => fn($source) => get_field('full_name', $source->databaseId) ?: null,
        ]);

        register_graphql_field('Designer', 'note', [
            'type'    => 'String',
            'resolve' => fn($source) => get_field('note', $source->databaseId) ?: null,
        ]);
    }
}