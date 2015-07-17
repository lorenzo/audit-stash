<?php

namespace AuditStash\Persister;

use AuditStash\PersisterInterface;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Elastica\Document;

class ElasticSearchPersister implements PersisterInterface
{

    protected $connection;

    public function logEvents(array $auditLogs)
    {
        $index = $this->connection()->getIndex()->getName();
        $documents = $this->transformToDocuments($auditLogs, $index);
        $this->connection()->addDocuments($documents);
    }

    public function transformToDocuments($auditLogs, $index)
    {
        $documents = [];
        foreach ($auditLogs as $log) {
            $eventType = $log->getEventType();
            $data = [
                'trasaction' => $log->getTransactionId(),
                'type' => $log->getEventType(),
                'primary_key' => $log->getId(),
                'source' => $log->getSourceName(),
                'parent_source' => $log->getParentSourceName(),
                'original' => $eventType === 'delete' ? null : $log->getOriginal(),
                'changed' => $eventType === 'delete' ? null : $log->getChanged()
            ];
            $documents[] = new Document('', $data, $log->getSourceName(), $index);
        }

        return $documents;
    }

    public function connection(Connection $connection = null)
    {
        if ($connection === null) {
            if ($this->connection === null) {
                $this->connection = ConnectionManager::get('auditlog_elastic');
            }
            return $this->connection;
        }

        $this->connection = $connection;
    }
}
