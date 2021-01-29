# Combodo monitoring

## Feature
This extension let you expose metrics to monitoring systems.
Currently, it supports only prometheus, but it should be easy to add support for other solutions.

## Usage

In order to use this extension, You'll spend most of your time within the configuration:
```php
$MyModuleSettings = array(
	'combodo-monitoring' => array (
		'authorized_network' => '',
		'access_token' => '123',
		'metrics' => array (
          'collection1' => array(
            //Metrics will be configured here later on          
          ),
          'collection2' => array(
             //Another collection of metrics can be placed here
          ),
          //... you can add as many collection as you want
        ),
	),
);
```

Then, Prometheus just has to call this url:
> https://example.com/iTop/pages/exec.php?exec_module=combodo-monitoring&exec_page=index.php&exec_env=production&access_token=123&collection=collection1


Lets deep dive into each configuration key we've just seen:

### authorized_network
This must contains a list of authorized IP under this form : /!\ TODO /!\

Since this extension can expose sensible information, you should allow only private network!!

### access_token
This token is required to access the pages.

Since it is given as a GET parameter (named `access_token`), you should only use https pages.

### metrics
Here we are, the final goal of this extension is to expose metrics to a monitoring agent.

### collections
Since not all metrics should be pulled with the same frequency, you have to group them within collections.
Their name is free, Prometheus just have to ask for one of them using the GET parameter `collection`.


Let's see some examples of metric within a collection:
```php 
array(
  'itop_backup' =>
  array (
    'description' => 'backup retention_count',
    'conf' => ['MyModuleSettings', 'itop-backup', 'retention_count'],
  ),
),
'count_users' =>
  array (
    'description' => 'Nb of users',
    'oql_count' => [
        'select' => 'SELECT URP_UserProfile JOIN URP_Profiles AS URP_Profiles_profileid ON URP_UserProfile.profileid =URP_Profiles_profileid.id',
        'groupby' => ['profile' => 'URP_Profiles_profileid.friendlyname'],
    ]
),
```

The first one, named `itop_backup` gather data from the configuration file, while the second uses an OQL to count matching objects.
As you can see, each individual metric is typed in order to perform specialized data gathering.
From now on wee will name them "Metric readers"
Let's see all the builtins Metric readers:

## Metric readers

### ConfReader
As seen before, this reader simply extract data from itop's configuration.
You can either read from `MySettings` or from `MyModuleSettings`
*Example*:
```php 
array(
  'cron_max_execution_time' =>
  array (
    'description' => 'cron max execution time',
    'conf' => ['MySettings', 'cron_max_execution_time'],
  ),
),
array(
  'itop_backup' =>
  array (
    'description' => 'backup retention_count',
    'conf' => ['MyModuleSettings', 'itop-backup', 'retention_count'],
  ),
),
```

If you want to extract data from multidimensional arrays, simply add all the keys.
example:
```php 
array(
  'monitoring_access_key' =>
  array (
    'description' => 'The monitoring access key',
    'conf' => ['MyModuleSettings', 'combodo-monitoring', 'foo', 'key1', 'key2', 'key3'],
  ),
);

//This will access the string `'I am accessed'` within this structure :
$MyModuleSettings = [
    'combodo-monitoring' => [
        'foo' => [
           'key1' => [
               'key2' => [
                    'key3' => [
                        'I am accessed'
                    ]
               ] 
            ] 
        ]
    ]
];
```

### OqlCountReader

This Reader is the simplest possible one: it count the number of result .

*Example*:
```php 
[
    'oql_count' => [
        'select' => 'SELECT User',      
    ],
    'description' => 'ordered users',
]
```

### OqlGroupByReader

This Reader count the number of rows for each group.
The labels names are the keys of the group by alias, and the label values are the value of the grouped column

*Example*:
```php 
 [
    'description' => 'ordered users',
    'oql_groupby' => [
        'select' => 'SELECT User',
        'groupby' => ['first_name' => 'first_nameAlias'],    
    ],
]
```


### OqlSelectReader

This reader expose the value for each column, the column name being in the labels.
*Example*:
```php 
[
    'description' => 'ordered users',
    'oql_select' => [
        'select' => 'SELECT User',
        'columns' =>  ['first_name', 'last_name'],
        
        // optional:
        'orderby' => ['first_name' => true, '_itop_count_' => false],         
        // optional:
        'limit_count' => 0,         
        // optional:
        'limit_start' => 0,  
    ],
]
```

Please note that `orderby`, `limit_count` and `limit_start` are optionals, and take the standard iTop's syntax.


### CustomReader

This reader is by far the most complicated, but it is also the one that can barely collect everything.
The principe is simple:
This reader will accept to call any class that implements `Combodo\iTop\Monitoring\MetricReader\CustomReaderInterface`.
So simply put any such class name into the `'class'` property.

*Example*:
```php 
[
    'custom' => ['class' => CustomReaderImpl::class],
    'description' => 'descriptionFromConf'
    
    //As this configuration array is passed to the class, feel free to add any relevant configuration for your use case.
]
```
This will call the CustomReaderImpl that YOU have to `implemnt`, for example, you can declare:

```php 
namespace Combodo\iTop\Monitoring\Test\MetricReader\CustomReaders;

use Combodo\iTop\Monitoring\Model\Constants;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;

class CustomReaderImpl implements \Combodo\iTop\Monitoring\MetricReader\CustomReaderInterface
{

    private $aMetricConf;

    public function __construct($aMetricConf)
    {
        $this->aMetricConf = $aMetricConf;
    }

    /**
     * @inheritDoc
     */
    public function GetMetrics(): ?array
    {
        return [ new MonitoringMetric('foo', $this->aMetricConf[Constants::METRIC_DESCRIPTION] ?? '', 42, ['bar' => 'baz'])];
    }
}
```




## Tests
For a functional understanding of this extension, please read the test: `\CombodoMonitoringTest`

For more unit test, read the others



