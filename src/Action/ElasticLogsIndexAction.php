<?php
declare(strict_types=1);

namespace AuditStash\Action;

use Cake\Database\Expression\QueryExpression;
use Cake\ElasticSearch\Index;
use Cake\ElasticSearch\Query;
use Cake\ElasticSearch\QueryBuilder;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crud\Action\IndexAction;
use DateTime;
use Elastica\Query\BoolQuery;
use Elastica\Query\QueryString;

/**
 * A CRUD action class to implement the listing of all audit logs
 * documents in elastic search.
 */
class ElasticLogsIndexAction extends IndexAction
{
    use IndexConfigTrait;

    /**
     * Renders the index action by searching all documents matching the URL conditions.
     *
     * @return \Cake\Http\Response|null
     * @throws \Exception
     */
    protected function _handle(): ?Response
    {
        $request = $this->_request();
        $this->configIndex($this->_table(), $request);
        $query = $this->_table()->find();
        $repository = $query->getRepository();

        $query->searchOptions(['ignore_unavailable' => true]);

        if ($request->getQuery('type')) {
            $repository->setName($request->getQuery('type'));
        }

        if ($request->getQuery('primary_key')) {
            $query->where(['primary_key' => $request->getQuery('primary_key')]);
        }

        if ($request->getQuery('transaction')) {
            $query->where(['transaction' => $request->getQuery('transaction')]);
        }

        if ($request->getQuery('user')) {
            $query->where(['meta.user' => $request->getQuery('user')]);
        }

        if ($request->getQuery('changed_fields')) {
            $query->where(function (QueryBuilder $builder) use ($request): BoolQuery {
                $fields = explode(',', $request->getQuery('changed_fields'));
                $fields = array_map(fn($f): string => 'changed.' . $f, array_map('trim', $fields));
                $fields = array_map([$builder, 'exists'], $fields);

                return $builder->and($fields);
            });
        }

        if ($request->getQuery('query')) {
            $query->where(fn(QueryBuilder $builder): BoolQuery => $builder
                ->and(new QueryString($request->getQuery('query'))));
        }

        try {
            $this->addTimeConstraints($request, $query);
        } catch (\Exception $e) {
        }

        $subject = $this->_subject(['success' => true, 'query' => $query]);
        $this->_trigger('beforePaginate', $subject);

        $items = $this->_controller()->paginate($subject->query);
        $subject->set(['entities' => $items]);

        $this->_trigger('afterPaginate', $subject);
        $this->_trigger('beforeRender', $subject);

        return null;
    }

    /**
     * Returns the Repository object to use.
     */
    protected function _table(): Index
    {
        return $this->_controller()->AuditLogs = $this->getIndexRepository();
    }

    /**
     * Alters the query object to add the time constraints as they can be found in
     * the request object.
     *
     * @param \Cake\Http\ServerRequest $request The request where query string params can be found
     * @param \Cake\ElasticSearch\Query $query The Query to add filters to
     * @return void
     * @throws \Exception
     */
    protected function addTimeConstraints(ServerRequest $request, Query $query): void
    {
        if ($request->getQuery('from')) {
            $from = new DateTime($request->getQuery('from'));
            $until = new DateTime();
        }

        if ($request->getQuery('until')) {
            $until = new DateTime($request->getQuery('until'));
        }

        if (!empty($from) && !empty($until)) {
            $query->where(fn(QueryExpression $builder): QueryExpression => $builder
                ->between(
                    '@timestamp',
                    $from->format('Y-m-d H:i:s'),
                    $until->format('Y-m-d H:i:s')
                )
            );

            return;
        }

        if (!empty($until)) {
            $query->where(['@timestamp <=' => $until->format('Y-m-d H:i:s')]);
        }
    }
}
