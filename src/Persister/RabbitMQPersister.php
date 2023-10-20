<?php
declare(strict_types=1);

namespace AuditStash\Persister;

use AuditStash\PersisterInterface;
use Cake\Datasource\ConnectionManager;
use ProcessMQ\Connection\RabbitMQConnection;

/**
 * Implements audit logs events persisting using RabbitMQ.
 */
class RabbitMQPersister implements PersisterInterface
{
    /**
     * The client or connection to RabbitMQ.
     *
     * @var \ProcessMQ\Connection\RabbitMQConnection|null;
     */
    protected ?RabbitMQConnection $connection;

    /**
     * The options set for this persister.
     *
     * @var array
     */
    protected array $options;

    /**
     * Sets the options for this persister. The available options are:
     *
     * @param array $options
     * - connection: The connection name for rabbitmq as configured in ConnectionManager
     * - delivery_mode: The delivery_mode to use for each message (default: 2 for persisting messages in disk)
     * - exchange: The exchange name where to publish the messages
     * - routing: The routing name to use inside the exchange
     * @return void
     */
    public function __construct(array $options = [])
    {
        $options += [
            'connection' => 'auditlog_rabbit',
            'delivery_mode' => 2,
            'exchange' => 'audits.persist',
            'routing' => 'store',
        ];
        $this->options = $options;
    }

    /**
     * Persists all the audit log event objects that are provided.
     *
     * @param array<\AuditStash\EventInterface> $auditLogs An array of EventInterface objects
     * @return void
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    public function logEvents(array $auditLogs): void
    {
        $this->connection()->send(
            $this->options['exchange'],
            $this->options['routing'],
            $auditLogs,
            ['delivery_mode' => $this->options['delivery_mode']]
        );
    }

    /**
     * Sets the client connection to elastic search when passed.
     * If no arguments are provided, it returns the current connection.
     *
     * @param \ProcessMQ\Connection\RabbitMQConnection|null $connection The conneciton to elastic search
     * @return \ProcessMQ\Connection\RabbitMQConnection
     */
    public function connection(?RabbitMQConnection $connection = null): RabbitMQConnection
    {
        if ($connection === null) {
            if ($this->connection === null) {
                /** @var \ProcessMQ\Connection\RabbitMQConnection $connection */
                $connection = ConnectionManager::get($this->options['connection']);
                $this->connection = $connection;
            }

            return $this->connection;
        }

        return $this->connection = $connection;
    }
}
