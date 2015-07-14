<?php

namespace AuditStash;

interface PersisterInterface
{
    public function logAudit(EventInterface $event);
}
