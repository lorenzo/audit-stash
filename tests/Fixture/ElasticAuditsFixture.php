<?php

namespace AuditStash\Test\Fixture;

use Cake\ElasticSearch\TestSuite\TestFixture;

class ElasticAuditsFixture extends TestFixture
{

    public $connection = 'test_elastic';

    /**
     * The table/index for this fixture.
     *
     * @var string
     */
    public $table = 'audit';

    /**
     * The mapping data.
     *
     * @var array
     */
    public $schema = [
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
