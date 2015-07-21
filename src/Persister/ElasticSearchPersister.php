<?php

namespace AuditStash\Persister;

use AuditStash\PersisterInterface;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Elastica\Client;
use Elastica\Document;

/**
 * Implementes audit logs events persisting using ElastiSearch
 *
 */
class ElasticSearchPersister implements PersisterInterface
{

    /**
     * The client or connection to ElastiSearch
     *
     * @var Elastica\Client;
     */
    protected $connection;

    /**
     * Persists all of the audit log event objects that are provided
     *
     * @param array $auditLogs An array of EventInterface objects
     * @return void
     */
    public function logEvents(array $auditLogs)
    {
        $index = $this->connection()->getIndex()->getName();
        $documents = $this->transformToDocuments($auditLogs, $index);
        $this->connection()->addDocuments($documents);
    }

    /**
     * Transforms the EventInterface objects to Elastica Documents
     *
     * @param array $auditLogs An array of EventInterface objects.
     * @param string $index The name of the index where the documents will be stored.
     * @return array
     */
    protected function transformToDocuments($auditLogs, $index)
    {
        $documents = [];
        foreach ($auditLogs as $log) {
            $eventType = $log->getEventType();
            $data = [
                '@timestamp' => $log->getTimestamp(),
                'transaction' => $log->getTransactionId(),
                'type' => $log->getEventType(),
                'primary_key' => $log->getId(),
                'source' => $log->getSourceName(),
                'parent_source' => $log->getParentSourceName(),
                'original' => $eventType === 'delete' ? null : $log->getOriginal(),
                'changed' => $eventType === 'delete' ? null : $log->getChanged(),
                'meta' => $log->getMetaInfo()
            ];
            $documents[] = new Document('', $data, $log->getSourceName(), $index);
        }

        return $documents;
    }

    /**
     * Sets the client connection to elastic search when passed.
     * If no arguments are provided, it returns the current connection.
     *
     * @param Elastica\Client $connection The conneciton to elastic search
     * @return Elastica\Client
     */
    public function connection(Client $connection = null)
    {
        if ($connection === null) {
            if ($this->connection === null) {
                $this->connection = ConnectionManager::get('auditlog_elastic');
            }
            return $this->connection;
        }

        return $this->connection = $connection;
    }
}
