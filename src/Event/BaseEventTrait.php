<?php

namespace AuditStash\Event;

/**
 * Implements most of the methods of the EventInterface.
 */
trait BaseEventTrait
{
    /**
     * Global transaction id.
     *
     * @var string
     */
    protected string $transactionId;

    /**
     * Entity primary key.
     *
     * @var mixed
     */
    protected $id;

    /**
     * Repository name.
     *
     * @var string
     */
    protected string $source;

    /**
     * Parent repository name.
     *
     * @var string
     */
    protected string $parentSource;

    /**
     * Time of event.
     *
     * @var string
     */
    protected string $timestamp;

    /**
     * Extra information to describe the event.
     *
     * @var array
     */
    protected array $meta = [];

    /**
     * @var string
     */
    protected string $displayValue;

    /**
     * Returns the global transaction id in which this event is contained.
     *
     * @return string
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * Returns the id of the entity that was created or altered.
     *
     * @return mixed
     */
    public function getId()
    {
        if (is_array($this->id) && count($this->id) === 1) {
            return current($this->id);
        }
        return $this->id;
    }

    /**
     * Returns the repository name in which the entity is.
     *
     * @return string
     */
    public function getSourceName(): string
    {
        return $this->source;
    }

    /**
     * Returns the repository name that triggered this event.
     *
     * @return string
     */
    public function getParentSourceName(): string
    {
        return $this->parentSource;
    }

    /**
     * Sets the name of the repository that triggered this event.
     *
     * @param string $source The repository name
     * @return void
     */
    public function setParentSourceName($source)
    {
        $this->parentSource = $source;
    }

    /**
     * Returns the time string in which this change happened.
     *
     * @return string
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Returns an array with meta information that can describe this event.
     *
     * @return array
     */
    public function getMetaInfo(): array
    {
        return $this->meta;
    }

    /**
     * Sets the meta information that can describe this event.
     *
     * @param array $meta The meta information to attach to the event
     * @return void
     */
    public function setMetaInfo(array $meta)
    {
        $this->meta = $meta;
    }

    /**
     * @return string
     */
    public function getDisplayValue(): string
    {
        return $this->displayValue;
    }
}
