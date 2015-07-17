<?php

namespace AuditStash\Test\Persister;

use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\AuditDeleteEvent;
use AuditStash\Event\AuditUpdateEvent;
use AuditStash\Persister\ElasticSearchPersister;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\TypeRegistry;
use Cake\TestSuite\TestCase;

class ElasticSearchPersisterTest extends TestCase
{

    public $fixtures = [
        'plugin.audit_stash.elastic_articles',
        'plugin.audit_stash.elastic_authors',
        'plugin.audit_stash.elastic_tags',
    ];

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

        $expected = [
            'trasaction' => '1234',
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
            ]
        ];
        unset($articles[0]->id);
        $this->assertEquals($expected, $articles[0]->toArray());
    }

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

        $expected = [
            'trasaction' => '1234',
            'type' => 'update',
            'primary_key' => 50,
            'source' => 'articles',
            'parent_source' => 'authors',
            'original' => $original,
            'changed' => $changed
        ];
        unset($articles[0]->id);
        $this->assertEquals($expected, $articles[0]->toArray());
    }

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

        $expected = [
            'trasaction' => '1234',
            'type' => 'delete',
            'primary_key' => 50,
            'source' => 'articles',
            'parent_source' => 'authors',
            'original' => null,
            'changed' => null
        ];
        unset($articles[0]->id);
        $this->assertEquals($expected, $articles[0]->toArray());
    }

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
        $expected = [
            'trasaction' => '1234',
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
            ]
        ];
        unset($tag->id);
        $this->assertEquals($expected, $tag->toArray());


        $authors = TypeRegistry::get('Authors')->find()->all();
        $this->assertCount(1, $authors);
        $author = $authors->first();
        $expected = [
            'trasaction' => '1234',
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
            ]
        ];
        unset($author->id);
        $this->assertEquals($expected, $author->toArray());

        $articles = TypeRegistry::get('Articles')->find()->all();
        $this->assertCount(2, $articles);
        $this->assertEquals(
            [50 => 'delete', 51 => 'delete'],
            $articles->combine('primary_key', 'type')->toArray()
        );
    }
}
