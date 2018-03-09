<?php

namespace AuditStash\Persister;

use AuditStash\PersisterInterface;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Elastica\Client;
use Elastica\Document;

/**
 * Implementes audit logs events persisting using ElastiSearch.
 */
class ElasticSearchPersister implements PersisterInterface
{

    /**
     * The client or connection to ElasticSearch.
     *
     * @var Elastica\Client
     */
    protected $connection;

    /**
     * Whether to use the transaction ids as document ids.
     *
     * @var bool
     */
    protected $useTransactionId = false;

    /**
     * Default mapping type of documents in ElasticSearch
     *
     * @var string
     */
    protected $documentType = 'audit';

    /**
     * Sets the options for this persister. The available options are:
     *
     * - connection: The client of connection to ElasticSearch
     * - userTransactionId: Whether to use the transaction ids as document ids
     * - documentType: The ElasticSearch mapping type
     *
     * @return void
     */
    public function __construct($options = [])
    {
        if (isset($options['connection'])) {
            $this->setConnection($options['connection']);
        }

        if (isset($options['useTransactionId'])) {
            $this->useTransactionId = (bool)$options['useTransactionId'];
        }

        if (isset($options['documentType'])) {
            $this->documentType = $options['documentType'];
        }
    }

    /**
     * Persists all of the audit log event objects that are provided.
     *
     * @param array $auditLogs An array of EventInterface objects
     * @return void
     */
    public function logEvents(array $auditLogs)
    {
        $client = $this->getConnection();
        $index = sprintf($client->getConfig('index'), '-' . gmdate('Y.m.d'));

        print_r(" Persister: " . $index . " ");

        $documents = $this->transformToDocuments($auditLogs, $index);

        $client->addDocuments($documents);
    }

    /**
     * Transforms the EventInterface objects to Elastica Documents.
     *
     * @param array $auditLogs An array of EventInterface objects.
     * @param string $index The name of the index where the documents will be stored.
     * @return array
     */
    protected function transformToDocuments($auditLogs, $index)
    {
        $documents = [];
        foreach ($auditLogs as $log) {
            $primary = $log->getId();
            $primary = is_array($primary) ? array_values($primary) : $primary;
            $eventType = $log->getEventType();
            $data = [
                '@timestamp' => $log->getTimestamp(),
                'transaction' => $log->getTransactionId(),
                'type' => $eventType,
                'primary_key' => $primary,
                'source' => $log->getSourceName(),
                'parent_source' => $log->getParentSourceName(),
                'original' => $eventType === 'delete' ? null : $log->getOriginal(),
                'changed' => $eventType === 'delete' ? null : $log->getChanged(),
                'meta' => $log->getMetaInfo()
            ];
            $id = $this->useTransactionId ? $log->getTransactionId() : '';
            $documents[] = new Document($id, $data, $this->documentType, $index);
        }

        return $documents;
    }

    /**
     * If true is passed, the transactionId from the event logs will be used as the document
     * id in elastic search. Only enable this feature if you know that your transactions are
     * only comprised of a single event log per commit.
     *
     * @param bool $use Whether or not to copy the transactionId as the document id
     * @return void
     */
    public function reuseTransactionId($use = true)
    {
        $this->useTransactionId = $use;
    }

    /**
     * Sets the client connection to elastic search.
     *
     * @param Elastica\Client $connection The conneciton to elastic search
     * @return $this
     */
    public function setConnection(Client $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Gets the client connection to elastic search.
     *
     * If connection is not defined, create a new one.
     *
     * @return Elastica\Client
     */
    public function getConnection()
    {
        if ($this->connection === null) {
            $this->connection = ConnectionManager::get('auditlog_elastic');
        }

        return $this->connection;
    }

    /**
     * Sets the client connection to elastic search when passed.
     * If no arguments are provided, it returns the current connection.
     *
     * @deprecated Use getConnection()/setConnection() instead
     * @param Elastica\Client $connection The conneciton to elastic search
     * @return Elastica\Client
     */
    public function connection(Client $connection = null)
    {
        if ($connection !== null) {
            return $this->setConnection($connection);
        }

        return $this->getConnection();
    }
}
