<?php

namespace AuditStash\Model\Document;

use Cake\ElasticSearch\Document;

/**
 * Represents an audit log event of any type
 *
 */
class AuditLog extends Document
{

    /**
     * Returns the type (source) for the audit log
     *
     * @return string
     */
    public function getType()
    {
        return $this->_result->getType();
    }
}
