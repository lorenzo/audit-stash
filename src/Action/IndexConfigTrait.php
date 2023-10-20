<?php
declare(strict_types=1);

namespace AuditStash\Action;

use Cake\ElasticSearch\Datasource\IndexLocator;
use Cake\ElasticSearch\Index;
use Cake\Http\ServerRequest;
use DateTime;

trait IndexConfigTrait
{
    /**
     * Configures the index to use in elastic search by completing the placeholders with the current date
     * if needed.
     *
     * @param \Cake\ElasticSearch\Index $repository
     * @param \Cake\Http\ServerRequest $request
     * @return void
     * @throws \Exception
     */
    protected function configIndex(Index $repository, ServerRequest $request): void
    {
        /** @var \Elastica\Connection $client */
        $client = $repository->getConnection();
        $indexTemplate = $repository->getName();
        $client->setConfig(['index' => sprintf($indexTemplate, '*')]);

        if ($request->getQuery('at')) {
            $client->setConfig([
                'index' => sprintf(
                    $indexTemplate,
                    (new DateTime($request->getQuery('at')))
                        ->format('-Y.m.d')
                ),
            ]);
        }
    }

    /**
     * Get index repository
     *
     * @return \Cake\ElasticSearch\Index
     */
    protected function getIndexRepository(): Index
    {
        $indexLocator = new IndexLocator();
        $repository = $indexLocator->get('AuditStash.AuditLogs');
        assert($repository instanceof Index);

        return $repository;
    }
}
