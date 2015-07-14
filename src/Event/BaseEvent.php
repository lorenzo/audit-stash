<?php

namespace AuditStash\Event;

use AuditStash\EventInterface;

abstract class BaseEvent implements EventInterface
{
    protected $id;

    protected $source;

    protected $changed;

    protected $original;

    public function __construct($id, $source, $changed, $original)
    {
        $this->id = $id;
        $this->source = $source;
        $this->changed = $changed;
        $this->original = $original;
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
