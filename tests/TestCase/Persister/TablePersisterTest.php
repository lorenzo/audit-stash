<?php

namespace AuditStash\Test\Persister;

use AuditStash\Event\AuditCreateEvent;
use AuditStash\Persister\TablePersister;
use Cake\Datasource\EntityInterface;
use Cake\Error\Debugger;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class AuditLogsTable extends Table
{
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->table('audit_logs');
        $this->primaryKey('id');

        $this->schema([
            'id' => 'integer',
            'transaction' => 'string',
            'type' => 'string',
            'primary_key' => 'integer',
            'source' => 'string',
            'parent_source' => 'string',
            'original' => 'string',
            'changed' => 'string',
            'meta' => 'string',
            'created' => 'datetime',
        ]);
    }
}

class TablePersisterTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \AuditStash\Persister\TablePersister
     */
    public $TablePersister;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->TablePersister = new TablePersister();

        TableRegistry::config('AuditLogs', [
            'className' => AuditLogsTable::class
        ]);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->TablePersister);

        parent::tearDown();
    }

    public function testConfigDefaults()
    {
        $expected = [
            'extractMetaFields' => false,
            'logErrors' => true,
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_AUTOMATIC,
            'serializeFields' => true,
            'table' => 'AuditLogs',
            'unsetExtractedMetaFields' => true,
        ];
        $this->assertEquals($expected, $this->TablePersister->config());
    }

    public function testGetTableDefault()
    {
        $this->assertInstanceOf(AuditLogsTable::class, $this->TablePersister->getTable());
    }

    public function testSetTableAsAlias()
    {
        $this->assertInstanceOf(AuditLogsTable::class, $this->TablePersister->getTable());
        $this->assertInstanceOf(TablePersister::class, $this->TablePersister->setTable('Custom'));
        $this->assertInstanceOf(Table::class, $this->TablePersister->getTable());
        $this->assertEquals('Custom', $this->TablePersister->getTable()->alias());
    }

    public function testSetTableAsObject()
    {
        $customTable = TableRegistry::get('Custom');
        $this->assertInstanceOf(AuditLogsTable::class, $this->TablePersister->getTable());
        $this->assertInstanceOf(TablePersister::class, $this->TablePersister->setTable($customTable));
        $this->assertSame($customTable, $this->TablePersister->getTable());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The `$table` argument must be either a table alias, or an instance of `\Cake\ORM\Table`.
     */
    public function testSetInvalidTable()
    {
        $this->TablePersister->setTable(null);
    }

    public function testSerializeNull()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', null, null);
        $event->setMetaInfo(null);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'original' => null,
            'changed' => null,
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => null
        ]);
        $entity->source('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save'], TableRegistry::config('AuditLogs'));
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testExtractMetaFields()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], []);
        $event->setMetaInfo([
            'foo' => 'bar',
            'baz' => [
                'nested' => 'value',
                'bar' => 'foo'
            ]
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'original' => '[]',
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '{"baz":{"bar":"foo"}}',
            'foo' => 'bar',
            'nested' => 'value'
        ]);
        $entity->source('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save'], TableRegistry::config('AuditLogs'));
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->config([
            'extractMetaFields' => [
                'foo',
                'baz.nested' => 'nested'
            ]
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testExtractAllMetaFields()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], []);
        $event->setMetaInfo([
            'foo' => 'bar',
            'baz' => [
                'nested' => 'value',
                'bar' => 'foo'
            ]
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'original' => '[]',
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '[]',
            'foo' => 'bar',
            'baz' => [
                'nested' => 'value',
                'bar' => 'foo'
            ]
        ]);
        $entity->source('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save'], TableRegistry::config('AuditLogs'));
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->config([
            'extractMetaFields' => true
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testExtractMetaFieldsDoNotUnset()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], []);
        $event->setMetaInfo([
            'foo' => 'bar'
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'original' => '[]',
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '{"foo":"bar"}',
            'foo' => 'bar'
        ]);
        $entity->source('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save'], TableRegistry::config('AuditLogs'));
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->config([
            'extractMetaFields' => [
                'foo'
            ],
            'unsetExtractedMetaFields' => false
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testExtractAllMetaFieldsDoNotUnset()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], []);
        $event->setMetaInfo([
            'foo' => 'bar'
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'original' => '[]',
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '{"foo":"bar"}',
            'foo' => 'bar'
        ]);
        $entity->source('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save'], TableRegistry::config('AuditLogs'));
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->config([
            'extractMetaFields' => true,
            'unsetExtractedMetaFields' => false
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testErrorLogging()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], []);

        /* @var $TablePersister TablePersister|\PHPUnit_Framework_MockObject_MockObject */
        $TablePersister = $this
            ->getMockBuilder(TablePersister::class)
            ->setMethods(['log'])
            ->getMock();

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'original' => '[]',
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '[]'
        ]);

        $logged = clone $entity;
        $logged->errors('field', ['error']);
        $logged->source('AuditLogs');

        $TablePersister
            ->expects($this->once())
            ->method('log')
            ->with(
                '[AuditStash\Persister\TablePersister] Persisting audit log failed. Data:' . PHP_EOL .
                Debugger::exportVar($logged, 4)
            );

        $TablePersister->getTable()->eventManager()->on(
            'Model.beforeSave',
            function ($event, EntityInterface $entity) {
                $entity->errors('field', ['error']);
                return false;
            }
        );

        $TablePersister->logEvents([$event]);
    }

    public function testDisableErrorLogging()
    {
        /* @var $TablePersister TablePersister|\PHPUnit_Framework_MockObject_MockObject */
        $TablePersister = $this
            ->getMockBuilder(TablePersister::class)
            ->setMethods(['log'])
            ->getMock();

        $TablePersister
            ->expects($this->never())
            ->method('log');

        $TablePersister->config([
            'logErrors' => false
        ]);
        $TablePersister->getTable()->eventManager()->on(
            'Model.beforeSave',
            function ($event, EntityInterface $entity) {
                return false;
            }
        );

        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], []);
        $TablePersister->logEvents([$event]);
    }

    public function testCompoundPrimaryKeyExtractDefault()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', [1, 2, 3], 'source', [], []);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'original' => '[]',
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => '[1,2,3]',
            'meta' => '[]',
        ]);
        $entity->source('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save'], TableRegistry::config('AuditLogs'));
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->schema()->columnType('primary_key', 'string');

        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testPrimaryKeyExtractRaw()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], []);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'original' => '[]',
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '[]',
        ]);
        $entity->source('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save'], TableRegistry::config('AuditLogs'));
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->config([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_RAW
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testCompoundPrimaryKeyExtractRaw()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', [1, 2, 3], 'source', [], []);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'original' => '[]',
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => [1, 2, 3],
            'meta' => '[]',
        ]);
        $entity->source('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save'], TableRegistry::config('AuditLogs'));
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->schema()->columnType('primary_key', 'json');

        $this->TablePersister->config([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_RAW
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testPrimaryKeyExtractProperties()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], []);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'original' => '[]',
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '[]',
        ]);
        $entity->source('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save'], TableRegistry::config('AuditLogs'));
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->config([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_PROPERTIES
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testCompoundPrimaryKeyExtractProperties()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', [1, 2, 3], 'source', [], []);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'original' => '[]',
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key_0' => 1,
            'primary_key_1' => 2,
            'primary_key_2' => 3,
            'meta' => '[]',
        ]);
        $entity->source('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save'], TableRegistry::config('AuditLogs'));
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->config([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_PROPERTIES
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testPrimaryKeyExtractSerialized()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 'pk', 'source', [], []);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'original' => '[]',
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => '"pk"',
            'meta' => '[]',
        ]);
        $entity->source('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save'], TableRegistry::config('AuditLogs'));
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->schema()->columnType('primary_key', 'string');

        $this->TablePersister->config([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_SERIALIZED
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testCompoundPrimaryKeyExtractSerialized()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', [1, 2, 3], 'source', [], []);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'original' => '[]',
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => '[1,2,3]',
            'meta' => '[]',
        ]);
        $entity->source('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save'], TableRegistry::config('AuditLogs'));
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->schema()->columnType('primary_key', 'string');

        $this->TablePersister->config([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_SERIALIZED
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testDoNotSerializeFields()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], []);
        $event->setMetaInfo([
            'foo' => 'bar'
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'original' => [],
            'changed' => [],
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => [
                'foo' => 'bar'
            ],
        ]);
        $entity->source('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save'], TableRegistry::config('AuditLogs'));
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->schema()->columnType('original', 'json');
        $AuditLogsTable->schema()->columnType('changed', 'json');
        $AuditLogsTable->schema()->columnType('meta', 'json');

        $this->TablePersister->config([
            'serializeFields' => false
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }
}
