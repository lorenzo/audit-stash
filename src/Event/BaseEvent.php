<?php

namespace AuditStash\Event;

use AuditStash\EventInterface;

abstract class BaseEvent implements EventInterface
{
    protected $transactionId;

    protected $id;

    protected $source;

    protected $changed;

    protected $original;

    public function __construct($transactionId, $id, $source, $changed, $original)
    {
        $this->transactionId = $transactionId;
        $this->id = $id;
        $this->source = $source;
        $this->changed = $changed;
        $this->original = $original;
    }

    public function getTransactionId()
    {
        return $this->transactionId;
    }

    public function getId()
    {
        if (is_array($this->id) && count($this->id) === 1) {
            return current($this->id);
        }
        return $this->id;
    }

    public function getSourceName()
    {
        return $this->source;
    }

    public function getOriginal()
    {
        return $this->original;
    }

    public function getChanged()
    {
        return $this->changed;
    }

    abstract public function getEventType();
}
