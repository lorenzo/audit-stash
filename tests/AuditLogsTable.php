<?php
declare(strict_types=1);

namespace AuditStash\Test;

use Cake\ORM\Table;

class AuditLogsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('audit_logs');
        $this->setPrimaryKey('id');

        $this->setSchema([
            'id' => 'integer',
            'transaction' => 'string',
            'type' => 'string',
            'primary_key' => 'integer',
            'source' => 'string',
            'parent_source' => 'string',
            'original' => 'string',
            'changed' => 'string',
            'meta' => 'string',
            'created' => 'datetime',
        ]);
    }
}
