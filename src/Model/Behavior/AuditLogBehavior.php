<?php

namespace AuditStash\Model\Behavior;

use ArrayObject;
use AuditStash\Event\AuditCreateEvent;
use AuditStash\Event\AuditDeleteEvent;
use AuditStash\Event\AuditUpdateEvent;
use AuditStash\Persister\ElasticSearchPersister;
use AuditStash\PersisterInterface;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\Behavior;
use Cake\Utility\Inflector;
use Cake\Utility\Text;
use SplObjectStorage;

/**
 * This behavior can be used to log all the creations, modifications and deletions
 * done to a particular table.
 */
class AuditLogBehavior extends Behavior
{
    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'index' => null,
        'type' => null,
        'blacklist' => ['created', 'modified'],
        'whitelist' => [],
    ];

    /**
     * The persister object.
     *
     * @var PersisterInterface
     */
    protected $persister;

    /**
     * Returns the list of implemented events.
     *
     * @return array
     */
    public function implementedEvents(): array
    {
        $events = [
            'Model.beforeSave' => 'injectTracking',
            'Model.beforeDelete' => 'injectTracking',
            'Model.afterSave' => 'afterSave',
            'Model.afterDelete' => 'afterDelete',
        ];

        if (Configure::read('AuditStash.saveType') !== 'afterSave') {
            $events['Model.afterSaveCommit'] = 'afterCommit';
            $events['Model.afterDeleteCommit'] = 'afterCommit';
        }

        return $events;
    }

    /**
     * Conditionally adds the `_auditTransaction` and `_auditQueue` keys to $options. They are
     * used to track all changes done inside the same transaction.
     *
     * @param \Cake\Event\Event $event The Model event that is enclosed inside a transaction
     * @param \Cake\Datasource\EntityInterface $entity The entity that is to be saved
     * @param ArrayObject $options The options to be passed to the save or delete operation
     * @return void
     */
    public function injectTracking(Event $event, EntityInterface $entity, ArrayObject $options)
    {
        if (!isset($options['_auditTransaction'])) {
            $options['_auditTransaction'] = Text::uuid();
        }

        if (!isset($options['_auditQueue'])) {
            $options['_auditQueue'] = new SplObjectStorage();
        }
    }

    /**
     * Calculates the changes done to the entity and stores the audit log event object into the
     * log queue inside the `_auditQueue` key in $options.
     *
     * @param \Cake\Event\Event $event The Model event that is enclosed inside a transaction
     * @param \Cake\Datasource\EntityInterface $entity The entity that is to be saved
     * @param ArrayObject $options Options array containing the `_auditQueue` key
     * @return void
     */
    public function afterSave(Event $event, EntityInterface $entity, $options)
    {
        if (!isset($options['_auditQueue'])) {
            return;
        }

        $config = $this->_config;
        if (empty($config['whitelist'])) {
            $config['whitelist'] = $this->_table->getSchema()->columns();
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
        $properties = $this->getAssociationProperties(array_keys($options['associated']));
        foreach ($properties as $property) {
            unset($changed[$property], $original[$property]);
        }

        if (!$changed || ($original === $changed && !$entity->isNew())) {
            return;
        }

        $primary = $entity->extract((array)$this->_table->getPrimaryKey());
        $auditEvent = $entity->isNew() ? AuditCreateEvent::class : AuditUpdateEvent::class;

        $transaction = $options['_auditTransaction'];
        $auditEvent = new $auditEvent($transaction, $primary, $this->_table->getTable(), $changed, $original, $entity);

        if (!empty($options['_sourceTable'])) {
            $auditEvent->setParentSourceName($options['_sourceTable']->getTable());
        }

        $options['_auditQueue']->attach($entity, $auditEvent);

        if (Configure::read('AuditStash.saveType') === 'afterSave') {
            $this->afterCommit(new Event(''), $entity, $options);
        }
    }

    /**
     * Persists all audit log events stored in the `_eventQueue` key inside $options.
     *
     * @param \Cake\Event\Event $event The Model event that is enclosed inside a transaction
     * @param \Cake\Datasource\EntityInterface $entity The entity that is to be saved or deleted
     * @param ArrayObject $options Options array containing the `_auditQueue` key
     * @return void
     */
    public function afterCommit(Event $event, EntityInterface $entity, $options)
    {
        if (!isset($options['_auditQueue'])) {
            return;
        }

        $events = collection($options['_auditQueue'])
            ->map(function ($entity, $pos, $it) {
                return $it->getInfo();
            })
            ->toList();

        if (empty($events)) {
            return;
        }

        $data = $this->_table->dispatchEvent('AuditStash.beforeLog', ['logs' => $events]);
        $this->persister()->logEvents($data->getData('logs'));
    }

    /**
     * Persists all audit log events stored in the `_eventQueue` key inside $options.
     *
     * @param \Cake\Event\Event $event The Model event that is enclosed inside a transaction
     * @param \Cake\Datasource\EntityInterface $entity The entity that is to be saved or deleted
     * @param ArrayObject $options Options array containing the `_auditQueue` key
     * @return void
     */
    public function afterDelete(Event $event, EntityInterface $entity, $options)
    {
        if (!isset($options['_auditQueue'])) {
            return;
        }
        $transaction = $options['_auditTransaction'];
        $parent = isset($options['_sourceTable']) ? $options['_sourceTable']->getTable() : null;
        $primary = $entity->extract((array)$this->_table->getPrimaryKey());
        $auditEvent = new AuditDeleteEvent($transaction, $primary, $this->_table->getTable(), $parent);
        $options['_auditQueue']->attach($entity, $auditEvent);
        if (Configure::read('AuditStash.saveType') === 'afterSave') {
            $this->afterCommit(new Event(''), $entity, $options);
        }
    }

    /**
     * Sets the persister object to use for logging all audit events.
     * If called with no arguments, it will return the currently configured persister.
     *
     * @param \AuditStash\PersisterInterface $persister The persister object to use
     * @return \AuditStash\PersisterInterface The configured persister
     */
    public function persister(PersisterInterface $persister = null)
    {
        if ($persister === null && $this->persister === null) {
            $class = Configure::read('AuditStash.persister') ?: ElasticSearchPersister::class;
            $index = $this->getConfig('index') ?: $this->_table->getTable();
            $type = $this->getConfig('type') ?: Inflector::singularize($index);

            $persister = new $class(compact('index', 'type'));
        }

        if ($persister === null) {
            return $this->persister;
        }

        return $this->persister = $persister;
    }

    /**
     * Helper method used to get the property names of associations for a table.
     *
     * @param array $associated Whitelist of associations to look for
     * @return array List of property names
     */
    protected function getAssociationProperties($associated)
    {
        $associations = $this->_table->associations();
        $result = [];

        foreach ($associated as $name) {
            $result[] = $associations->get($name)->getProperty();
        }

        return $result;
    }
}
