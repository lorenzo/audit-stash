<?php

namespace AuditStash\Test\TestCase\Persister;

use AuditStash\Event\AuditCreateEvent;
use AuditStash\Persister\ElasticSearchPersister;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Elastica\Bulk\ResponseSet;
use Elastica\Client;
use Elastica\Response;

class ElasticSearchPersisterTest extends TestCase
{
    /**
     * Tests that create events are correctly stored.
     *
     * @return void
     */
    public function testLogEvents()
    {
        $clientMock = $this->createPartialMock(Client::class, ['addDocuments']);
        $clientMock
            ->method('addDocuments')
            ->willReturn(new ResponseSet(new Response('test', 200), []));

        $connectionMock = $this->createPartialMock(Connection::class, ['getDriver']);
        $connectionMock
            ->method('getDriver')
            ->willReturn($clientMock);

        $persister = new ElasticSearchPersister([
            'connection' => $connectionMock, 'index' => 'article', 'type' => 'article'
        ]);
        $data = [
            'title' => 'A new article',
            'body' => 'article body',
            'author_id' => 1,
            'published' => 'Y'
        ];

        $events[] = new AuditCreateEvent('1234', 50, 'articles', $data, $data, new Entity());
        $this->assertNull($persister->logEvents($events));
    }
}
