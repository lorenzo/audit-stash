<?php

namespace AuditStash\Action;

use DateTime;

trait IndexConfigTrait
{
    /**
     * Configures the index to use in elastic search by completing the placeholders with the current date
     * if needed.
     *
     * @param Cake\ElasticSearch\Index $repository
     * @param Cake\Http\Request
     * @return void
     */
    protected function _configIndex($repository, $request)
    {
        $client = $repository->connection();
        $indexTemplate = $repository->getName();
        $client->setConfig(['index' => sprintf($indexTemplate, '*')]);

        if ($request->query('at')) {
            $client->setConfig(['index' => sprintf($indexTemplate, (new DateTime($request->query('at')))->format('-Y.m.d'))]);
        }
    }
}
