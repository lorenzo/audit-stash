<?php
declare(strict_types=1);

namespace AuditStash\Persister;

use AuditStash\Connection\RabbitMqConnection;
use AuditStash\PersisterInterface;
use Cake\Datasource\ConnectionManager;

/**
 * Implements audit logs events persisting using RabbitMQ.
 */
class RabbitMQPersister implements PersisterInterface
{
    /**
     * The client or connection to RabbitMQ.
     *
     * @var \AuditStash\Connection\RabbitMqConnection|null;
     */
    protected ?RabbitMqConnection $connection;

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
     */
    public function logEvents(array $auditLogs): void
    {
        $this->connection()->send(
            $this->options['exchange'],
            $auditLogs,
            ['delivery_mode' => $this->options['delivery_mode']]
        );
    }

    /**
     * Sets the client connection to elastic search when passed.
     * If no arguments are provided, it returns the current connection.
     *
     * @param RabbitMqConnection|null $connection The conneciton to elastic search
     * @return RabbitMqConnection
     */
    public function connection(?RabbitMqConnection $connection = null): RabbitMqConnection
    {
        if ($connection === null) {
            if ($this->connection === null) {
                /** @var \AuditStash\Connection\RabbitMqConnection $connection */
                $connection = ConnectionManager::get($this->options['connection']);
                $this->connection = $connection;
            }

            return $this->connection;
        }

        $this->connection = $connection;

        return $this->connection;
    }
}
