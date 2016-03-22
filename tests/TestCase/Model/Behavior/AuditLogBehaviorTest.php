<?php

namespace AuditStash\Test\Model\Behavior;

use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\AuditUpdateEvent;
use AuditStash\Model\Behavior\AuditLogBehavior;
use Cake\Event\Event;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;

class AuditLogBehaviorTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->table = new Table(['table' => 'articles']);
        $this->table->primaryKey('id');
        $this->behavior = new AuditLogBehavior($this->table, [
            'whitelist' => ['id', 'title', 'body', 'author_id']
        ]);
    }

    public function testOnSaveCreateWithWithelist()
    {
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true,
        ];
        $entity = new Entity($data, ['markNew' => true]);

        $event = new Event('Model.afterSave');
        $queue = new \SplObjectStorage();
        $this->behavior->afterSave($event, $entity, [
            '_auditQueue' => $queue,
            '_auditTransaction' => 1,
            'associated' => []
        ]);
        $result = $queue[$entity];
        $this->assertEquals($result->getOriginal(), $result->getChanged());
        unset($data['something_extra']);
        $this->assertEquals($data, $result->getChanged());
        $this->assertEquals(13, $result->getId());
        $this->assertEquals('articles', $result->getSourceName());
        $this->assertInstanceOf(AuditCreateEvent::class, $result);
    }

    public function testOnSaveUpdateWithWithelist()
    {
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true
        ];
        $entity = new Entity($data, ['markNew' => false, 'markClean' => true]);
        $entity->title = 'Another Title';

        $event = new Event('Model.afterSave');
        $queue = new \SplObjectStorage();
        $this->behavior->afterSave($event, $entity, [
            '_auditQueue' => $queue,
            '_auditTransaction' => 1,
            'associated' => []
        ]);
        $result = $queue[$entity];
        $this->assertEquals(['title' => 'Another Title'], $result->getChanged());
        $this->assertEquals(['title' => 'The Title'], $result->getOriginal());
        $this->assertEquals(13, $result->getId());
        $this->assertEquals('articles', $result->getSourceName());
        $this->assertInstanceOf(AuditUpdateEvent::class, $result);
    }

    public function testSaveCreateWithBlacklist()
    {
        $this->behavior->config('blacklist', ['author_id']);
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true,
        ];
        $entity = new Entity($data, ['markNew' => true]);

        $event = new Event('Model.afterSave');
        $queue = new \SplObjectStorage();
        $this->behavior->afterSave($event, $entity, [
            '_auditQueue' => $queue,
            '_auditTransaction' => 1,
            'associated' => []
        ]);
        $result = $queue[$entity];
        $this->assertEquals($result->getOriginal(), $result->getChanged());
        unset($data['something_extra'], $data['author_id']);
        $this->assertEquals($data, $result->getChanged());
    }

    public function testSaveUpdateWithBlacklist()
    {
        $this->behavior->config('blacklist', ['author_id']);
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1
        ];
        $entity = new Entity($data, ['markNew' => false, 'markClean' => true]);
        $entity->author_id = 50;

        $event = new Event('Model.afterSave');
        $queue = new \SplObjectStorage();
        $this->behavior->afterSave($event, $entity, [
            '_auditQueue' => $queue,
            '_auditTransaction' => 1,
            'associated' => []
        ]);

        $this->assertFalse(isset($queue[$entity]));
    }

    public function testSaveWithFieldsFromSchema()
    {
        $this->table->schema([
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'body' => ['type' => 'text']
        ]);
        $this->behavior->config('whitelist', false);
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true,
        ];
        $entity = new Entity($data, ['markNew' => true]);
        $event = new Event('Model.afterSave');
        $queue = new \SplObjectStorage();
        $this->behavior->afterSave($event, $entity, [
            '_auditQueue' => $queue,
            '_auditTransaction' => 1,
            'associated' => []
        ]);
        $result = $queue[$entity];
        unset($data['something_extra'], $data['author_id']);
        $this->assertEquals($data, $result->getChanged());
        $this->assertEquals(13, $result->getId());
        $this->assertEquals('articles', $result->getSourceName());
        $this->assertInstanceOf(AuditCreateEvent::class, $result);
    }
}
