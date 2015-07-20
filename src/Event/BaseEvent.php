<?php

namespace AuditStash\Event;

use AuditStash\EventInterface;
use DateTime;

abstract class BaseEvent implements EventInterface
{
    protected $transactionId;

    protected $timestamp;

    protected $id;

    protected $source;

    protected $parentSource;

    protected $changed;

    protected $original;

    public function __construct($transactionId, $id, $source, $changed, $original)
    {
        $this->transactionId = $transactionId;
        $this->id = $id;
        $this->source = $source;
        $this->changed = $changed;
        $this->original = $original;
        $this->timestamp = Datetime::createFromFormat('U.u', microtime(true))->format('Y-m-d\TH:i:s.u\Z');
    }

    public function getTransactionId()
    {
        return $this->transactionId;
    }

    public function getTimestamp()
    {
        return $this->timestamp;
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

    public function getParentSourceName()
    {
        return $this->parentSource;
    }

    public function setParentSourceName($name) {
        $this->parentSource = $name;
    }

    abstract public function getEventType();
}
