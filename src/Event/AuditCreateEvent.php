<?php

namespace AuditStash\Event;

class AuditCreateEvent extends BaseEvent
{

    public function getEventType()
    {
        return 'create';
    }
}
