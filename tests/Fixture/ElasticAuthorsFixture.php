<?php

namespace AuditStash\Test\Fixture;

use Cake\ElasticSearch\TestSuite\TestFixture;

class ElasticAuthorsFixture extends TestFixture
{

    public $connection = 'test_elastic';

    /**
     * The table/type for this fixture.
     *
     * @var string
     */
    public $table = 'authors';

    /**
     * The mapping data.
     *
     * @var array
     */
    public $schema = [
        'id' => ['type' => 'integer'],
        '@timestamp' => ['type' => 'date'],
        'transaction' => ['type' => 'text', 'index' => 'not_analyzed'],
        'type' => ['type' => 'text', 'index' => 'not_analyzed'],
        'primary_key' => ['type' => 'integer'],
        'source' => ['type' => 'text', 'index' => 'not_analyzed'],
        'parent_source' => ['type' => 'text', 'index' => 'not_analyzed'],
        'original' => [
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'text'],
            ]
        ],
        'changed' => [
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'text'],
            ]
        ],
    ];
}
