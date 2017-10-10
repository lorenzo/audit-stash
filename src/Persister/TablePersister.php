<?php

namespace AuditStash\Persister;

use AuditStash\PersisterInterface;
use Cake\Core\InstanceConfigTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;

/**
 * A persister that uses the ORM API to persist audit logs.
 */
class TablePersister implements PersisterInterface
{
    use ExtractionTrait;
    use InstanceConfigTrait;
    use LocatorAwareTrait;
    use LogTrait;

    /**
     * Strategy that will choose between raw and serialized.
     *
     * @var string
     */
    const STRATEGY_AUTOMATIC = 'automatic';

    /**
     * Strategy that extracts data as separate fields/properties.
     *
     * @var string
     */
    const STRATEGY_PROPERTIES = 'properties';

    /**
     * Strategy that extracts data as is.
     *
     * @var string
     */
    const STRATEGY_RAW = 'raw';

    /**
     * Strategy that extracts data serialized in JSON format.
     *
     * @var string
     */
    const STRATEGY_SERIALIZED = 'serialized';

    /**
     * The default configuration.
     *
     * ## Configuration options
     *
     * - `extractMetaFields` (`bool|array`, defaults to `false`)
     *
     *   Defines whether/which meta data fields to extract as entity properties, thus
     *   making them savable in the target table. When set to `true`, all meta data fields
     *   being extracted, where the keys are being used as the property/column names.
     *
     *   When passing an array, either a `key => value` map, or single string values are
     *   expected, where the key will be used as the property/column named, and the value
     *   should be a `Hash::get()` compatible path that is used to extract from the meta
     *   data. When passing single values, the value will be used as the property/name
     *   as well as the path for extracting.
     *
     * - `logErrors` (`bool`, defaults to `true`)
     *
     *   Defines whether to log errors. By default, errors are logged using the log level
     *   `LogLevel::ERROR`.
     *
     * - `primaryKeyExtractionStrategy` (`string`, defaults to `TablePersister::STRATEGY_AUTOMATIC`)
     *
     *   Defines how the primary key values of the changed records should be stored. Valid
     *   values are the persister class' `STORAGE_STRATEGY_*` constants:
     *
     *     * `STRATEGY_AUTOMATIC`: Stores the primary key in a single column, either as is,
     *        or in case of a composite key, in JSON format.
     *
     *     * `STRATEGY_PROPERTIES`: Stores the keys in individual columns if required.
     *       Composite primary keys will be stored prefixed with `primary_key_` followed by the
     *       index of the value in the primary key array.
     *
     *     * `STRATEGY_RAW`: Stores the key as is in a single column.
     *
     *     * `STRATEGY_SERIALIZED`: Stores the primary key in JSON format.
     *
     * - `serializeFields` (`bool`, defaults to `true`)
     *
     *   Defines whether the (non-primary key) fields that expect array data are being
     *   serialized in JSON format.
     *
     * - `table` (`string|\Cake\ORM\Table`, defaults to `AuditLogs`)
     *
     *   Defines the table to use for persisting records. Either a string denoting a table
     *   alias that is going to be resolved using the persisters table locator, or a table
     *   object.
     *
     * - `unsetExtractedMetaFields` (`bool`, defaults to `true`)
     *
     *   Defines whether the fields extracted from the meta data should be unset, ie removed.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'extractMetaFields' => false,
        'logErrors' => true,
        'primaryKeyExtractionStrategy' => self::STRATEGY_AUTOMATIC,
        'serializeFields' => true,
        'table' => 'AuditLogs',
        'unsetExtractedMetaFields' => true,
    ];

    /**
     * The table to use for persisting logs.
     *
     * @var \Cake\ORM\Table
     */
    protected $_table;

    /**
     * Returns the table to use for persisting logs.
     *
     * @return \Cake\ORM\Table
     */
    public function getTable()
    {
        if ($this->_table === null) {
            $this->setTable($this->config('table'));
        }

        return $this->_table;
    }

    /**
     * Sets the table to use for persisting logs.
     *
     * @param string|\Cake\ORM\Table $table Either a string denoting a table alias, or a table object.
     * @return $this
     */
    public function setTable($table)
    {
        if (is_string($table)) {
            $table = $this->tableLocator()->get($table);
        }

        if (!($table instanceof Table)) {
            throw new \InvalidArgumentException(
                'The `$table` argument must be either a table alias, or an instance of `\Cake\ORM\Table`.'
            );
        }

        $this->_table = $table;

        return $this;
    }

    /**
     * Persists each of the passed EventInterface objects.
     *
     * @param \AuditStash\EventInterface[] $auditLogs List of EventInterface objects to persist
     * @return void
     */
    public function logEvents(array $auditLogs)
    {
        $PersisterTable = $this->getTable();

        $serializeFields = $this->config('serializeFields');
        $primaryKeyExtractionStrategy = $this->config('primaryKeyExtractionStrategy');
        $extractMetaFields = $this->config('extractMetaFields');
        $unsetExtractedMetaFields = $this->config('unsetExtractedMetaFields');
        $logErrors = $this->config('logErrors');

        foreach ($auditLogs as $log) {
            $fields = $this->extractBasicFields($log, $serializeFields);
            $fields += $this->extractPrimaryKeyFields($log, $primaryKeyExtractionStrategy);
            $fields += $this->extractMetaFields(
                $log, $extractMetaFields, $unsetExtractedMetaFields, $serializeFields
            );

            $persisterEntity = $PersisterTable->newEntity($fields);

            if (!$PersisterTable->save($persisterEntity) &&
                $logErrors
            ) {
                $this->log($this->toErrorLog($persisterEntity));
            }
        }
    }
}
