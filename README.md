# AuditStash Plugin For CakePHP 4.x

This plugin is forked from [lorenzo/audit-stash](https://github.com/lorenzo/audit-stash)

The above plugin has the following issues or does not have the features I wanted.
- Original data is not recorded at the delete event.
    - This is useful if you add the plugin after some data was added.
  
- Associated table records were not saved properly (In this case I considered two models with ‘hasMany’ relationship)
    - Currently, in the CakePHP (4.x) ORM when there is a ‘hasMany’ relationship (for example; think about 2 DB tables: items and item_attributes), `EntityTrait::extractOriginal(array $fields)` doesn't return the original value of the associated table’s (item_attributes) original data instead they return the modified values.
    - Also, there is another bug in CakePHP (4.x) ORM, it marks the associated ('hasMany') entities as dirty, even if there are no changes made to the associated table data.
    - Unable to records only the changed data columns from the associated tables

- Unable to record audit logs when saveMany() is called to save multiple entities. 
- Unable to set some common configurations for the AuditLog behaviour and/or Table Persister via the app.php
- Doesn't record a human-friendly data field from foreign keys
- The Create event adds the same data into the 'original' and 'changed' columns
- The 'id' (primary key) filed is added to the 'original' and 'changed' data, unless you blacklist it in each model class. (The primary key is recorded as a separate field as well)


Therefore, I decided to fork from the original project and improve it to support the above missing features.

## Installation

You can install this plugin into your CakePHP application using [composer](https://getcomposer.org) and executing the
following lines in the root of your application.

```
composer require kdesilva/audit-trail
bin/cake plugin load AuditStash
```

If you plan to use ElasticSearch as the storage engine, please refer to [lorenzo/audit-stash](https://github.com/lorenzo/audit-stash)

## Configuration

### Tables / Regular Databases

If you want to use a regular database, respectively an engine that can be used via the CakePHP ORM API, then you can use
the table persister that ships with this plugin.

To do so you need to configure the `AuditStash.persister` option accordingly. In your `config/app.php` file add the
following configuration:

```php
'AuditStash' => [
    'persister' => 'AuditStash\Persister\TablePersister'
]
```

The plugin will then by default try to store the logs in a table named `audit_logs`, via a table class with the alias
`AuditLogs`, which you could create/overwrite in your application if you need.

You can find a migration in the `config/migration` folder of this plugin which you can apply to your database, this will
add a table named `audit_logs` with all the default columns. Alternatively you can bake your own migration to create the table. After that you
can migrate the corresponding table class.

If you use the plugin's default migration, you can create the table and model class using the commands below.

```
bin/cake migrations migrate -p AuditStash -t 20171018185609
bin/cake bake model AuditLogs
```

#### Table Persister Configuration

The table persister supports various configuration options, please refer to
[its API documentation](/src/Persister/TablePersister.php) for further information. Generally configuration can be
applied via its `config()` (or `setConfig()`) method:

```php
$this->addBehavior('AuditStash.AuditLog');
$this->behaviors()->get('AuditLog')->persister()->config([
    'extractMetaFields' => [
        'user.name' => 'username'
    ]
]);
```

Also, you can set some common config via the `app.php`. Currently, the plugin supports 'extractMetaFields' and 'blacklist'

```php
...
'AuditStash' => [
    'persister' => 'AuditStash\Persister\TablePersister',
    'extractMetaFields' => [
            'user.username' => 'username',
            'user.customer_id' => 'customer_id',
        ],
    'blacklist' => ['customer_id'],
],
```

## Using AuditStash

Enabling the Audit Log in any of your table classes is as simple as adding a behavior in the `initialize()` function:

```php
class ArticlesTable extends Table
{
    public function initialize(array $config = [])
    {
        ...
        $this->addBehavior('AuditStash.AuditLog');
    }
}
```

### Configuring the Behavior

The `AuditLog` behavior can be configured to ignore certain fields of your table, by default it ignores the `id`, `created` and `modified` fields:

```php
class ArticlesTable extends Table
{
    public function initialize(array $config = [])
    {
        ...
        $this->addBehavior('AuditStash.AuditLog', [
            'blacklist' => ['created', 'modified', 'another_field_name']
        ]);
    }
}
```

If you prefer, you can use a `whitelist` instead. This means that only the fields listed in that array will be tracked by the behavior:


```php
public function initialize(array $config = [])
{
    ...
    $this->addBehavior('AuditStash.AuditLog', [
        'whitelist' => ['title', 'description', 'author_id']
    ]);
}
```

If you need to retrieve human-friendly data fields from related tables (i.e. with foreign keys) you can set `foreignKeys` as below.
```php

public function initialize(array $config = [])
{
    ...

    $this->addBehavior('AuditStash.AuditLog', [
        'blacklist' => ['customer_id', 'product_id'],
        'foreignKeys' => [
            'Categories' => 'name', // foreign key Model => human-friendly field name
            'ProductStatuses' => 'status',
        ],
        'unsetAssociatedEntityFieldsNotDirtyByFieldName' => [
            'associated_table_name' => 'field_name_in_associated_table'
        ]
    ]);
}
```

As explained in the project description above, CakePHP (4.x) ORM returns all associated data even if no are changes made to the associated data.
Therefore, you need to set `unsetAssociatedEntityFieldsNotDirtyByFieldName` as you can see in the above example if you need to remove unchanged data from the associated entities.

### Storing The Logged In User

It is often useful to store the identifier of the user that is triggering the changes in a certain table. For this purpose, `AuditStash`
provides the `RequestMetadata` listener class, that is capable of storing the current URL, IP and logged in user. You need to add this
listener to your application in the `AppController::beforeFilter()` method:

```php
use AuditStash\Meta\RequestMetadata;
...

class AppController extends Controller
{
    public function beforeFilter(Event $event)
    {
        ...
        $eventManager = $this->loadModel()->eventManager();
        $identity = $this->request->getAttribute('identity');
        if ($identity != null) {
            $eventManager->on(
                 new RequestMetadata($this->request, [
                    'username' => $identity['username'],
                    'customer_id' => $identity['customer_id'],
                ])
            );
        }
    }
}
```

The above code assumes that you will trigger the table operations from the controller, using the default Table class for the controller.
If you plan to use other Table classes for saving or deleting inside the same controller, it is advised that you attach the listener
globally:


```php
use AuditStash\Meta\RequestMetadata;
use Cake\Event\EventManager;
...

class AppController extends Controller
{
    public function beforeFilter(Event $event)
    {
        ...
        $identity = $this->request->getAttribute('identity');
        if ($identity != null) {
            EventManager::instance()->on(
                new RequestMetadata($this->request, [
                    'username' => $identity['username'],
                    'customer_id' => $identity['customer_id'],
                ])
            );
        }
    }
}
```

### Storing Extra Information In Logs

`AuditStash` is also capable of storing arbitrary data for each of the logged events. You can use the `ApplicationMetadata` listener or
create your own. If you choose to use `ApplicationMetadata`, your logs will contain the `app_name` key stored and any extra information
your may have provided. You can configure this listener anywhere in your application, such as the `bootstrap.php` file or, again, directly
in your AppController.


```php
use AuditStash\Meta\ApplicationMetadata;
use Cake\Event\EventManager;

EventManager::instance()->on(new ApplicationMetadata('my_blog_app', [
    'server' => $theServerID,
    'extra' => $somExtraInformation,
    'moon_phase' => $currentMoonPhase
]));

```

Implementing your own metadata listeners is as simple as attaching the listener to the `AuditStash.beforeLog` event. For example:

```php
EventManager::instance()->on('AuditStash.beforeLog', function ($event, array $logs) {
    foreach ($logs as $log) {
        $log->setMetaInfo($log->getMetaInfo() + ['extra' => 'This is extra data to be stored']);
    }
});
```

### Implementing Your Own Persister Strategies

There are valid reasons for wanting to use a different persist engine for your audit logs. Luckily, this plugin allows you to implement
your own storage engines. It is as simple as implementing the `PersisterInterface` interface:

```php
use AuditStash\PersisterInterface;

class MyPersister implements PersisterInterface
{
    public function logEvents(array $auditLogs)
    {
        foreach ($auditLogs as $log) {
            $eventType = $log->getEventType();
            $data = [
                'timestamp' => $log->getTimestamp(),
                'transaction' => $log->getTransactionId(),
                'type' => $log->getEventType(),
                'primary_key' => $log->getId(),
                'source' => $log->getSourceName(),
                'parent_source' => $log->getParentSourceName(),
                'original' => json_encode($log->getOriginal()),
                'changed' => $eventType === 'delete' ? null : json_encode($log->getChanged()),
                'meta' => json_encode($log->getMetaInfo())
            ];
            $storage = new MyStorage();
            $storage->save($data);
        }
    }
}
```

Finally, you need to configure `AuditStash` to use your new persister. In the `config/app.php` file add the following
lines:

```php
'AuditStash' => [
    'persister' => 'App\Namespace\For\Your\Persister'
]
```

or if you are using as standalone via

```php
\Cake\Core\Configure::write('AuditStash.persister', 'App\Namespace\For\Your\DatabasePersister');
```

The configuration contains the fully namespaced class name of your persister.

### Working With Transactional Queries

Occasionally, you may want to wrap a number of database changes in a transaction, so that it can be rolled back if one part of the process fails. In order to create audit logs during a transaction, some additional setup is required. First create the file `src/Model/Audit/AuditTrail.php` with the following:

```php
<?php
namespace App\Model\Audit;

use Cake\Utility\Text;
use SplObjectStorage;

class AuditTrail
{
    protected $_auditQueue;
    protected $_auditTransaction;

    public function __construct()
    {
        $this->_auditQueue = new SplObjectStorage;
        $this->_auditTransaction = Text::uuid();
    }

    public function toSaveOptions()
    {
        return [
            '_auditQueue' => $this->_auditQueue,
            '_auditTransaction' => $this->_auditTransaction
        ];
    }
}
```

Anywhere you wish to use `Connection::transactional()`, you will need to first include the following at the top of the file:

```php
use App\Model\Audit\AuditTrail;
use Cake\Event\Event;
```

Your transaction should then look similar to this example of a BookmarksController:

```php
$trail = new AuditTrail();
$success = $this->Bookmarks->connection()->transactional(function () use ($trail) {
    $bookmark = $this->Bookmarks->newEntity();
    $bookmark1->save($data1, $trail->toSaveOptions());
    $bookmark2 = $this->Bookmarks->newEntity();
    $bookmark2->save($data2, $trail->toSaveOptions());
    ...
    $bookmarkN = $this->Bookmarks->newEntity();
    $bookmarkN->save($dataN, $trail->toSaveOptions());

    return true;
});

if ($success) {
    $event = new Event('Model.afterCommit', $this->Bookmarks);
    $table->behaviors()->get('AuditLog')->afterCommit($event, $result, $auditTrail->toSaveOptions());
}
```

This will save all audit info for your objects, as well as audits for any associated data. Please note, `$result` must be an instance of an Object. Do not change the text "Model.afterCommit".

### Saving Multiple Entities
Create the file `src/Model/Audit/AuditTrail.php` as shown in the above section

```php
...
$auditTrail = new AuditTrail();

if ($this->Bookmarks->saveMany($entities, $auditTrail->toSaveOptions())) {
    ...                   
}
```

