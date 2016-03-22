<?php
namespace AuditStash;

use JsonSerializable;
use Serializable;

/**
 * Represents an event in a particular entity in a repository.
 */
interface EventInterface extends JsonSerializable, Serializable
{
    /**
     * Returns the name of this event type.
     *
     * @return string
     */
    public function getEventType();

    /**
     * Returns the global transaction id in which this event is contained.
     *
     * @return string
     */
    public function getTransactionId();

    /**
     * Returns the id of the entity that was created or altered.
     *
     * @return mixed
     */
    public function getId();

    /**
     * Returns the repository name in which the entity is.
     *
     * @return string
     */
    public function getSourceName();

    /**
     * Returns the time string in which this change happened.
     *
     * @return string
     */
    public function getTimestamp();

    /**
     * Returns an array with meta information that can describe this event.
     *
     * @return array
     */
    public function getMetaInfo();

    /**
     * Sets the meta information that can describe this event.
     *
     * @param array $meta The meta information to attach to the event
     * @return void
     */
    public function setMetaInfo($meta);
}
