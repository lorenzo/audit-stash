<?php

namespace AuditStash\Model\Behavior;

use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\AuditDeleteEvent;
use AuditStash\Event\AuditUpdateEvent;
use AuditStash\PersisterInterface;
use Cake\ORM\Behavior;
use Cake\Event\Event;
use Cake\Datasource\EntityInterface;

class AuditLogBehavior extends Behavior
{

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'implementedEvents' => [
            'Model.afterSaveCommit' => 'onSave',
            'Model.afterDeleteCommit' => 'onDelete'
        ],
        'blacklist' => ['created', 'modified'],
        'whitelist' => []
    ];

    /**
     * The persiter object
     *
     * @var PersisterInterface
     */
    protected $persister;

    public function onSave(Event $event, EntityInterface $entity, $options)
    {
        $config = $this->_config;
        if (empty($config['whitelist'])) {
            $config['whitelist'] = $this->_table->schema()->columns();
        }

        $config['whitelist'] = array_diff($config['whitelist'], $config['blacklist']);
        $changed = $entity->extract($config['whitelist'], true);

        if (!$changed) {
            return;
        }

        $original = $entity->extractOriginal(array_keys($changed));
        $primary = $entity->extract((array)$this->_table->primaryKey());

        $auditEvent = $entity->isNew() ? AuditCreateEvent::class : AuditUpdateEvent::class;
        $auditEvent = new $auditEvent($primary, $this->_table->table(), $changed, $original);

        $this->persister()->logAudit($auditEvent);
    }

    public function onDelete(Event $event, EntityInterface $entity, $options)
    {
    }

    public function persister(PersisterInterface $persister = null)
    {
        if ($persister === null) {
            return $this->persister;
        }
        $this->persister = $persister;
    }
}
