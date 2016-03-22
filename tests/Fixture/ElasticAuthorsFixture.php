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
        'transaction' => ['type' => 'string', 'index' => 'not_analyzed'],
        'type' => ['type' => 'string', 'index' => 'not_analyzed'],
        'primary_key' => ['type' => 'integer'],
        'source' => ['type' => 'string', 'index' => 'not_analyzed'],
        'parent_source' => ['type' => 'string', 'index' => 'not_analyzed'],
        'original' => [
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ]
        ],
        'changed' => [
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ]
        ],
    ];
}
