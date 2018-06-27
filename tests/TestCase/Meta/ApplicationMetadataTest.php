<?php

namespace AuditStash\Test\Meta;

use AuditStash\Event\AuditDeleteEvent;
use AuditStash\Meta\ApplicationMetadata;
use Cake\Event\EventDispatcherTrait;
use Cake\TestSuite\TestCase;

class ApplicationMetadataTest extends TestCase
{

    use EventDispatcherTrait;

    /**
     * Tests that metadata is added to the audit log objects.
     *
     * @return void
     */
    public function testDataIsAdded()
    {
        $listener = new ApplicationMetadata('my_app', ['extra' => 'thing']);
        $this->getEventManager()->on($listener);
        $logs[] = new AuditDeleteEvent(1234, 1, 'articles');
        $event = $this->dispatchEvent('AuditStash.beforeLog', ['logs' => $logs]);

        $expected = [
            'app_name' => 'my_app',
            'extra' => 'thing',
        ];
        $this->assertEquals($expected, $logs[0]->getMetaInfo());
    }
}
