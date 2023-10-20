<?php
declare(strict_types=1);

namespace AuditStash\Persister;

use Cake\Datasource\EntityInterface;
use Cake\Error\Debugger;
use Cake\Log\LogTrait as BaseLogTrait;

trait LogTrait
{
    use BaseLogTrait;

    /**
     * Converts an entity to an error log message.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity to convert.
     * @param int $depth The depth up to which to export the entity data.
     * @return string
     */
    protected function toErrorLog(EntityInterface $entity, int $depth = 4): string
    {
        return sprintf(
            '[%s] Persisting audit log failed. Data:' . PHP_EOL . '%s',
            self::class,
            Debugger::exportVar($entity, $depth)
        );
    }
}
