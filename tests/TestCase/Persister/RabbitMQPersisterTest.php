<?php

namespace AuditStash\Test\TestCase\Persister;

use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\AuditDeleteEvent;
use AuditStash\Persister\RabbitMQPersister;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use ProcessMQ\Connection\RabbitMQConnection;

class RabbitMQPersisterTest extends TestCase
{
    /**
     * Tests that using the defaults calls the right methods.
     *
     * @return void
     */
    public function testLogDefaults()
    {
        $client = $this->getMockBuilder(RabbitMQConnection::class)
            ->disableOriginalConstructor()
            ->setMethods(['send'])
            ->getMock();

        $persister = new RabbitMQPersister();
        $persister->connection($client);
        $data = [
            'title' => 'A new article',
            'body' => 'article body',
            'author_id' => 1,
            'published' => 'Y'
        ];

        $events[] = new AuditCreateEvent('1234', 50, 'articles', $data, $data, new Entity());
        $events[] = new AuditDeleteEvent('1234', 2, 'comments', 'articles');

        $client->expects($this->once())
            ->method('send')
            ->with('audits.persist', 'store', $events, ['delivery_mode' => 2]);

        $persister->logEvents($events);
    }

    /**
     * Tests overriding defaults.
     *
     * @return void
     */
    public function testLogOverrideDefaults()
    {
        $client = $this->getMockBuilder(RabbitMQConnection::class)
            ->disableOriginalConstructor()
            ->setMethods(['send'])
            ->getMock();

        $persister = new RabbitMQPersister(['delivery_mode' => 1, 'routing' => 'foo', 'exchange' => 'bar']);
        $persister->connection($client);
        $data = [
            'title' => 'A new article',
            'body' => 'article body',
            'author_id' => 1,
            'published' => 'Y'
        ];

        $events[] = new AuditCreateEvent('1234', 50, 'articles', $data, $data, new Entity());
        $events[] = new AuditDeleteEvent('1234', 2, 'comments', 'articles');

        $client->expects($this->once())
            ->method('send')
            ->with('bar', 'foo', $events, ['delivery_mode' => 1]);

        $persister->logEvents($events);
    }
}
