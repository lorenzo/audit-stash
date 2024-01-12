<?php

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
    public function serialize()
    {
        return serialize($this->__serialize());
    }

    /**
     * @return array<string,mixed>
     */
    public function __serialize()
    {
        return get_object_vars($this);
    }

    /**
     * Takes the string representation of this object so it can be reconstructed.
     *
     * @param string $data serialized string
     * @return void
     */
    public function unserialize($data)
    {
        $this->__unserialize(unserialize($data));
    }

    /**
     * Reconstructs the object from serialization data
     *
     * @param array<string,mixed> $data Serialization data
     * @return void
     */
    public function __unserialize($data)
    {
        foreach ($data as $var => $value) {
            $this->{$var} = $value;
        }
    }

    /**
     * Returns an array with the basic variables that should be json serialized.
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    protected function basicSerialize()
    {
        return [
            'type' => $this->getEventType(),
            'transaction' => $this->transactionId,
            'primary_key' => $this->id,
            'source' => $this->source,
            'parent_source' => $this->parentSource,
            '@timestamp' => $this->timestamp,
            'meta' => $this->meta,
            'entity' => $this->entity
        ];
    }
}
