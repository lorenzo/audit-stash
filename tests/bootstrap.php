<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

define('CAKE', dirname(__DIR__) . '/vendor/cakephp/cakephp/src/');

define('ROOT', dirname(__DIR__));
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
define('APP', __DIR__);
define('TMP', sys_get_temp_dir() . DS);
define('LOGS', TMP . 'logs' . DS);

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\FactoryLocator;
use Cake\ElasticSearch\IndexRegistry;
use Cake\ElasticSearch\TestSuite\Fixture\MappingGenerator;
use Cake\Routing\Router;
use Cake\TestSuite\Fixture\SchemaLoader;
use function Cake\Core\env;

Configure::write('debug', true);

Cache::setConfig('_cake_core_', [
    'className' => 'File',
    'path' => sys_get_temp_dir(),
]);

if (!getenv('elastic_dsn')) {
    putenv('elastic_dsn=Cake\ElasticSearch\Datasource\Connection://127.0.0.1:9200?driver=Cake\ElasticSearch\Datasource\Connection');
}

ConnectionManager::setConfig('test_elastic', ['url' => getenv('elastic_dsn')]);

/*
 * Only load fixtures if there is an active elastic service
 */
if (env('FIXTURE_MAPPINGS_METADATA') && file_exists(getenv('elastic_dsn'))) {
    throw new Exception('does pipeline get here?');
    $schema = new MappingGenerator(env('FIXTURE_MAPPINGS_METADATA'), 'test_elastic');
    $schema->reload();
    Router::reload();

    $indexRegistry = new IndexRegistry();
    FactoryLocator::add('Elastic', $indexRegistry);
    FactoryLocator::add('ElasticSearch', $indexRegistry);
}

if (!getenv('db_dsn')) {
    putenv('db_dsn=sqlite:///:memory:');
}

ConnectionManager::setConfig('test', ['url' => getenv('db_dsn')]);
if (env('FIXTURE_SCHEMA_METADATA')) {
    $loader = new SchemaLoader();
    $loader->loadInternalFile(env('FIXTURE_SCHEMA_METADATA'));
}
