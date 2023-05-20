<?php

namespace AuditStash\Test\Persister;

use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\AuditDeleteEvent;
use AuditStash\Event\AuditUpdateEvent;
use AuditStash\Persister\ElasticSearchPersister;
use Cake\ElasticSearch\Datasource\Connection;
use Cake\ElasticSearch\IndexRegistry;
use Cake\I18n\Time;
use Cake\TestSuite\TestCase;
use DateTime;
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

        $events[] = new AuditCreateEvent('1234', 50, 'articles', $data, $data);
        $this->assertNull($persister->logEvents($events));
    }
}
