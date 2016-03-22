<?php
$findRoot = function ($root) {
    do {
        $lastRoot = $root;
        $root = dirname($root);
        if (is_dir($root . '/vendor/cakephp/cakephp')) {
            return $root;
        }
    } while ($root !== $lastRoot);
    throw new Exception('Cannot find the root of the application, unable to run tests');
};

$root = $findRoot(__FILE__);
unset($findRoot);
chdir($root);

if (!getenv('elastic_dsn')) {
    putenv('elastic_dsn=Cake\ElasticSearch\Datasource\Connection://127.0.0.1:9200?index=audits_test&driver=Cake\ElasticSearch\Datasource\Connection');
}

require $root . '/vendor/cakephp/cakephp/tests/bootstrap.php';

use Cake\Datasource\ConnectionManager;

ConnectionManager::config('test_elastic', ['url' => getenv('elastic_dsn')]);
