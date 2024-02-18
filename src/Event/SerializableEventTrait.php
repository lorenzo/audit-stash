<?php
declare(strict_types=1);

namespace AuditStash\Event;

/**
 * Exposes basic functions for serializing event classes.
 */
trait SerializableEventTrait
{
    /**
     * Returns the string representation of this object.
     *
     * @return string
     */
    public function serialize(): string
    {
        return serialize(
            $this->__serialize()
        );
    }

    /**
     * Takes the string representation of this object so it can be reconstructed.
     *
     * @param string $data serialized string
     * @return void
     */
    public function unserialize(string $data): void
    {
        $this->__unserialize(
            unserialize($data)
        );
    }

    /**
     * Returns the string representation of this object.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return get_object_vars($this);
    }

    /**
     * Takes the string representation of this object so it can be reconstructed.
     *
     * @param array $data serialized string
     * @return void
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $var => $value) {
            $this->{$var} = $value;
        }
    }

    /**
     * Returns an array with the basic variables that should be json serialized.
     *
     * @return array
     */
    protected function basicSerialize(): array
    {
        return [
            'type' => $this->getEventType(),
            'transaction' => $this->transactionId,
            'primary_key' => $this->id,
            'source' => $this->source,
            'parent_source' => $this->parentSource,
            '@timestamp' => $this->timestamp,
            'meta' => $this->meta,
            'entity' => $this->entity,
        ];
    }
}
