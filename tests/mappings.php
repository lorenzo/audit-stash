<?php
declare(strict_types=1);

return [
    [
        'name' => 'article',
        'mapping' => [
            'id' => ['type' => 'integer'],
            '@timestamp' => ['type' => 'date'],
            'transaction' => ['type' => 'text', 'index' => false],
            'type' => ['type' => 'text', 'index' => false],
            'primary_key' => ['type' => 'integer'],
            'source' => ['type' => 'text', 'index' => false],
            'parent_source' => ['type' => 'text', 'index' => false],
            'original' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'author_id' => ['type' => 'integer'],
                    'title' => ['type' => 'text'],
                    'body' => ['type' => 'text'],
                    'published' => ['type' => 'text', 'index' => false],
                    'published_date' => ['type' => 'date'],
                ],
            ],
            'changed' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'author_id' => ['type' => 'integer'],
                    'title' => ['type' => 'text'],
                    'body' => ['type' => 'text'],
                    'published' => ['type' => 'text', 'index' => false],
                    'published_date' => ['type' => 'date'],
                ],
            ],
        ],
    ],
    [
        'name' => 'audit',
        'mapping' => [
            'id' => ['type' => 'integer'],
            '@timestamp' => ['type' => 'date'],
            'transaction' => ['type' => 'text', 'index' => false],
            'type' => ['type' => 'text', 'index' => false],
            'primary_key' => ['type' => 'integer'],
            'source' => ['type' => 'text', 'index' => false],
            'parent_source' => ['type' => 'text', 'index' => false],
            'original' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'text'],
                ],
            ],
            'changed' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'text'],
                ],
            ],
        ],
    ],
    [
        'name' => 'author',
        'mapping' => [
            'id' => ['type' => 'integer'],
            '@timestamp' => ['type' => 'date'],
            'transaction' => ['type' => 'text', 'index' => false],
            'type' => ['type' => 'text', 'index' => false],
            'primary_key' => ['type' => 'integer'],
            'source' => ['type' => 'text', 'index' => false],
            'parent_source' => ['type' => 'text', 'index' => false],
            'original' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'text'],
                ],
            ],
            'changed' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'text'],
                ],
            ],
        ],
    ],
    [
        'name' => 'tag',
        'mapping' => [
            'id' => ['type' => 'integer'],
            '@timestamp' => ['type' => 'date'],
            'transaction' => ['type' => 'text', 'index' => false],
            'type' => ['type' => 'text', 'index' => false],
            'primary_key' => ['type' => 'integer'],
            'source' => ['type' => 'text', 'index' => false],
            'parent_source' => ['type' => 'text', 'index' => false],
            'original' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'text'],
                ],
            ],
            'changed' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'text'],
                ],
            ],
        ],
    ],
];
