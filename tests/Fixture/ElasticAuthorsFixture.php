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
}
