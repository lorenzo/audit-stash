<?php
declare(strict_types=1);

namespace AuditStash\Event;

use AuditStash\EventInterface;
use Cake\Datasource\EntityInterface;
use DateTime;
use ReturnTypeWillChange;

/**
 * Represents a change in the repository where the list of changes can be
 * tracked as a list of properties and their values.
 */
abstract class BaseEvent implements EventInterface
{
    use BaseEventTrait;
    use SerializableEventTrait;

    /**
     * The array of changed properties for the entity.
     *
     * @var array|null
     */
    protected ?array $changed;

    /**
     * The array of original properties before they got changed.
     *
     * @var array|null
     */
    protected ?array $original;

    /**
     * Constructor.
     *
     * @param string $transactionId The global transaction id
     * @param mixed $id The entities primary key
     * @param string $source The name of the source (table)
     * @param array|null $changed The array of changes that got detected for the entity
     * @param array|null $original The original values the entity had before it got changed
     * @param \Cake\Datasource\EntityInterface|null $entity The entity being changed
     */
    public function __construct(
        string $transactionId,
        mixed $id,
        string $source,
        ?array $changed,
        ?array $original,
        ?EntityInterface $entity = null
    ) {
        $this->transactionId = $transactionId;
        $this->id = $id;
        $this->source = $source;
        $this->changed = $changed;
        $this->original = $original;
        $this->timestamp = (new DateTime())->format(DateTime::ATOM);
        $this->entity = $entity;
    }

    /**
     * Returns an array with the properties and their values before they got changed.
     *
     * @return array|null
     */
    public function getOriginal(): ?array
    {
        return $this->original;
    }

    /**
     * Returns an array with the properties and their values as they were changed.
     *
     * @return array|null
     */
    public function getChanged(): ?array
    {
        return $this->changed;
    }

    /**
     * Returns the name of this event type.
     *
     * @return string
     */
    abstract public function getEventType(): string;

    /**
     * Returns the array to be used for encoding this object as json.
     *
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize(): mixed
    {
        return $this->basicSerialize() + [
            'original' => $this->original,
            'changed' => $this->changed,
        ];
    }
}
