<?php
namespace AuditStash\Action;

use Cake\ElasticSearch\TypeRegistry;
use Crud\Action\IndexAction;

/**
 * A CRUD action class to implement the listing of all audit logs
 * documents in elastic search
 */
class ElasticLogsIndexAction extends IndexAction
{

    use IndexConfigTrait;

    /**
     * Renders the index action by searching all documents matching the URL conditions
     *
     * @return void
     */
    protected function _handle()
    {
        $request = $this->_request();
        $this->_configIndex($this->_table(), $request);
        $query = $this->_table()->find();
        $repository = $query->repository();

        $query->searchOptions(['ignore_unavailable' => true]);

        if ($request->query('type')) {
            $repository->name($request->query('type'));
        }

        if ($request->query('primary_key')) {
            $query->where(['primary_key' => $request->query('primary_key')]);
        }

        if ($request->query('transaction')) {
            $query->where(['transaction' => $request->query('transaction')]);
        }

        if ($request->query('user')) {
            $query->where(['meta.user' => $request->query('user')]);
        }

        if ($request->query('changed_fields')) {
            $query->where(function ($builder) use ($request) {
                $fields = explode(',', $request->query('changed_fields'));
                $fields = array_map(function ($f) { return 'changed.' . $f; }, array_map('trim', $fields));
                $fields = array_map([$builder, 'exists'], $fields);
                return $builder->and_($fields);
            });
        }

        if ($request->query('query')) {
            $query->where(function ($builder) use ($request) {
                return $builder->query(new \Elastica\Query\QueryString($request->query('query')));
            });
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
    }

    /**
     * Returns the Repository object to use
     *
     * @return AuditStash\Model\Type\AuditLogsType;
     */
    protected function _table()
    {
        return $this->_controller()->AuditLogs = TypeRegistry::get('AuditStash.AuditLogs');
    }

    /**
     * Alters the query object to add the time constraints as they can be found in
     * the request object
     *
     * @param Cake\Network\Request $request The request where query string params can be found
     * @param Cake\ElasticSearch\Query $query The Query to add filters to
     * @return void
     */
    protected function addTimeConstraints($request, $query)
    {
        if ($request->query('from')) {
            $from = new \DateTime($request->query('from'));
            $until = new \DateTime();
        }

        if ($request->query('until')) {
            $until = new \DateTime($request->query('until'));
        }

        if (!empty($from)) {
            $query->where(function ($builder) use ($from, $until) {
                return $builder->between('@timestamp', $from->format('Y-m-d H:i:s'), $until->format('Y-m-d H:i:s'));
            });
            return;
        }

        if (!empty($until)) {
            $query->where(['@timestamp <=' => $until->format('Y-m-d H:i:s')]);
        }
    }
}
