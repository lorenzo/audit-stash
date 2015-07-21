<?php

namespace AuditStash\Shell;

use Cake\Console\Shell;
use Cake\ORM\TableRegistry;
use Cake\Datasource\ConnectionManager;
use Elastica\Type\Mapping as ElasticaMapping;

/**
 * Exposes a shell command to create the required Elastic Search mappings
 */
class ElasticMappingShell extends Shell
{

    /**
     * {@inheritDoc}
     *
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
            ->addOption('dry-run', [
                'short' => 'd',
                'help' => 'Do not create the mapping, just output it to the screen',
                'boolean' => true
            ]);
    }

    /**
     * Creates the elastic search mapping for the provided table, or just prints it out
     * to the screen if the `dry-run` option is provided
     *
     * @param string $table The table name to inspect and create a mapping for
     * @return boolean
     */
    public function main($table)
    {
        $table = TableRegistry::get($table);
        $schema = $table->schema();
        $mapping = [
            '@timestamp' => ['type' => 'date'],
            'transaction' => ['type' => 'string', 'index' => 'not_analyzed'],
            'type' => ['type' => 'string', 'index' => 'not_analyzed'],
            'primary_key' => ['type' => 'integer'],
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
                    'ip' => ['type' => 'ip'],
                    'url' => ['type' => 'string', 'index' => 'not_analyzed'],
                    'user' => ['type' => 'string', 'index' => 'not_analyzed']
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
        $index = ConnectionManager::get('auditlog_elastic')->getIndex();
        $type = $index->getType($table->table());
        $elasticMapping = new ElasticaMapping();
        $elasticMapping->setType($type);
        $elasticMapping->setProperties($mapping);

        if ($this->params['dry-run']) {
            $this->out(json_encode($elasticMapping->toArray(), JSON_PRETTY_PRINT));
            return true;
        }

        if (!$index->exists()) {
            $index->create();
        }

        $elasticMapping->send();
        $this->out('<success>Successfully created the mapping</success>');
        return true;
    }

    /**
     * Returns the correct mapping properties for a table column
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
            return ['type' => 'string', 'index' => 'not_analyzed'];
        case 'integer':
            return ['type' => 'integer'];
        case 'date':
        case 'datetime':
        case 'timestamp':
            return ['type' => 'date'];
        case 'float':
        case 'decimal':
            return ['type' => 'float'];
        case 'float':
        case 'decimal':
            return ['type' => 'float'];
        case 'boolean':
            return ['type' => 'boolean'];
        default:
            return [
                'type' => 'multi_field',
                'fields' => [
                    $column => ['type' => 'string'],
                    'raw' => ['type' => 'string', 'index' => 'not_analyzed']
                ]
            ];
        }
    }
}
