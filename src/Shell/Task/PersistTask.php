<?php

namespace AuditStash\Shell\Task;

use AuditStash\EventFactory;
use AuditStash\Persister\ElasticSearchPersister;
use AuditStash\PersisterInterface;
use Cake\Console\Shell;

/**
 * Used to directly persist event logs into the configured persister.
 */
class PersistTask extends Shell
{

    /**
     * The persister object to use.
     *
     * @var PersisterInterface
     */
    protected $persister;

    /**
     * Persists a list of event logs represented in arrays
     * or that actually are instances of EventInterface.
     *
     * @param array $events The events to persist
     * @return void
     */
    public function persist(array $events)
    {
        $factory = new EventFactory();
        $events = array_map(
            function ($event) use ($factory) {
                return is_array($event) ? $factory->create($event) : $event;
            },
            $events
        );
        $this->persister()->logEvents($events);
    }

    /**
     * Sets the persister object to use for logging al audit events.
     * If called if no arguments, it will return the ElasitSearchPersister.
     *
     * @param PersisterInterface $persister The persister object to use
     * @return PersisterInterface The configured persister
     */
    public function persister(PersisterInterface $persister = null)
    {
        if ($persister === null && $this->persister === null) {
            $persister = new ElasticSearchPersister();
        }

        if ($persister === null) {
            return $this->persister;
        }

        return $this->persister = $persister;
    }
}
