<?php

namespace AuditStash\Event;

/**
 * Represents an audit log event for a modified created record.
 */
class AuditUpdateEvent extends BaseEvent
{
    /**
     * Returns the type name of this event object.
     *
     * @return string
     */
    public function getEventType()
    {
        return 'update';
    }
}
