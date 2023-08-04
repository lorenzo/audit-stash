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
        return serialize(get_object_vars($this));
    }

    /**
     *
     */
    public function __serialize(): array
    {
        return $this->serialize();
    }

    /**
     * Takes the string representation of this object so it can be reconstructed.
     *
     * @param string $data serialized string
     * @return void
     */
    public function unserialize(string $data): void
    {
        $vars = unserialize($data);
        foreach ($vars as $var => $value) {
            $this->{$var} = $value;
        }
    }

    /**
     * @inheritDoc
     */
    public function __unserialize(array $data): void
    {
        $this->unserialize($data);
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
            'meta' => $this->meta
        ];
    }
}
