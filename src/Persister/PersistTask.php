<?php
declare(strict_types=1);

namespace AuditStash\Persister;

use AuditStash\EventFactory;
use AuditStash\EventInterface;
use AuditStash\PersisterInterface;

/**
 * Used to directly persist event logs into the configured persister.
 */
class PersistTask
{
    /**
     * The persister object to use.
     *
     * @var PersisterInterface|null`
     */
    protected ?PersisterInterface $persister;

    /**
     * Persists a list of event logs represented in arrays
     * or that actually are instances of EventInterface.
     *
     * @param array $events The events to persist
     * @return void
     * @throws \ReflectionException
     */
    public function persist(array $events): void
    {
        $factory = new EventFactory();
        $events = array_map(
            fn (EventInterface|array $event): EventInterface => is_array($event) ?
                $factory->create($event) :
                $event,
            $events
        );
        $this->persister()->logEvents($events);
    }

    /**
     * Sets the persister object to use for logging al audit events.
     * If called if no arguments, it will return the ElasitSearchPersister.
     *
     * @param \AuditStash\PersisterInterface|null $persister The persister object to use
     * @return \AuditStash\PersisterInterface|null The configured persister
     */
    public function persister(?PersisterInterface $persister = null): ?PersisterInterface
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
