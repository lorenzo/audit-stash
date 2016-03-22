<?php

namespace AuditStash\Meta;

use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\Network\Request;

/**
 * Event listener that is capable of enriching the audit logs
 * with the current request info.
 */
class RequestMetadata implements EventListenerInterface
{

    /**
     * The current request.
     *
     * @var Request
     */
    protected $request;

    /**
     * The current user name or id.
     *
     * @var mixed
     */
    protected $user;

    /**
     * Constructor.
     *
     * @param Request $request The current request
     * @param string|int $user The current user id or usernam
     */
    public function __construct($request, $user = null)
    {
        $this->request = $request;
        $this->user = $user;
    }

    /**
     * Returns an array with the events this class listens to.
     *
     * @return array
     */
    public function implementedEvents()
    {
        return ['AuditStash.beforeLog' => 'beforeLog'];
    }

    /**
     * Enriches all of the passed audit logs to add the request
     * info metadata.
     *
     * @param Event The AuditStash.beforeLog event
     * @param array $logs The audit log event objects
     * @return void
     */
    public function beforeLog(Event $event, array $logs)
    {
        $meta = [
            'ip' => $this->request->clientIp(),
            'url' => $this->request->here(),
            'user' => $this->user
        ];

        foreach ($logs as $log) {
            $log->setMetaInfo($log->getMetaInfo() + $meta);
        }
    }
}
