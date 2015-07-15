<?php

namespace AuditStash\Test\Model\Behavior;

use AuditStash\Model\Behavior\AuditLogBehavior;
use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\AuditUpdateEvent;
use AuditStash\PersisterInterface;
use Cake\Event\Event;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class DebugPersister implements PersisterInterface
{

    public function logEvents(array $events)
    {
    }
}

class AuditIntegrationTest extends TestCase
{

    public $fixtures = [
        'core.articles',
        'core.comments',
        'core.authors',
        'core.tags',
        'core.articles_tags',
    ];

    public function setUp()
    {
        $this->table = TableRegistry::get('Articles');
        $this->table->hasMany('Comments');
        $this->table->belongsToMany('Tags');
        $this->table->belongsTo('Authors');
        $this->table->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class
        ]);

        $this->persister = $this->getMock(DebugPersister::class);
        $this->table->behaviors()->get('AuditLog')->persister($this->persister);
    }

    public function testCreateArticle()
    {
        $entity = $this->table->newEntity([
            'title' => 'New Article',
            'author_id' => 1,
            'body' => 'new article body'
        ]);

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events)  use ($entity) {
                $this->assertCount(1, $events);
                $event = $events[0];
                $this->assertInstanceOf(AuditCreateEvent::class, $event);

                $this->assertEquals(4, $event->getId());
                $this->assertEquals('articles', $event->getSourceName());
                $this->assertEquals($event->getOriginal(), $event->getChanged());

                $data = $entity->toArray();
                $this->assertEquals($data, $event->getChanged());
            }));

        $this->table->save($entity);
    }

    public function testUpdateArticle()
    {
        $entity = $this->table->get(1);
        $entity->title = 'Changed title';
        $entity->published = 'Y';

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events)  use ($entity) {
                $this->assertCount(1, $events);
                $event = $events[0];
                $this->assertInstanceOf(AuditUpdateEvent::class, $event);

                $this->assertEquals(1, $event->getId());
                $this->assertEquals('articles', $event->getSourceName());
                $expected = [
                    'title' => 'Changed title',
                    'published' => 'Y'
                ];
                $this->assertEquals($expected, $event->getChanged());
            }));

        $this->table->save($entity);
    }

    public function testCreateArticleWithExisitingBelongsTo()
    {
        $entity = $this->table->newEntity([
            'title' => 'New Article',
            'body' => 'new article body'
        ]);
        $entity->author = $this->table->Authors->get(1);

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events)  use ($entity) {
                $this->assertCount(1, $events);
                $event = $events[0];
                $this->assertInstanceOf(AuditCreateEvent::class, $event);

                $this->assertEquals(4, $event->getId());
                $this->assertEquals('articles', $event->getSourceName());
                $changed = $event->getChanged();
                $this->assertEquals(1, $changed['author_id']);
                $this->assertFalse(isset($changed['author']));
            }));

        $this->table->save($entity);
    }

    public function testUpdateArticleWithExistingBelongsTo()
    {
        $entity = $this->table->get(1, [
            'contain' => ['Authors']
        ]);
        $entity->title = 'Changed title';
        $entity->author = $this->table->Authors->get(2);

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events)  use ($entity) {
                $this->assertCount(1, $events);
                $event = $events[0];
                $this->assertInstanceOf(AuditUpdateEvent::class, $event);

                $this->assertEquals(1, $event->getId());
                $this->assertEquals('articles', $event->getSourceName());
                $expected = [
                    'title' => 'Changed title',
                    'author_id' => 2
                ];
                $this->assertEquals($expected, $event->getChanged());
                $this->assertFalse(isset($changed['author']));
            }));

        $this->table->save($entity);
    }
}
