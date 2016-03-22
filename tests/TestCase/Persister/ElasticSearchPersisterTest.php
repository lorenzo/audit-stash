<?php

namespace AuditStash\Test\Persister;

use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\AuditDeleteEvent;
use AuditStash\Event\AuditUpdateEvent;
use AuditStash\Persister\ElasticSearchPersister;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\TypeRegistry;
use Cake\I18n\Time;
use Cake\TestSuite\TestCase;
use DateTime;

class ElasticSearchPersisterTest extends TestCase
{

    /**
     * Fixtures to be loaded.
     *
     * @var string
     */
    public $fixtures = [
        'plugin.audit_stash.elastic_articles',
        'plugin.audit_stash.elastic_authors',
        'plugin.audit_stash.elastic_tags',
    ];

    /**
     * Tests that create events are correctly stored.
     *
     * @return void
     */
    public function testLogSingleCreateEvent()
    {
        $client = ConnectionManager::get('test_elastic');
        $persister = new ElasticSearchPersister();
        $persister->connection($client);
        $data = [
            'title' => 'A new article',
            'body' => 'article body',
            'author_id' => 1,
            'published' => 'Y'
        ];

        $events[] = new AuditCreateEvent('1234', 50, 'articles', $data, $data);
        $persister->logEvents($events);
        $client->getIndex()->refresh();

        $articles = TypeRegistry::get('Articles')->find()->toArray();
        $this->assertCount(1, $articles);

        $this->assertEquals(
            new DateTime($events[0]->getTimestamp()),
            new DateTime($articles[0]->get('@timestamp'))
        );

        $expected = [
            'transaction' => '1234',
            'type' => 'create',
            'primary_key' => 50,
            'source' => 'articles',
            'parent_source' => null,
            'original' => [
                'title' => 'A new article',
                'body' => 'article body',
                'author_id' => 1,
                'published' => 'Y'
            ],
            'changed' => [
                'title' => 'A new article',
                'body' => 'article body',
                'author_id' => 1,
                'published' => 'Y'
            ],
            'meta' => []
        ];
        unset($articles[0]['id'], $articles[0]['@timestamp']);
        $this->assertEquals($expected, $articles[0]->toArray());
    }

    /**
     * Tests that update events are correctly stored.
     *
     * @return void
     */
    public function testLogSingleUpdateEvent()
    {
        $client = ConnectionManager::get('test_elastic');
        $persister = new ElasticSearchPersister();
        $persister->connection($client);
        $original = [
            'title' => 'Old article title',
            'published' => 'N'
        ];
        $changed = [
            'title' => 'A new article',
            'published' => 'Y'
        ];

        $events[] = new AuditUpdateEvent('1234', 50, 'articles', $changed, $original);
        $events[0]->setParentSourceName('authors');
        $persister->logEvents($events);
        $client->getIndex()->refresh();

        $articles = TypeRegistry::get('Articles')->find()->toArray();
        $this->assertCount(1, $articles);

        $this->assertEquals(
            new DateTime($events[0]->getTimestamp()),
            new DateTime($articles[0]->get('@timestamp'))
        );
        $expected = [
            'transaction' => '1234',
            'type' => 'update',
            'primary_key' => 50,
            'source' => 'articles',
            'parent_source' => 'authors',
            'original' => $original,
            'changed' => $changed,
            'meta' => []
        ];
        unset($articles[0]['id'], $articles[0]['@timestamp']);
        $this->assertEquals($expected, $articles[0]->toArray());
    }

    /**
     * Tests that delete events are correctly stored.
     *
     * @return void
     */
    public function testLogSingleDeleteEvent()
    {
        $client = ConnectionManager::get('test_elastic');
        $persister = new ElasticSearchPersister();
        $persister->connection($client);

        $events[] = new AuditDeleteEvent('1234', 50, 'articles', 'authors');
        $persister->logEvents($events);
        $client->getIndex()->refresh();

        $articles = TypeRegistry::get('Articles')->find()->toArray();
        $this->assertCount(1, $articles);

        $this->assertEquals(
            new DateTime($events[0]->getTimestamp()),
            new DateTime($articles[0]->get('@timestamp'))
        );

        $expected = [
            'transaction' => '1234',
            'type' => 'delete',
            'primary_key' => 50,
            'source' => 'articles',
            'parent_source' => 'authors',
            'original' => null,
            'changed' => null,
            'meta' => []
        ];
        unset($articles[0]['id'], $articles[0]['@timestamp']);
        $this->assertEquals($expected, $articles[0]->toArray());
    }

    /**
     * Tests that all events sent to the logger are actually persisted in the right types.
     *
     * @return void
     */
    public function testLogMultipleEvents()
    {
        $client = ConnectionManager::get('test_elastic');
        $persister = new ElasticSearchPersister();
        $persister->connection($client);

        $data = [
            'id' => 3,
            'tag' => 'cakephp'
        ];
        $events[] = new AuditCreateEvent('1234', 4, 'tags', $data, $data);

        $original = [
            'title' => 'Old article title',
            'published' => 'N'
        ];
        $changed = [
            'title' => 'A new article',
            'published' => 'Y'
        ];
        $events[] = new AuditUpdateEvent('1234', 2, 'authors', $changed, $original);
        $events[] = new AuditDeleteEvent('1234', 50, 'articles');
        $events[] = new AuditDeleteEvent('1234', 51, 'articles');

        $persister->logEvents($events);
        $client->getIndex()->refresh();

        $tags = TypeRegistry::get('Tags')->find()->all();
        $this->assertCount(1, $tags);
        $tag = $tags->first();
        $this->assertEquals(
            new DateTime($events[0]->getTimestamp()),
            new DateTime($tag->get('@timestamp'))
        );

        $expected = [
            'transaction' => '1234',
            'type' => 'create',
            'primary_key' => 4,
            'source' => 'tags',
            'parent_source' => null,
            'original' => [
                'id' => 3,
                'tag' => 'cakephp'
            ],
            'changed' => [
                'id' => 3,
                'tag' => 'cakephp'
            ],
            'meta' => []
        ];
        unset($tag['@timestamp'], $tag['id']);
        $this->assertEquals($expected, $tag->toArray());

        $authors = TypeRegistry::get('Authors')->find()->all();
        $this->assertCount(1, $authors);
        $author = $authors->first();

        $this->assertEquals(
            new DateTime($events[0]->getTimestamp()),
            new DateTime($author->get('@timestamp'))
        );

        $expected = [
            'transaction' => '1234',
            'type' => 'update',
            'primary_key' => 2,
            'source' => 'authors',
            'parent_source' => null,
            'original' => [
                'title' => 'Old article title',
                'published' => 'N'
            ],
            'changed' => [
                'title' => 'A new article',
                'published' => 'Y'
            ],
            'meta' => []
        ];
        unset($author['id'], $author['@timestamp']);
        $this->assertEquals($expected, $author->toArray());

        $articles = TypeRegistry::get('Articles')->find()->all();
        $this->assertCount(2, $articles);
        $this->assertEquals(
            [50 => 'delete', 51 => 'delete'],
            $articles->combine('primary_key', 'type')->toArray()
        );
    }

    /**
     * Tests that Time objects are correctly serialized.
     *
     * @return void
     */
    public function testPersistingTimeObjects()
    {
        $client = ConnectionManager::get('test_elastic');
        $persister = new ElasticSearchPersister();
        $persister->connection($client);
        $original = [
            'title' => 'Old article title',
            'published_date' => new Time('2015-04-12 20:20:21')
        ];
        $changed = [
            'title' => 'A new article',
            'published_date' => new Time('2015-04-13 20:20:21')
        ];

        $events[] = new AuditUpdateEvent('1234', 50, 'articles', $changed, $original);
        $persister->logEvents($events);
        $client->getIndex()->refresh();

        $articles = TypeRegistry::get('Articles')->find()->toArray();
        $this->assertCount(1, $articles);

        $this->assertEquals(
            new DateTime($events[0]->getTimestamp()),
            new DateTime($articles[0]->get('@timestamp'))
        );

        $expected = [
            'transaction' => '1234',
            'type' => 'update',
            'primary_key' => 50,
            'source' => 'articles',
            'parent_source' => null,
            'original' => [
                'title' => 'Old article title',
                'published_date' => '2015-04-12T20:20:21+0000'
            ],
            'changed' => [
                'title' => 'A new article',
                'published_date' => '2015-04-13T20:20:21+0000'
            ],
            'meta' => []
        ];
        unset($articles[0]['id'], $articles[0]['@timestamp']);
        $this->assertEquals($expected, $articles[0]->toArray());
    }

    /**
     * Tests that metadata is correctly stored.
     *
     * @return void
     */
    public function testLogEventWithMetadata()
    {
        $client = ConnectionManager::get('test_elastic');
        $persister = new ElasticSearchPersister();
        $persister->connection($client);

        $events[] = new AuditDeleteEvent('1234', 50, 'articles', 'authors');
        $events[0]->setMetaInfo(['a' => 'b', 'c' => 'd']);
        $persister->logEvents($events);
        $client->getIndex()->refresh();

        $articles = TypeRegistry::get('Articles')->find()->toArray();
        $this->assertCount(1, $articles);
        $this->assertEquals(['a' => 'b', 'c' => 'd'], $articles[0]->meta);
    }
}
