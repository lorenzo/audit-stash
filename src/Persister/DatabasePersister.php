<?php
namespace AuditStash\Persister;

use AuditStash\PersisterInterface;
use Cake\Datasource\ModelAwareTrait;
use DateTime;

/**
 * Class DatabasePersister
 *
 * Implements audit logs events persisting using MySQL Database.
 *
 * @package App\Persister
 * @author Cake Development Corporation
 * @deprecated Use \AuditStash\Persister\TablePersister instead.
 */
class DatabasePersister implements PersisterInterface
{
    use ModelAwareTrait;

    /**
     * Persists all of the audit log event objects that are provided
     *
     * @param array $auditLogs An array of EventInterface objects
     * @return void
     */
    public function logEvents(array $auditLogs)
    {
        foreach ($auditLogs as $log) {
            $eventType = $log->getEventType();
            $primaryKey = $log->getId();
            if (is_array($primaryKey)) {
                if (count($primaryKey) == 1) {
                    $primaryKey = array_pop($primaryKey);
                } else {
                    $primaryKey = json_encode($primaryKey);
                }
            }
            $date = new DateTime($log->getTimestamp());
            $meta = (array)$log->getMetaInfo();
            $data = [
                'created' => $date,
                'transaction' => $log->getTransactionId(),
                'type' => $eventType,
                'source_key' => $primaryKey,
                'source' => $log->getSourceName(),
                'parent_source' => $log->getParentSourceName(),
                'original' => $eventType === 'delete' ? null : json_encode($log->getOriginal()),
                'changed' => $eventType === 'delete' ? null : json_encode($log->getChanged()),
                'meta' => json_encode($meta)
            ];
            $Audit = $this->loadModel('Audits');
            if (!empty($meta['user'])) {
                $data['user_id'] = $meta['user'];
            }
            $record = $Audit->newEntity($data);
            $Audit->save($record);
        }
    }
}
