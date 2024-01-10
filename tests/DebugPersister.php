<?php
declare(strict_types=1);

namespace AuditStash\Test;

use AuditStash\PersisterInterface;

class DebugPersister implements PersisterInterface
{
    public function logEvents(array $events): void
    {
    }
}
