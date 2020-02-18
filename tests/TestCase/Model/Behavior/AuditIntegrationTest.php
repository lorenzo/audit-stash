<?php

namespace AuditStash\Test\Model\Behavior;

use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\AuditDeleteEvent;
use AuditStash\Event\AuditUpdateEvent;
use AuditStash\Model\Behavior\AuditLogBehavior;
use AuditStash\PersisterInterface;
use Cake\Datasource\ModelAwareTrait;
use Cake\Event\Event;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;

class DebugPersister implements PersisterInterface
{
    public function logEvents(array $events)
    {
    }
}

class AuditIntegrationTest extends TestCase
{
    use ModelAwareTrait;

    /**
     * Fixtures to use.
     *
     * @var array
     */
    public $fixtures = [
        'core.Articles',
        'core.Comments',
        'core.Authors',
        'core.Tags',
        'core.ArticlesTags',
    ];

    /**
     * tests setup.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->table = $this->loadModel('Articles');
        $this->table->hasMany('Comments');
        $this->table->belongsToMany('Tags');
        $this->table->belongsTo('Authors');
        $this->table->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class
        ]);

        $this->persister = $this->createMock(DebugPersister::class);
        $this->table->behaviors()->get('AuditLog')->persister($this->persister);
    }

    /**
     * Tests that creating an article means having one audit log create event.
     *
     * @return void
     */
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
            ->will($this->returnCallback(function (array $events) use ($entity) {
                $this->assertCount(1, $events);
                $event = $events[0];
                $this->assertInstanceOf(AuditCreateEvent::class, $event);

                $this->assertEquals(4, $event->getId());
                $this->assertEquals('articles', $event->getSourceName());
                $this->assertEquals($event->getOriginal(), $event->getChanged());
                $this->assertNotEmpty($event->getTransactionId());

                $data = $entity->toArray();
                $this->assertEquals($data, $event->getChanged());
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that updating an article means having one audit log update event.
     *
     * @return void
     */
    public function testUpdateArticle()
    {
        $entity = $this->table->get(1);
        $entity->title = 'Changed title';
        $entity->published = 'Y';

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) use ($entity) {
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
                $this->assertNotEmpty($event->getTransactionId());
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that adding a belongsTo association means having one update
     * log event for the main entity.
     *
     * @return void
     */
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
            ->will($this->returnCallback(function (array $events) use ($entity) {
                $this->assertCount(1, $events);
                $event = $events[0];
                $this->assertInstanceOf(AuditCreateEvent::class, $event);

                $this->assertEquals(4, $event->getId());
                $this->assertEquals('articles', $event->getSourceName());
                $changed = $event->getChanged();
                $this->assertEquals(1, $changed['author_id']);
                $this->assertFalse(isset($changed['author']));
                $this->assertNotEmpty($event->getTransactionId());
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that adding a belongsTo association means having one update
     * log event for the main entity.
     *
     * @return void
     */
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
            ->will($this->returnCallback(function (array $events) use ($entity) {
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
                $this->assertNotEmpty($event->getTransactionId());
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that adding a new belongsTo entity means having one update
     * log event for the main entity and one of the new belongsto entity.
     *
     * @return void
     */
    public function testCreateArticleWithNewBelongsTo()
    {
        $this->table->Authors->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class
        ]);
        $entity = $this->table->newEntity([
            'title' => 'New Article',
            'body' => 'new article body',
            'author' => [
                'name' => 'Jose'
            ]
        ]);
        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) use ($entity) {
                $this->assertCount(2, $events);
                $this->assertEquals('authors', $events[0]->getSourceName());
                $this->assertEquals('articles', $events[1]->getSourceName());

                $this->assertInstanceOf(AuditCreateEvent::class, $events[0]);
                $this->assertNotEmpty($events[0]->getTransactionId());
                $this->assertSame($events[0]->getTransactionId(), $events[1]->getTransactionId());

                $this->assertEquals(['id' => 5, 'name' => 'Jose'], $events[0]->getChanged());
                $this->assertFalse(isset($events[1]->getChanged()['author']));
                $this->assertEquals('new article body', $events[1]->getChanged()['body']);
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that adding has many entities means one event for each of the updated
     * associated entities.
     *
     * @return void
     */
    public function testUpdateArticleWithHasMany()
    {
        $this->table->Comments->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class
        ]);

        $entity = $this->table->get(1, [
            'contain' => ['Comments']
        ]);
        $entity->comments[] = $this->table->Comments->newEntity([
            'user_id' => 1,
            'comment' => 'This is a comment'
        ]);
        $entity->comments[] = $this->table->Comments->newEntity([
            'user_id' => 1,
            'comment' => 'This is another comment'
        ]);
        $entity->setDirty('comments', true);

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) use ($entity) {
                $this->assertCount(2, $events);
                $this->assertEquals('comments', $events[0]->getSourceName());
                $this->assertEquals('comments', $events[1]->getSourceName());

                $this->assertNotEmpty($events[0]->getTransactionId());
                $this->assertSame($events[0]->getTransactionId(), $events[1]->getTransactionId());

                $expected = [
                    'id' => 7,
                    'article_id' => 1,
                    'user_id' => 1,
                    'comment' => 'This is a comment'
                ];
                $this->assertEquals($expected, $events[0]->getChanged());

                $expected = [
                    'id' => 8,
                    'article_id' => 1,
                    'user_id' => 1,
                    'comment' => 'This is another comment'
                ];
                $this->assertEquals($expected, $events[1]->getChanged());
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that adding has many entities means one event for each of the updated
     * associated entities and finally and event for the main entity if it is new.
     *
     * @return void
     */
    public function testCreateArticleWithHasMany()
    {
        $this->table->Comments->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class
        ]);

        $entity = $this->table->newEntity([
            'title' => 'New Article',
            'body' => 'new article body',
            'comments' => [
                ['comment' => 'This is a comment', 'user_id' => 1],
                ['comment' => 'This is another comment', 'user_id' => 1],
            ]
        ]);

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) use ($entity) {
                $this->assertCount(3, $events);
                $this->assertEquals('comments', $events[0]->getSourceName());
                $this->assertEquals('articles', $events[0]->getParentSourceName());
                $this->assertEquals('comments', $events[1]->getSourceName());
                $this->assertEquals('articles', $events[2]->getSourceName());

                $this->assertNotEmpty($events[0]->getTransactionId());
                $this->assertSame($events[0]->getTransactionId(), $events[1]->getTransactionId());
                $this->assertSame($events[0]->getTransactionId(), $events[2]->getTransactionId());
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that adding belongsToMany entities means log events for each new
     * entity in the target table and events for as many entities got saved in the
     * junction table.
     *
     * @return void
     */
    public function testUpdateWithBelongsToMany()
    {
        $this->table->Tags->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class
        ]);
        $this->table->Tags->junction()->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class
        ]);

        $entity = $this->table->get(1, [
            'contain' => ['Tags']
        ]);
        $entity->tags[] = $this->table->Tags->newEntity([
            'name' => 'This is a Tag'
        ]);
        $entity->tags[] = $this->table->Tags->get(3);
        $entity->setDirty('tags', true);

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) use ($entity) {
                $this->assertCount(3, $events);
                $this->assertEquals('tags', $events[0]->getSourceName());
                $this->assertEquals('articles_tags', $events[1]->getSourceName());
                $this->assertEquals('articles_tags', $events[2]->getSourceName());

                $this->assertNotEmpty($events[0]->getTransactionId());
                $this->assertSame($events[0]->getTransactionId(), $events[1]->getTransactionId());
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that deleting an entity logs a single event.
     *
     * @return void
     */
    public function testDelete()
    {
        $entity = $this->table->get(1);
        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) use ($entity) {
                $this->assertCount(1, $events);
                $this->assertinstanceOf(AuditDeleteEvent::class, $events[0]);
                $this->assertEquals(1, $events[0]->getId());
                $this->assertEquals('articles', $events[0]->getSourceName());
                $this->assertNotEmpty($events[0]->getTransactionId());
            }));

        $this->table->delete($entity);
    }

    /**
     * Tests that deleting an entity with cascading delete.
     *
     * @return void
     */
    public function testDeleteCascade()
    {
        $this->table->Tags->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class
        ]);
        $this->table->Tags->junction()->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class
        ]);
        $this->table->Comments->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class
        ]);
        $entity = $this->table->get(1, [
            'contain' => ['Comments', 'Tags']
        ]);

        $this->table->Comments->setDependent(true);
        $this->table->Comments->setCascadeCallbacks(true);

        $this->table->Tags->setDependent(true);
        $this->table->Tags->getCascadeCallbacks(true);

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) use ($entity) {
                $this->assertCount(5, $events);
                $id = $events[0]->getTransactionId();
                foreach ($events as $event) {
                    $this->assertinstanceOf(AuditDeleteEvent::class, $event);
                    $this->assertNotEmpty($event->getTransactionId());
                    $this->assertEquals($id, $event->getTransactionId());
                }

                $this->assertEquals('comments', $events[0]->getSourceName());
                $this->assertEquals('comments', $events[1]->getSourceName());
                $this->assertEquals('comments', $events[2]->getSourceName());
                $this->assertEquals('comments', $events[3]->getSourceName());
                $this->assertEquals('articles', $events[4]->getSourceName());
            }));

        $this->table->delete($entity);
    }
}
