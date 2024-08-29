<?php
declare(strict_types=1);

namespace AuditStash\Test\TestCase\Model\Behavior;

use ArrayObject;
use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\AuditUpdateEvent;
use AuditStash\Model\Behavior\AuditLogBehavior;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use SplObjectStorage;

class AuditLogBehaviorTest extends TestCase
{
    private ?Table $table;
    private ?AuditLogBehavior $behavior;

    public function setUp(): void
    {
        parent::setUp();
        $this->table = new Table(['table' => 'articles']);
        $this->table->setPrimaryKey('id');
        $this->behavior = new AuditLogBehavior($this->table, [
            'whitelist' => ['id', 'title', 'body', 'author_id'],
        ]);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Configure::write('AuditStash.saveType', null);
    }

    public function testOnSaveCreateWithWhitelist()
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
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => '1',
            'associated' => [],
        ]));
        $result = $queue[$entity];
        $this->assertEquals($result->getOriginal(), $result->getChanged());
        unset($data['something_extra']);
        $this->assertEquals($data, $result->getChanged());
        $this->assertEquals(13, $result->getId());
        $this->assertEquals('articles', $result->getSourceName());
        $this->assertInstanceOf(AuditCreateEvent::class, $result);
    }

    public function testOnSaveUpdateWithWhitelist()
    {
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true,
        ];
        $entity = new Entity($data, ['markNew' => false, 'markClean' => true]);
        $entity->title = 'Another Title';

        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => '1',
            'associated' => [],
        ]));
        $result = $queue[$entity];
        $this->assertEquals(['title' => 'Another Title'], $result->getChanged());
        $this->assertEquals(['title' => 'The Title'], $result->getOriginal());
        $this->assertEquals(13, $result->getId());
        $this->assertEquals('articles', $result->getSourceName());
        $this->assertInstanceOf(AuditUpdateEvent::class, $result);
    }

    public function testSaveCreateWithBlacklist()
    {
        $this->behavior->setConfig('blacklist', ['author_id']);
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true,
        ];
        $entity = new Entity($data, ['markNew' => true]);

        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => '1',
            'associated' => [],
        ]));
        $result = $queue[$entity];
        $this->assertEquals($result->getOriginal(), $result->getChanged());
        unset($data['something_extra'], $data['author_id']);
        $this->assertEquals($data, $result->getChanged());
    }

    public function testSaveUpdateWithBlacklist()
    {
        $this->behavior->setConfig('blacklist', ['author_id']);
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
        ];
        $entity = new Entity($data, ['markNew' => false, 'markClean' => true]);
        $entity->author_id = 50;

        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => '1',
            'associated' => [],
        ]));

        $this->assertFalse(isset($queue[$entity]));
    }

    public function testSaveWithFieldsFromSchema()
    {
        $this->table->setSchema([
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'text'],
            'body' => ['type' => 'text'],
        ]);
        $this->behavior->setConfig('whitelist', false);
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true,
        ];
        $entity = new Entity($data, ['markNew' => true]);
        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => '1',
            'associated' => [],
        ]));
        $result = $queue[$entity];
        unset($data['something_extra'], $data['author_id']);
        $this->assertEquals($data, $result->getChanged());
        $this->assertEquals(13, $result->getId());
        $this->assertEquals('articles', $result->getSourceName());
        $this->assertInstanceOf(AuditCreateEvent::class, $result);
    }

    /**
     * @dataProvider dataProviderForSaveType
     */
    public function testImplementedEvents(?string $saveType): void
    {
        Configure::write('AuditStash.saveType', $saveType);
        $events = (new AuditLogBehavior(new Table()))->implementedEvents();
        if ($saveType === 'afterSave') {
            $this->assertArrayNotHasKey('Model.afterSaveCommit', $events);
            $this->assertArrayNotHasKey('Model.afterDeleteCommit', $events);
        } else {
            $this->assertArrayHasKey('Model.afterSaveCommit', $events);
            $this->assertArrayHasKey('Model.afterDeleteCommit', $events);
        }
    }

    public static function dataProviderForSaveType(): array
    {
        return [
            ['afterSave'],
            ['afterCommit'],
            [null],
        ];
    }

    public function testSensitiveFields(): void
    {
        $behavior = new AuditLogBehavior($this->table, [
            'whitelist' => ['id', 'title', 'body', 'author_id'],
            'sensitive' => ['body'],
        ]);

        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
        ];
        $entity = new Entity($data, ['markNew' => false, 'markClean' => true]);
        $entity->body = 'The changed body';

        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => '1',
            'associated' => [],
        ]));

        $event = $queue[$entity];

        $this->assertInstanceOf(AuditUpdateEvent::class, $event);

        $changed = $event->getChanged();
        $this->assertArrayHasKey('body', $changed);
        $this->assertEquals('****', $changed['body']);
    }
}
