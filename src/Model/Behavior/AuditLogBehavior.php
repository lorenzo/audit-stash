<?php

namespace AuditStash\Model\Behavior;

use ArrayObject;
use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\AuditDeleteEvent;
use AuditStash\Event\AuditUpdateEvent;
use AuditStash\PersisterInterface;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\Behavior;
use SplObjectStorage;

class AuditLogBehavior extends Behavior
{

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'blacklist' => ['created', 'modified'],
        'whitelist' => []
    ];

    public function implementedEvents()
    {
        return [
            'Model.beforeSave' => 'beforeSave',
            'Model.afterSave' => 'afterSave',
            'Model.afterSaveCommit' => 'afterCommit',
            'Model.afterDeleteCommit' => 'onDelete'
        ];
    }

    /**
     * The persiter object
     *
     * @var PersisterInterface
     */
    protected $persister;

    public function beforeSave(Event $event, EntityInterface $entity, ArrayObject $options)
    {
        if (!empty($options['_primary'])) {
            $options['_auditQueue'] = new SplObjectStorage;
        }

        if (!isset($options['_auditQueue'])) {
            return;
        }
    }

    public function afterSave(Event $event, EntityInterface $entity, $options)
    {
        if (!isset($options['_auditQueue'])) {
            return;
        }

        $config = $this->_config;
        if (empty($config['whitelist'])) {
            $config['whitelist'] = $this->_table->schema()->columns();
            $config['whitelist'] = array_merge(
                $config['whitelist'],
                $this->getAssociationProperties(array_keys($options['associated']))
            );
        }

        $config['whitelist'] = array_diff($config['whitelist'], $config['blacklist']);
        $changed = $entity->extract($config['whitelist'], true);

        if (!$changed) {
            return;
        }

        $original = $entity->extractOriginal(array_keys($changed));
        $whitelist = $config['whitelist'];
        $options['_auditQueue']->attach($entity, compact('changed', 'original', 'whitelist'));

        $properties = $this->getAssociationProperties(array_keys($options['associated']));
        foreach ($properties as $property) {
            unset($changed[$property], $original[$property]);
        }

        $primary = $entity->extract((array)$this->_table->primaryKey());
        $auditEvent = $entity->isNew() ? AuditCreateEvent::class : AuditUpdateEvent::class;

        $auditEvent = new $auditEvent($primary, $this->_table->table(), $changed, $original);
        $options['_auditQueue'][$entity] = $auditEvent;
    }

    public function afterCommit(Event $event, EntityInterface $entity, $options)
    {
        if (!isset($options['_auditQueue'])) {
            return;
        }

        $events = collection($options['_auditQueue'])
            ->map(function ($entity, $pos, $it) {
                return $it->getInfo();
            });
        $persister = $this->persister()->logEvents($events->toList());
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

    protected function getAssociationProperties($associated)
    {
        $associations = $this->_table->associations();
        $result = [];

        foreach ($associated as $name) {
            $result[] = $associations->get($name)->property();
        }

        return $result;
    }
}
