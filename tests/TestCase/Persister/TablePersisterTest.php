<?php

namespace AuditStash\Test\TestCase\Persister;

use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\BaseEvent;
use AuditStash\Persister\TablePersister;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

class AuditLogsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('audit_logs');
        $this->setPrimaryKey('id');

        $this->setSchema([
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
    public function setUp(): void
    {
        parent::setUp();

        $this->TablePersister = new TablePersister();

        TableRegistry::getTableLocator()->setConfig('AuditLogs', [
            'className' => AuditLogsTable::class
        ]);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
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
        $this->assertEquals($expected, $this->TablePersister->getConfig());
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
        $this->assertEquals('Custom', $this->TablePersister->getTable()->getAlias());
    }

    public function testSetTableAsObject()
    {
        $customTable = TableRegistry::get('Custom');
        $this->assertInstanceOf(AuditLogsTable::class, $this->TablePersister->getTable());
        $this->assertInstanceOf(TablePersister::class, $this->TablePersister->setTable($customTable));
        $this->assertSame($customTable, $this->TablePersister->getTable());
    }

    public function testSetInvalidTable()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The `$table` argument must be either a table alias, or an instance of `\Cake\ORM\Table`.');
        $this->TablePersister->setTable(null);
    }

    public function testSerializeNull()
    {
        $event = self::createAuditEvent();
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
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
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
        $event = self::createAuditEvent(1, [], []);
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
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
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
        $event = self::createAuditEvent(null, [], []);
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
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'extractMetaFields' => true
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testExtractMetaFieldsDoNotUnset()
    {
        $event = self::createAuditEvent(null, [], []);
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
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
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
        $event = self::createAuditEvent(null, [], []);
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
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'extractMetaFields' => true,
            'unsetExtractedMetaFields' => false
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testErrorLogging()
    {
        $event = self::createAuditEvent(null, [], []);

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
        $logged->setError('field', ['error']);
        $logged->setSource('AuditLogs');

        $TablePersister
            ->expects($this->once())
            ->method('log');

        $TablePersister->getTable()->getEventManager()->on(
            'Model.beforeSave',
            function ($event, EntityInterface $entity) {
                $entity->setError('field', ['error']);
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

        $TablePersister->setConfig([
            'logErrors' => false
        ]);
        $TablePersister->getTable()->getEventManager()->on(
            'Model.beforeSave',
            function ($event, EntityInterface $entity) {
                return false;
            }
        );

        $event = self::createAuditEvent(null, [], []);
        $TablePersister->logEvents([$event]);
    }

    public function testCompoundPrimaryKeyExtractDefault()
    {
        $event = self::createAuditEvent([1, 2, 3], [], []);
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
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('primary_key', 'string');

        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testPrimaryKeyExtractRaw()
    {
        $event = self::createAuditEvent(null, [], []);

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
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_RAW
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testCompoundPrimaryKeyExtractRaw()
    {
        $event = self::createAuditEvent([1, 2, 3], [], []);

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
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('primary_key', 'json');

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_RAW
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testPrimaryKeyExtractProperties()
    {
        $event = self::createAuditEvent(null, [], []);

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
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_PROPERTIES
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testCompoundPrimaryKeyExtractProperties()
    {
        $event = self::createAuditEvent([1, 2, 3], [], []);

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
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_PROPERTIES
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testPrimaryKeyExtractSerialized()
    {
        $event = self::createAuditEvent('pk', [], []);

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
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('primary_key', 'string');

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_SERIALIZED
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testCompoundPrimaryKeyExtractSerialized()
    {
        $event = self::createAuditEvent([1, 2, 3], [], []);

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
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('primary_key', 'string');

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_SERIALIZED
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function testDoNotSerializeFields()
    {
        $event = self::createAuditEvent(null, [], []);
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
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('original', 'json');
        $AuditLogsTable->getSchema()->setColumnType('changed', 'json');
        $AuditLogsTable->getSchema()->setColumnType('meta', 'json');

        $this->TablePersister->setConfig([
            'serializeFields' => false
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    public function getMockForModel($alias, array $methods = [], array $options = [])
    {
	    return parent::getMockForModel($alias, $methods, $options + [
		    'className' => AuditLogsTable::class
	    ]);
    }

    /**
     * @param mixed $id
     * @param array|null $changed
     * @param array|null $original
     * @return BaseEvent
     */
    private static function createAuditEvent($id = null, ?array $changed = null, ?array $original = null): BaseEvent
    {
        return new AuditCreateEvent(
            '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            $id ?? 1,
            'source',
            $changed,
            $original,
            new Entity()
        );
    }
}
