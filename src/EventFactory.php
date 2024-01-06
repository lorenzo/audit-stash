<?php

namespace AuditStash;

use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\AuditDeleteEvent;
use AuditStash\Event\AuditUpdateEvent;
use ReflectionObject;

/**
 * Can be used to convert an array of data obtained from elastic search
 * to convert it to an EventInterface object.
 */
class EventFactory
{
    /**
     * Converts an array of data as coming from elastic search and
     * converts it into an AuditStash\EventInterface object.
     *
     * @param array $data The array data from elastic search
     * @return \AuditStash\EventInterface
     * @throws \ReflectionException
     */
    public function create(array $data)
    {
        $map = [
            'create' => AuditCreateEvent::class,
            'update' => AuditUpdateEvent::class,
            'delete' => AuditDeleteEvent::class,
        ];

        if ($data['type'] !== 'delete') {
            $event = new $map[$data['type']](
                $data['transaction'],
                $data['primary_key'],
                $data['source'],
                $data['changed'],
                $data['original'],
                null
            );
        } else {
            $event = new $map[$data['type']](
                $data['transaction'],
                $data['primary_key'],
                $data['source']
            );
        }

        if (isset($data['parent_source'])) {
            $event->setParentSourceName($data['parent_source']);
        }

        $reflection = new ReflectionObject($event);
        $timestamp = $reflection->getProperty('timestamp');
        $timestamp->setAccessible(true);
        $timestamp->setValue($event, $data['@timestamp']);
        $event->setMetaInfo($data['meta']);

        return $event;
    }
}
