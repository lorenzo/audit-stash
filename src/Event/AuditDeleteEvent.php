<?php

namespace AuditStash\Event;

/**
 * Represents an audit log event for a newly deleted record.
 */
class AuditDeleteEvent extends BaseEvent
{

    /**
     * Construnctor.
     *
     * @param string $transationId The global transaction id
     * @param mixed $id The primary key record that got deleted
     * @param string $source The name of the source (table) where the record was deleted
     * @param string $parentSource The name of the source (table) that triggered this change
     */
    public function __construct($transactionId, $id, $source, $parentSource = null, $original = null)
    {
        if (is_array($parentSource) === true) {
            $parentSource = null;
            $original = $parentSource;
        }

        $this->parentSource = $parentSource;

        //debug(compact('transactionId', 'id', 'source', 'parentSource', 'original')); die();
        parent::__construct($transactionId, $id, $source, [], $original);
    }

    /**
     * Returns the name of this event type.
     *
     * @return string
     */
    public function getEventType()
    {
        return 'delete';
    }
}
