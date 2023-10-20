<?php
declare(strict_types=1);

namespace AuditStash\Action;

use AuditStash\Model\Document\AuditLog;
use Cake\ElasticSearch\Index;
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
     * @return \Cake\ElasticSearch\Index;
     */
    protected function _table(): Index
    {
        return $this->_controller()->AuditLogs = $this->getIndexRepository();
    }

    /**
     * Find a audit log by id.
     *
     * @param string $id Record id
     * @param \Crud\Event\Subject $subject Event subject
     * @return \AuditStash\Model\Document\AuditLog
     * @throws \Exception
     */
    protected function _findRecord(string $id, Subject $subject): AuditLog
    {
        $repository = $this->_table();
        $this->configIndex($repository, $this->_request());

        if ($this->_request()->getQuery('type')) {
            $repository->setName($this->_request()->getQuery('type'));
        }

        $query = $repository->find($this->findMethod());
        $query->where(['_id' => $id]);
        $subject->set([
            'repository' => $repository,
            'query' => $query,
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
