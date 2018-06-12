<?php

namespace AuditStash\Persister;

use AuditStash\Exception;
use AuditStash\PersisterInterface;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Elastica\Document;

/**
 * Implementes audit logs events persisting using Elasticsearch.
 */
class ElasticSearchPersister implements PersisterInterface
{
    /**
     * The client or connection to Elasticsearch.
     *
     * @var Cake\ElasticSearch\Datasource\Connection
     */
    protected $connection;

    /**
     * Whether to use the transaction ids as document ids.
     *
     * @var bool
     */
    protected $useTransactionId = false;

    /**
     * Elasticsearch index to store documents
     *
     * @var string
     */
    protected $index;

    /**
     * Elasticsearch mapping type of documents
     *
     * @var string
     */
    protected $type;

    /**
     * Sets the options for this persister. The available options are:
     *
     * - connection: The client of connection to Elasticsearch
     * - userTransactionId: Whether to use the transaction ids as document ids
     * - index: The Elasticsearch index to store documents
     * - type: The Elasticsearch mapping type of documents
     *
     * @return void
     */
    public function __construct($options = [])
    {
        if (isset($options['connection'])) {
            $this->setConnection($options['connection']);
        }

        if (!isset($options['index'])) {
            throw new Exception("You need to configure a 'index' name to store your events.");
        }
        $this->index = $options['index'];

        if (!isset($options['type'])) {
            throw new Exception("You need to configure a 'type' name to map your events.");
        }
        $this->type = $options['type'];

        if (isset($options['useTransactionId'])) {
            $this->useTransactionId = (bool)$options['useTransactionId'];
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
        $documents = $this->transformToDocuments($auditLogs);

        $client->addDocuments($documents);
    }

    /**
     * Transforms the EventInterface objects to Elastica Documents.
     *
     * @param array $auditLogs An array of EventInterface objects.
     * @return array
     */
    protected function transformToDocuments($auditLogs)
    {
        $index = $this->getIndex();
        $type = $this->getType();
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
            $documents[] = new Document($id, $data, $type, $index);
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
    public function setConnection(Connection $connection)
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

    /**
     * Sets the Elasticsearch index used to store events
     *
     * @param string $index Name of the Elasticsearch index
     * @return $this
     */
    public function setIndex($index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Gets the Elasticsearch index to persist events
     *
     * @return string Name of the Elasticsearch index
     */
    public function getIndex()
    {
        return sprintf($this->index, '-' . gmdate('Y.m.d'));
    }

    /**
     * Sets the Elasticsearch mapping type of stored events
     *
     * @param string $type Name of the Elasticsearch mapping type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Gets the Elasticsearch mapping type of stored events
     *
     * @return string Name of the Elasticsearch mapping type
     */
    public function getType()
    {
        return $this->type;
    }
}
