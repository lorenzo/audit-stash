<?php

namespace AuditStash;

interface PersisterInterface
{
    public function logEvents(array $auditLogs);
}
