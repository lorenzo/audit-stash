<?php
declare(strict_types=1);

namespace AuditStash\Model\Index;

use Cake\ElasticSearch\Index;
use Cake\ElasticSearch\Query;
use Elastica\Aggregation\Terms as TermsAggregation;

/**
 * Represents the repository containing all the audit logs events
 * of any kind and source.
 */
class AuditLogsIndex extends Index
{
    /**
     * The default connection name to inject when creating an instance.
     *
     * @return string
     */
    public static function defaultConnectionName(): string
    {
        return 'auditlog_elastic';
    }

    /**
     * Returns a query setup for getting the 'type' aggregation.
     *
     * @param \Cake\ElasticSearch\Query $query The Query Object
     * @return \Cake\ElasticSearch\Query
     */
    public function findTypes(Query $query): Query
    {
        $facet = new TermsAggregation('type');
        $facet->setField('_type');
        $facet->setSize(200);
        $query->aggregate($facet);

        return $query->limit(1);
    }
}
