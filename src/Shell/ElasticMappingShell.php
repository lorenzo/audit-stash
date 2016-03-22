<?php

namespace AuditStash\Shell;

use Cake\Console\Shell;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Elastica\Request;
use Elastica\Type\Mapping as ElasticaMapping;

/**
 * Exposes a shell command to create the required Elastic Search mappings.
 */
class ElasticMappingShell extends Shell
{
    /**
     * {@inheritdoc}
     */
    public function getOptionParser()
    {
        return parent::getOptionParser()
            ->description('Creates type mappings in elastic search for the tables you want tracked with audit logging')
            ->addArgument('table', [
                'short' => 't',
                'help' => 'The name of the database table to inspect and create a mapping for',
                'required' => true
            ])
            ->addOption('use-templates', [
                'short' => 'u',
                'help' => 'Creates mapping templates instead of creating the mapping directly',
                'boolean' => true
            ])
            ->addOption('dry-run', [
                'short' => 'd',
                'help' => 'Do not create the mapping, just output it to the screen',
                'boolean' => true
            ]);
    }

    /**
     * Creates the elastic search mapping for the provided table, or just prints it out
     * to the screen if the `dry-run` option is provided.
     *
     * @param string $table The table name to inspect and create a mapping for
     * @return bool
     */
    public function main($table)
    {
        $table = TableRegistry::get($table);
        $schema = $table->schema();
        $mapping = [
            '@timestamp' => ['type' => 'date', 'format' => 'basic_t_time_no_millis||dateOptionalTime||basic_date_time||ordinal_date_time_no_millis||yyyy-MM-dd HH:mm:ss'],
            'transaction' => ['type' => 'string', 'index' => 'not_analyzed'],
            'type' => ['type' => 'string', 'index' => 'not_analyzed'],
            'primary_key' => ['type' => 'string', 'index' => 'not_analyzed'],
            'source' => ['type' => 'string', 'index' => 'not_analyzed'],
            'parent_source' => ['type' => 'string', 'index' => 'not_analyzed'],
            'original' => [
                'properties' => []
            ],
            'changed' => [
                'properties' => []
            ],
            'meta' => [
                'properties' => [
                    'ip' => ['type' => 'string', 'index' => 'not_analyzed'],
                    'url' => ['type' => 'string', 'index' => 'not_analyzed'],
                    'user' => ['type' => 'string', 'index' => 'not_analyzed'],
                    'app_name' => ['type' => 'string', 'index' => 'not_analyzed']
                ]
            ]
        ];

        $properties = [];
        foreach ($schema->columns() as $column) {
            $properties[$column] = $this->mapType($schema, $column);
        }

        if ($table->hasBehavior('AuditLog')) {
            $whitelist = (array)$table->behaviors()->AuditLog->config('whitelist');
            $blacklist = (array)$table->behaviors()->AuditLog->config('blacklist');
            $properties = empty($whitelist) ? $properties : array_intersect_key($properties, array_flip($whitelist));
            $properties = array_diff_key($properties, array_flip($blacklist));
        }

        $mapping['original']['properties'] = $mapping['changed']['properties'] = $properties;
        $client = ConnectionManager::get('auditlog_elastic');
        $index = $client->getIndex(sprintf($client->getConfig('index'), '-' . gmdate('Y.m.d')));
        $type = $index->getType($table->table());
        $elasticMapping = new ElasticaMapping();
        $elasticMapping->setType($type);
        $elasticMapping->setProperties($mapping);

        if ($this->params['dry-run']) {
            $this->out(json_encode($elasticMapping->toArray(), JSON_PRETTY_PRINT));
            return true;
        }

        if ($this->params['use-templates']) {
            $template = [
                'template' => sprintf($client->getConfig('index'), '*'),
                'mappings' => $elasticMapping->toArray()
            ];
            $response = $client->request('_template/template_' . $type->getName(), Request::PUT, $template);
            $this->out('<success>Successfully created the mapping template</success>');
            return $response->isOk();
        }

        if (!$index->exists()) {
            $index->create();
        }

        $elasticMapping->send();
        $this->out('<success>Successfully created the mapping</success>');
        return true;
    }

    /**
     * Returns the correct mapping properties for a table column.
     *
     * @param Cake\Databse\Schema\Table $schema The table schema
     * @param string $column The column name to instrospect
     * @return array
     */
    protected function mapType($schema, $column)
    {
        $baseType = $schema->baseColumnType($column);
        switch ($baseType) {
        case 'uuid':
            return ['type' => 'string', 'index' => 'not_analyzed', 'null_value' => '_null_'];
        case 'integer':
            return ['type' => 'integer', 'null_value' => ~PHP_INT_MAX];
        case 'date':
            return ['type' => 'date', 'format' => 'dateOptionalTime||basic_date||yyy-MM-dd', 'null_value' => '0001-01-01'];
        case 'datetime':
        case 'timestamp':
            return ['type' => 'date', 'format' => 'basic_t_time_no_millis||dateOptionalTime||basic_date_time||ordinal_date_time_no_millis||yyyy-MM-dd HH:mm:ss||basic_date', 'null_value' => '0001-01-01 00:00:00'];
        case 'float':
        case 'decimal':
            return ['type' => 'float', 'null_value' => ~PHP_INT_MAX];
        case 'float':
        case 'decimal':
            return ['type' => 'float', 'null_value' => ~PHP_INT_MAX];
        case 'boolean':
            return ['type' => 'boolean'];
        default:
            return [
                'type' => 'multi_field',
                'fields' => [
                    $column => ['type' => 'string', 'null_value' => '_null_'],
                    'raw' => ['type' => 'string', 'index' => 'not_analyzed', 'null_value' => '_null_', 'ignore_above' => 256]
                ]
            ];
        }
    }
}
