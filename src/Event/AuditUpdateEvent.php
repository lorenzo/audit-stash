<?php

namespace AuditStash\Event;

class AuditUpdateEvent extends BaseEvent
{

    public function getEventType()
    {
        return 'update';
    }
}
