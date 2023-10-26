<?php
declare(strict_types=1);

namespace AuditStash\Connection;

use Cake\Cache\Cache;
use Cake\Core\Exception\CakeException;
use Cake\Datasource\ConnectionInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\SimpleCache\CacheInterface;

/**
 * RabbitMgConnection
 */
class RabbitMqConnection implements ConnectionInterface
{
    /**
     * Contains the configuration params for this connection.
     *
     * @var array<string, mixed>
     */
    protected array $config = [
        'name' => 'default',
        'host' => null,
        'port' => null,
        'user' => null,
        'password' => null,
        'vhost' => '/',
        'insist' => false,
        'login_method' => 'AMQPLAIN',
        'login_response' => null,
        'locale' => 'en_US',
        'connection_timeout' => 3.0,
        'read_write_timeout' => 3.0,
        'context' => null,
        'keepalive' => false,
        'heartbeat' => 0,
        'channel_rpc_timeout' => 0.0,
        'ssl_protocol' => null,
        'config' => null
    ];

    /**
     * @var AMQPStreamConnection
     */
    protected AMQPStreamConnection $client;

    /**
     * @var CacheInterface|null
     */
    protected ?CacheInterface $cacher;

    /**
     * Constructor.
     *
     * ### Available options:
     *
     * @see \PhpAmqpLib\Connection\AMQPStreamConnection::__construct()
     *
     * @param array<string, mixed> $config Configuration array.
     * @throws \Exception
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->client = new AMQPStreamConnection(
            host: $this->config['host'],
            port: $this->config['port'],
            user: $this->config['user'],
            password: $this->config['password'],
            vhost: $this->config['vhost'],
            insist: $this->config['insist'],
            login_method: $this->config['login_method'],
            login_response: $this->config['login_response'],
            locale: $this->config['locale'],
            connection_timeout: $this->config['connection_timeout'],
            read_write_timeout: $this->config['read_write_timeout'],
            context: $this->config['context'],
            keepalive: $this->config['keepalive'],
            heartbeat: $this->config['heartbeat'],
            channel_rpc_timeout: $this->config['channel_rpc_timeout'],
            ssl_protocol: $this->config['ssl_protocol'],
            config: $this->config['config'],
        );
    }

    /**
     * @inheritDoc
     */
    public function getDriver(string $role = self::ROLE_WRITE): AMQPStreamConnection
    {
        return $this->client;
    }

    /**
     * @inheritDoc
     */
    public function setCacher(CacheInterface $cacher)
    {
        $this->cacher = $cacher;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCacher(): CacheInterface
    {
        if ($this->cacher !== null) {
            return $this->cacher;
        }

        $configName = $this->_config['cacheMetadata'] ?? '_cake_model_';
        if (!is_string($configName)) {
            $configName = '_cake_model_';
        }

        if (!class_exists(Cache::class)) {
            throw new CakeException(
                'To use caching you must either set a cacher using Connection::setCacher()' .
                ' or require the cakephp/cache package in your composer config.'
            );
        }

        return $this->cacher = Cache::pool($configName);
    }

    /**
     * @inheritDoc
     */
    public function configName(): string
    {
        return $this->_config['name'] ?? '';
    }

    /**
     * @inheritDoc
     */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * Send message
     */
    public function send(string $topic, array $data, array $options = []): void
    {
        $AMQPChannel = $this->client->channel();
        $AMQPChannel->exchange_declare($topic, AMQPExchangeType::FANOUT);
        $AMQPMessage = new AMQPMessage(
            json_encode($data),
            [
                'content-type' => 'application/json',
                'delivery_mode' => $options['delivery_mode'] ?? AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]
        );

        $AMQPChannel->basic_publish($AMQPMessage, $topic);
        $AMQPChannel->close();
    }
}
