<?php

namespace AuditStash\Event;

use AuditStash\EventInterface;

class AuditDeleteEvent implements EventInterface
{
    protected $transactionId;

    protected $id;

    protected $source;

    protected $parentSource;

    public function __construct($transactionId, $id, $source, $parentSource = null)
    {
        $this->transactionId = $transactionId;
        $this->id = $id;
        $this->source = $source;
        $this->parentSource = $parentSource;
    }

    public function getEventType()
    {
        return 'delete';
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

    public function getParentSourceName()
    {
        return $this->parentSource;
    }
}
