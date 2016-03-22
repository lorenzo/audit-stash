<?php

namespace AuditStash\Test\Persister;

use AuditStash\Event\AuditDeleteEvent;
use AuditStash\Meta\RequestMetadata;
use Cake\Event\EventManagertrait;
use Cake\Network\Request;
use Cake\TestSuite\TestCase;

class RequestMetadataTest extends TestCase
{

    use EventManagertrait;

    /**
     * Tests that request metadata is added to the audit log objects.
     *
     * @return void
     */
    public function testRequestDataIsAdded()
    {
        $request = $this->getMock(Request::class, ['clientIp', 'here']);
        $listener = new RequestMetadata($request, 'jose');
        $this->eventManager()->attach($listener);

        $request->expects($this->once())->method('clientIp')->will($this->returnValue('12345'));
        $request->expects($this->once())->method('here')->will($this->returnValue('/things?a=b'));
        $logs[] = new AuditDeleteEvent(1234, 1, 'articles');
        $event = $this->dispatchEvent('AuditStash.beforeLog', ['logs' => $logs]);

        $expected = [
            'ip' => '12345',
            'url' => '/things?a=b',
            'user' => 'jose'
        ];
        $this->assertEquals($expected, $logs[0]->getMetaInfo());
    }
}
