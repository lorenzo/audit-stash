# AuditStash Plugin For CakePHP

This plugin is forked from [lorenzo/audit-stash](https://github.com/lorenzo/audit-stash)

The above plugin has the following issues or does not have the features I wanted.
- Original data is not recorded at the delete event.
    - This is useful if you add the plugin after some data has already been added.
  
- Associated table records were not saved properly
    - Currently, in the CakePHP (4.x) ORM when there is a ‘hasMany’ relationship (for example; think about 2 DB tables: items and item_attributes), `EntityTrait::extractOriginal(array $fields)` doesn't return the original value of the associated table’s (item_attributes) original data instead they return the modified values.
    - Also, there is another bug in CakePHP (4.x) ORM, it marks the associated ('hasMany') entities as dirty, even if there are no changes made to the associated table data.

- Unable to record audit logs when saveMany() is called to save multiple entities. 


Therefore, I decided to fork from the original project and improve it to support the above missing features.

## Installation

You can install this plugin into your CakePHP application using [composer](https://getcomposer.org) and executing the
following lines in the root of your application.

```
composer require kdesilva/audit-trail
bin/cake plugin load AuditStash
```

For using the default storage engine (ElasticSearch) you need to install the official `elastic-search` plugin, by executing
the following lines:

```
composer require cakephp/elastic-search
bin/cake plugin load Cake/ElasticSearch
```

## Configuration

### Elastic Search

You now need to add the datasource configuration to your `config/app.php` file:

```php
'Datasources' => [
    'auditlog_elastic' => [
        'className' => 'Cake\ElasticSearch\Datasource\Connection',
        'driver' => 'Cake\ElasticSearch\Datasource\Connection',
        'host' => '127.0.0.1', // server where elasticsearch is running
        'port' => 9200
    ],
    ...
]
```

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
add a table named `audit_logs` with all the default columns - alternatively create the table manually. After that you
can bake the corresponding table class.

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
        'user.id' => 'user_id'
    ]
]);
```


Also, you can set some common config via the app.php. Currently, support 'extractMetaFields' and 'blacklist'

```php
'AuditStash' => [
    'persister' => 'AuditStash\Persister\TablePersister',
    'extractMetaFields' => [
            'user.username' => 'username',
            'user.customer_id' => 'customer_id',
        ],
    'blacklist' => ['customer_id'],
]
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

When using the `Elasticserch` persister, it is recommended that you tell Elasticsearch about the schema of your table. You can do this
automatically by executing the following command:

```
bin/cake elastic_mapping Articles
```

If you are using one index per day, save yourself some time and add the `--use-templates` option. This will create a schema template so
any new index will inherit this configuration:

```
bin/cake elastic_mapping Articles --use-templates
```

Remember to execute the command line each time you change the schema of your table!

### Configuring The Behavior

The `AuditLog` behavior can be configured to ignore certain fields of your table, by default it ignores the `created` and `modified` fields:

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
        $eventManager->on(new RequestMetadata($this->request, $this->Auth->user('id')));
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
        EventManager::instance()->on(new RequestMetadata($this->request, $this->Auth->user('id')));
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
