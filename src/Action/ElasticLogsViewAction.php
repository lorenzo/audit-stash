<?php
namespace AuditStash\Action;

use Cake\ElasticSearch\IndexRegistry;
use Cake\Event\Event;
use Crud\Action\ViewAction;
use Crud\Event\Subject;

/**
 * A CRUD action class to implement the view of all details of a single audit log event
 * from elastic search.
 */
class ElasticLogsViewAction extends ViewAction
{

    use IndexConfigTrait;

    /**
     * Returns the Repository object to use.
     *
     * @return AuditStash\Model\Index\AuditLogsIndex;
     */
    protected function _table()
    {
        return $this->_controller()->AuditLogs = IndexRegistry::get('AuditStash.AuditLogs');
    }

    /**
     * Find a audit log by id.
     *
     * @param string $id Record id
     * @param \Crud\Event\Subject $subject Event subject
     * @return \AuditStash\Model\Document\AuditLog
     */
    protected function _findRecord($id, Subject $subject)
    {
        $repository = $this->_table();
        $this->_configIndex($repository, $this->_request());

        if ($this->_request()->query('type')) {
            $repository->name($this->_request()->query('type'));
        }

        $query = $repository->find($this->findMethod());
        $query->where(['_id' => $id]);
        $subject->set([
            'repository' => $repository,
            'query' => $query
        ]);
        $this->_trigger('beforeFind', $subject);
        $entity = $query->first();
        if (!$entity) {
            return $this->_notFound($id, $subject);
        }
        $subject->set(['entity' => $entity, 'success' => true]);
        $this->_trigger('afterFind', $subject);

        return $entity;
    }
}
