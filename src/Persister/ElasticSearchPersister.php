<?php
declare(strict_types=1);

namespace AuditStash\Persister;

use AuditStash\Exception;
use AuditStash\PersisterInterface;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\Datasource\Connection;
use Elastica\Document;

/**
 * Implements audit logs events persisting using Elasticsearch.
 */
class ElasticSearchPersister implements PersisterInterface
{
    /**
     * The client or connection to Elasticsearch.
     *
     * @var \Cake\ElasticSearch\Datasource\Connection|null
     */
    protected ?Connection $connection;

    /**
     * Whether to use the transaction ids as document ids.
     *
     * @var bool
     */
    protected bool $useTransactionId = false;

    /**
     * Elasticsearch index to store documents
     *
     * @var string
     */
    protected mixed $index;

    /**
     * Elasticsearch mapping type of documents
     *
     * @var string
     */
    protected mixed $type;

    /**
     * Sets the options for this persister. The available options are:
     *
     * - connection: The client of connection to Elasticsearch
     * - userTransactionId: Whether to use the transaction ids as document ids
     * - index: The Elasticsearch index to store documents
     * - type: The Elasticsearch mapping type of documents
     *
     * @param array $options
     * @return void
     * @throws \AuditStash\Exception
     */
    public function __construct(array $options = [])
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
     * Persists all the audit log event objects that are provided.
     *
     * @param array<\AuditStash\EventInterface> $auditLogs An array of EventInterface objects
     * @return void
     */
    public function logEvents(array $auditLogs): void
    {
        $documents = $this->transformToDocuments($auditLogs);

        $connection = $this->getConnection();
        $client = $connection->getDriver();
        $client->addDocuments($documents);
    }

    /**
     * Transforms the EventInterface objects to Elastica Documents.
     *
     * @param array<\AuditStash\EventInterface> $auditLogs An array of EventInterface objects.
     * @return array
     */
    protected function transformToDocuments(array $auditLogs): array
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
                'meta' => $log->getMetaInfo(),
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
     * @param bool $use Whether to copy the transactionId as the document id
     * @return void
     */
    public function reuseTransactionId(bool $use = true): void
    {
        $this->useTransactionId = $use;
    }

    /**
     * Sets the client connection to elastic search.
     *
     * @param \Cake\ElasticSearch\Datasource\Connection $connection The connection to elastic search
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
     * @return \Cake\ElasticSearch\Datasource\Connection
     */
    public function getConnection(): Connection
    {
        if ($this->connection === null) {
            /**
             * @var \Cake\ElasticSearch\Datasource\Connection $connection
             */
            $connection = ConnectionManager::get('auditlog_elastic');
            $this->connection = $connection;
        }

        return $this->connection;
    }

    /**
     * Sets the Elasticsearch index used to store events
     *
     * @param string $index Name of the Elasticsearch index
     * @return $this
     */
    public function setIndex(string $index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Gets the Elasticsearch index to persist events
     *
     * @return string Name of the Elasticsearch index
     */
    public function getIndex(): string
    {
        return sprintf($this->index, '-' . gmdate('Y.m.d'));
    }

    /**
     * Sets the Elasticsearch mapping type of stored events
     *
     * @param string $type Name of the Elasticsearch mapping type
     * @return $this
     */
    public function setType(string $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Gets the Elasticsearch mapping type of stored events
     *
     * @return string Name of the Elasticsearch mapping type
     */
    public function getType(): string
    {
        return $this->type;
    }
}
