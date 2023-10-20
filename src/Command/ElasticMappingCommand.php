<?php
declare(strict_types=1);

namespace AuditStash\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use Elastica\Mapping;
use Elastica\Request;

/**
 * Exposes a shell command to create the required Elastic Search mappings. Creates the elastic search mapping
 * for the provided table, or just prints it out to the screen if the `dry-run` option is provided. *
 */
class ElasticMappingCommand extends Command
{
    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription(
                'Creates type mappings in elastic search for the tables you want tracked with audit logging'
            )
            ->addArgument('table', [
                'short' => 't',
                'help' => 'The name of the database table to inspect and create a mapping for',
                'required' => true,
            ])
            ->addOption('use-templates', [
                'short' => 'u',
                'help' => 'Creates mapping templates instead of creating the mapping directly',
                'boolean' => true,
            ])
            ->addOption('dry-run', [
                'short' => 'd',
                'help' => 'Do not create the mapping, just output it to the screen',
                'boolean' => true,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $table = $this->fetchTable($args->getArgument('table'));
        $schema = $table->getSchema();
        $mapping = [
            '@timestamp' => [
                'type' => 'date',
                'format' => 'basic_t_time_no_millis||dateOptionalTime||basic_date_time||' .
                    'ordinal_date_time_no_millis||yyyy-MM-dd HH:mm:ss',
            ],
            'transaction' => [
                'type' => 'text',
                'index' => false,
            ],
            'type' => [
                'type' => 'text',
                'index' => false,
            ],
            'primary_key' => [
                'type' => 'text',
                'index' => false,
            ],
            'source' => [
                'type' => 'text',
                'index' => false,
            ],
            'parent_source' => [
                'type' => 'text',
                'index' => false,
            ],
            'original' => [
                'properties' => [],
            ],
            'changed' => [
                'properties' => [],
            ],
            'meta' => [
                'properties' => [
                    'ip' => [
                        'type' => 'text',
                        'index' => false,
                    ],
                    'url' => [
                        'type' => 'text',
                        'index' => false,
                    ],
                    'user' => [
                        'type' => 'text',
                        'index' => false,
                    ],
                    'app_name' => [
                        'type' => 'text',
                        'index' => false,
                    ],
                ],
            ],
        ];

        $properties = [];
        foreach ($schema->columns() as $column) {
            $properties[$column] = $this->mapType($schema, $column);
        }

        $indexName = $table->getTable();
        $typeName = Inflector::singularize(str_replace('%s', '', $indexName));

        if ($table->hasBehavior('AuditLog')) {
            $whitelist = (array)$table->behaviors()->AuditLog->config('whitelist');
            $blacklist = (array)$table->behaviors()->AuditLog->config('blacklist');
            $properties = empty($whitelist) ? $properties : array_intersect_key($properties, array_flip($whitelist));
            $properties = array_diff_key($properties, array_flip($blacklist));
            $indexName = $table->behaviors()->AuditLog->config('index') ?: $indexName;
            $typeName = $table->behaviors()->AuditLog->config('type') ?: $typeName;
        }

        $mapping['original']['properties'] = $mapping['changed']['properties'] = $properties;
        /** @var \Cake\ElasticSearch\Datasource\Connection $client */
        $client = ConnectionManager::get('auditlog_elastic');
        $index = $client->getIndex(sprintf($indexName, '-' . gmdate('Y.m.d')));
        $type = $index->getName();
        $elasticMapping = new Mapping();
        $elasticMapping->setParam('_type', $type);
        $elasticMapping->setProperties($mapping);

        if ($args->getOption('dry-run')) {
            $io->out(json_encode($elasticMapping->toArray(), JSON_PRETTY_PRINT));

            return static::CODE_SUCCESS;
        }

        if ($args->getOption('use-templates')) {
            $template = [
                'template' => sprintf($indexName, '*'),
                'mappings' => $elasticMapping->toArray(),
            ];
            $response = $client->request('_template/template_' . $type, Request::PUT, $template);
            $io->out('Successfully created the mapping template');

            return $response->isOk();
        }

        if (!$index->exists()) {
            $index->create();
        }

        $elasticMapping->send($index);
        $io->success('Successfully created the mapping');

        return static::CODE_SUCCESS;
    }

    /**
     * Returns the correct mapping properties for a table column.
     *
     * @param \Cake\Database\Schema\TableSchema $schema The table schema
     * @param string $column The column name to introspect
     * @return array
     */
    protected function mapType(TableSchema $schema, string $column): array
    {
        $baseType = $schema->baseColumnType($column);

        return match ($baseType) {
            'uuid' => [
                'type' => 'text',
                'index' => false,
                'null_value' => '_null_',
            ],
            'integer' => [
                'type' => 'integer',
                'null_value' => pow(-2, 31),
            ],
            'date' => [
                'type' => 'date',
                'format' => 'dateOptionalTime||basic_date||yyy-MM-dd',
                'null_value' => '0001-01-01',
            ],
            'datetime',
            'timestamp' => [
                'type' => 'date',
                'format' => 'basic_t_time_no_millis||dateOptionalTime||basic_date_time||' .
                    'ordinal_date_time_no_millis||yyyy-MM-dd HH:mm:ss||basic_date',
                'null_value' => '0001-01-01 00:00:00',
            ],
            'float',
            'decimal' => [
                'type' => 'float',
                'null_value' => pow(-2, 31),
            ],
            'boolean' => [
                'type' => 'boolean',
            ],
            default => [
                'type' => 'text',
                'fields' => [
                    $column => [
                        'type' => 'text',
                    ],
                    'raw' => [
                        'type' => 'text',
                        'index' => false,
                    ],
                ],
            ],
        };
    }
}
