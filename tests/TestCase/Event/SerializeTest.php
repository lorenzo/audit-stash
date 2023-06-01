<?php

namespace AuditStash\Test\TestCase\Event;

use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\AuditDeleteEvent;
use AuditStash\Event\AuditUpdateEvent;
use AuditStash\EventFactory;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;

class SerializeTest extends TestCase
{
    /**
     * Tests serializing a create event.
     *
     * @return void
     */
    public function testSerializeCreate()
    {
        $event = new AuditCreateEvent(
            '123', 50, 'articles', ['title' => 'foo'], ['title' => 'bar'], new Entity()
        );
        $event->setMetaInfo(['extra' => 'info']);
        $serialized = serialize($event);
        $this->assertEquals($event, unserialize($serialized));
    }

    /**
     * Tests serializing an update event.
     *
     * @return void
     */
    public function testSerializeUpdate()
    {
        $event = new AuditUpdateEvent(
            '123', 50, 'articles', ['title' => 'foo'], ['title' => 'bar'], new Entity()
        );
        $event->setMetaInfo(['extra' => 'info']);
        $serialized = serialize($event);
        $this->assertEquals($event, unserialize($serialized));
    }

    /**
     * Tests serializing a delete event.
     *
     * @return void
     */
    public function testSerializeDelete()
    {
        $event = new AuditDeleteEvent('123', 50, 'articles', 'authors');
        $event->setMetaInfo(['extra' => 'info']);
        $serialized = serialize($event);
        $this->assertEquals($event, unserialize($serialized));
    }

    /**
     * Tests json serializing a create event.
     *
     * @return void
     */
    public function testJsonSerializeCreate()
    {
        $factory = new EventFactory();
        $event = new AuditCreateEvent(
            '123', 50, 'articles', ['title' => 'foo'], ['title' => 'bar'], null
        );
        $event->setMetaInfo(['extra' => 'info']);
        $serialized = json_encode($event);
        $result = $factory->create(json_decode($serialized, true));
        $this->assertEquals($event, $result);
    }

    /**
     * Tests json serializing an update event.
     *
     * @return void
     */
    public function testJsonSerializeUpdate()
    {
        $factory = new EventFactory();
        $event = new AuditUpdateEvent(
            '123', 50, 'articles', ['title' => 'foo'], ['title' => 'bar'], null
        );
        $event->setMetaInfo(['extra' => 'info']);
        $serialized = json_encode($event);
        $result = $factory->create(json_decode($serialized, true));
        $this->assertEquals($event, $result);
    }

    /**
     * Tests json serializing a delete event.
     *
     * @return void
     */
    public function testJsonSerializeDelete()
    {
        $factory = new EventFactory();
        $event = new AuditDeleteEvent('123', 50, 'articles', 'authors');
        $event->setMetaInfo(['extra' => 'info']);
        $serialized = json_encode($event);
        $result = $factory->create(json_decode($serialized, true));
        $this->assertEquals($event, $result);
    }
}
