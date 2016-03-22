<?php

namespace AuditStash\Model\Type;

use Cake\ElasticSearch\Type;
use Elastica\Aggregation\Terms as TermsAggregation;

/**
 * Represents the rpository containing all the audit logs events
 * of any kind and source.
 */
class AuditLogsType extends Type
{
    /**
     * The default connection name to inject when creating an instance.
     *
     * @return string
     */
    public static function defaultConnectionName()
    {
        return 'auditlog_elastic';
    }

    /**
     * Sets the type name to use for querying.
     *
     * @param string $name The name of the type(s) to query
     * @return string
     */
    public function name($name = null)
    {
        if ($name === 'audit_logs') {
            return $this->_name = '';
        }
        return parent::name($name);
    }

    /**
     * Returns a query setup for getting the 'type' aggregation.
     *
     * @param Cake\ElasticSearch\Query $query The Query Object
     * @return Cake\ElasticSearch\Query
     */
    public function findTypes($query)
    {
        $facet = new TermsAggregation('type');
        $facet->setField('_type');
        $facet->setSize(200);
        $query->aggregate($facet);
        return $query->limit(1);
    }
}
