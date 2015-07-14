<?php

namespace AuditStash\Event;

class AuditDeleteEvent extends BaseEvent
{

    public function getEventType()
    {
        return 'delete';
    }
}

