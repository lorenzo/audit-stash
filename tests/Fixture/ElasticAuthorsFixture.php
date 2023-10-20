<?php
declare(strict_types=1);

namespace AuditStash\Test\Fixture;

use Cake\ElasticSearch\TestSuite\TestFixture;

class ElasticAuthorsFixture extends TestFixture
{
    public string $connection = 'test_elastic';

    /**
     * The table/index for this fixture.
     *
     * @var string
     */
    public string $table = 'author';

    /**
     * The mapping data.
     *
     * @var array
     */
    public array $schema = [
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
    ];
}
