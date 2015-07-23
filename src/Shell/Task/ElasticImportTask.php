<?php

namespace AuditStash\Shell\Task;

use Cake\Console\Shell;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\Time;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Elastica\Document;

/**
 * Imports audit logs from the legacy audit logs tables into elastic search
 */
class ElasticImportTask extends Shell
{

    /**
     * {@inheritDoc}
     *
     */
    public function getOptionParser()
    {
        return parent::getOptionParser()
            ->description('Imports audit logs from the legacy audit logs tables into elastic search')
            ->addOption('from', [
                'short' => 'f',
                'help' => 'The date from which to start importing audit logs',
                'default' => 'now'
            ])
            ->addOption('until', [
                'short' => 'u',
                'help' => 'The date in which to stop importing audit logs',
                'default' => 'now'
            ])->addOption('models', [
                'short' => 'm',
                'help' => 'A comma separated list of model names to import'
            ])
            ->addOption('exclude-models', [
                'short' => 'e',
                'help' => 'A comma separated list of model names to skip importing'
            ])
            ->addOption('type-map', [
                'short' => 't',
                'help' => 'A comma separated list of model:type pairs (for example ParentCategory:categories)'
            ]);
    }

    /**
     * Copies for the audits and audits_logs table into the elastic search storage
     *
     * @return boolean
     */
    public function main()
    {
        $table = $this->loadModel('Audits');
        $table->hasMany('AuditDeltas');
        $table->schema()->columnType('created', 'string');
        $map = [];

        if (!empty($this->params['type-map'])) {
            $map = explode(',', $this->params['type-map']);
            $map = collection($map)->unfold(function ($element) {
                list($model, $type) = explode(':', $element);
                yield $model => $type;
            })
            ->toArray();
        }

        $from = (new Time($this->params['from']))->modify('midnight');
        $until = (new Time($this->params['until']))->modify('23:59:59');

        $currentId = null;
        $buffer = new \SplQueue;
        $buffer->setIteratorMode(\SplDoublyLinkedList::IT_MODE_DELETE);
        $queue = new \SplQueue;
        $queue->setIteratorMode(\SplDoublyLinkedList::IT_MODE_DELETE);

        $habtmFormatter = function ($value, $key) {
            if (!ctype_upper($key[0])) {
                return $value;
            }
            return array_map('intval', explode(',', $value));
        };

        $allBallsRemover = function ($value) {
            if (is_string($value) && strpos($value, '0000-00-00') === 0) {
                return null;
            }
            return $value;
        };

        $changesExtractor = function ($audit) use ($habtmFormatter, $allBallsRemover) {
            $changes = collection($audit)
                ->extract('_matchingData.AuditDeltas')
                ->indexBy('property_name')
                ->toArray();

            $audit = $audit[0];
            unset($audit['_matchingData']);

            $audit['original'] = collection($changes)
                ->map(function ($c) { return $c['old_value']; })
                ->map($habtmFormatter)
                ->map($allBallsRemover)
                ->toArray();
            $audit['changed'] = collection($changes)
                ->map(function ($c) { return $c['new_value']; })
                ->map($habtmFormatter)
                ->map($allBallsRemover)
                ->toArray();

            return $audit;
        };

        $index = ConnectionManager::get('auditlog_elastic')->getConfig('index');
        $eventsFormatter = function ($audit)  use ($index) {
            $data = [
                '@timestamp' => $audit['created'],
                'transaction' => $audit['id'],
                'type' => $audit['event'] === 'EDIT' ? 'update' : strtolower($audit['event']),
                'primary_key' => $audit['entity_id'],
                'original' => $audit['original'],
                'changed' => $audit['changed'],
                'meta' => [
                    'ip' => $audit['source_ip'],
                    'url' => $audit['source_url'],
                    'user' => $audit['source_id'],
                ]
            ];

            $index = sprintf($index, \DateTime::createFromFormat('Y-m-d H:i:s', $audit['created'])->format('-Y.m.d'));
            $type = isset($map[$audit['model']]) ? $map[$audit['model']] : Inflector::tableize($audit['model']);
            return new Document($audit['id'], $data, $type, $index);
        };

        $query = $table->find()
            ->where(function ($exp) use ($from, $until) {
                return $exp->between('Audits.created', $from, $until, 'datetime');
            })
            ->where(function ($exp) {
                if (!empty($this->params['exclude-models'])) {
                    $exp->notIn('Audits.model', explode(',', $this->params['exclude-models']));
                }

                if (!empty($this->params['models'])) {
                    $exp->in('Audits.model', explode(',', $this->params['models']));
                }
                return $exp;
            })
            ->matching('AuditDeltas')
            ->order(['Audits.created', 'AuditDeltas.audit_id'])
            ->bufferResults(false)
            ->hydrate(false)
            ->unfold(function ($audit) use ($buffer, &$currentId) {
                if ($currentId && $currentId !== $audit['id']) {
                    yield collection($buffer)->toList();
                }
                $currentId = $audit['id'];
                $buffer->enqueue($audit);
            })
            ->map($changesExtractor)
            ->map($eventsFormatter)
            ->unfold(function ($audit) use ($queue) {
                $queue->enqueue($audit);

                if ($queue->count() >= 50) {
                    yield collection($queue)->toList();
                }
            });

        $query->each([$this, 'persistBulk']);

        // There are probably some un-yielded results, let's flush them
        $rest = collection(count($buffer) ? [collection($buffer)->toList()] : [])
            ->map($changesExtractor)
            ->map($eventsFormatter)
            ->append($queue);

        $this->persistBulk($rest->toList());

        return true;
    }

    /**
     * Persists the array of passed documents into elastic search
     *
     * @param array $documents List of Elastica\Document objects to be persisted
     * @return void
     */
    public function persistBulk($documents)
    {
        if (empty($documents)) {
            $this->log('No more documents to index', 'info');
            return;
        }
        $this->log(sprintf('Indexing %d documents', count($documents)), 'info');
        ConnectionManager::get('auditlog_elastic')->addDocuments($documents);
    }
}
