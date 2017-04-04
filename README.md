# IBM MQ - библиотека для взаимодействия с IBM WebSphere MQ

## Установка
```bash
composer require rstmpw/ibmmq:^1
```

Либо в файл composer.json проекта добавить следующую информацию (нехороший способ):
```json
{
  "require": {
    "rstmpw/ibmmq": "^1"
  }
}
```

## Использование
Функции могут выбрасывать исключение \RuntimeException

### Получение/отправка сообщений из очереди
```php
<?php
use rstmpw\ibmmq\MQClient;
use rstmpw\ibmmq\MQMessage;

// Функция-помощник для формирования правильной структуры параметров подключения.
// Если используется внешнее хранилище параметров подклчений, то там следует хранить уже
// сформированную структуру.
$mqConnOpts = MQClient::makeConnOpts('SITE_QM', '172.18.0.7', '41414');

// Соединяемся с сервером
$mqServer = new MQClient($mqConnOpts);

// Открываем очередь
$mqQueue = $mqServer->openQueue('KS_VV_IN');
// Для удаленных очередей:
// $mqOueue = $mqServer->openQueue('VV_KS_OUT', [MQSERIES_MQOO_FAIL_IF_QUIESCING, MQSERIES_MQOO_OUTPUT]);

// Создаем сообщение и отправляем его в очередь
$outMessage = new MQMessage('Somebody message '.time());
echo "\n Data: ".$outMessage->data();
$mqQueue->put($outMessage);
echo "\n msgId: ".$outMessage->property('MsgId');

// Получаем сообщение из очереди
$inMessage = $mqQueue->get();
echo "\n Data: ".$inMessage->data();
echo "\n msgId: ".$inMessage->property('MsgId');

// Закрываем объект и соединение с сервром
// Явным образом можно не закрывать, деструкторы сделают все автоматически
$mqQueue->close();
unset($mqServer);
```

Если в очередь нужно положить только одно сообщение, то очередь можно явно не открывать
и воспользоваться функцией put1()

```php
<?php
use rstmpw\ibmmq\MQClient;
use rstmpw\ibmmq\MQMessage;

$mqConnOpts = MQClient::makeConnOpts('SITE_QM', '172.18.0.7', '41414');
$mqServer = new MQClient($mqConnOpts);

// Создаем сообщение
$outMessage = new MQMessage('Somebody message '.time());

$mqServer->put1($outMessage, 'KS_VV_IN');
echo "\n msgId: ".$outMessage->property('MsgId');
```

### Получение сообщений из очереди с ожиданием и выборкой по CorrelId
```php
<?php
use rstmpw\ibmmq\MQClient;
use rstmpw\ibmmq\MQMessage;

$mqConnOpts = MQClient::makeConnOpts('SITE_QM', '172.18.0.7', '41414');
$mqServer = new MQClient($mqConnOpts);

$mqQueue = $mqServer->openQueue('KS_VV_IN');

// Устанавливаем опции получения сообщения и выборки
$getOpts = [
    'Version' => MQSERIES_MQGMO_VERSION_2,
    'Options' => [MQSERIES_MQGMO_FAIL_IF_QUIESCING, MQSERIES_MQGMO_WAIT],
    'WaitInterval' => 60*1000, //60 sec
    'MatchOptions' => [MQSERIES_MQMO_MATCH_CORREL_ID]
];
$MQMD = [
    'MsgId' => MQSERIES_MQMI_NONE,
    'CorrelId' => 'NeedCorrelId'
];

// Получаем сообщение из очереди
$inMessage = $mqQueue->get($getOpts, $MQMD);
echo "\n Data: ".$inMessage->data();
echo "\n msgId: ".$inMessage->property('MsgId');
echo "\n correlId: ".$inMessage->property('CorrelId');

$mqQueue->close();
unset($mqServer);
```

### Запись/чтение свойств сообщений
```php
<?php
use rstmpw\ibmmq\MQClient;
use rstmpw\ibmmq\MQMessage;

$mqConnOpts = MQClient::makeConnOpts('SITE_QM', '172.18.0.7', '41414');
$mqServer = new MQClient($mqConnOpts);

// Создаем сообщение и устнавливаем свойства
$outMessage = new MQMessage('Somebody message '.time());
$outMessage->property('Priority', 7);
$outMessage->property('ReplyToQ', 'RESPONSE_QUEUE');
$outMessage->property('ReplyToQMgr', 'RESPONSE_QM');

// Отправляем сообщение
$mqServer->put1($outMessage, 'KS_VV_IN');

// Получаем сообщение и смотрим свойства
$mqQueue = $mqServer->openQueue('KS_VV_IN');
$inMessage = $mqQueue->get();
echo "\n Data: ".$inMessage->data();
echo "\n msgId: ".$inMessage->property('MsgId');
echo "\n Priority: ".$inMessage->property('Priority');
echo "\n ReplyToQ: ".$inMessage->property('ReplyToQ');
echo "\n ReplyToQMgr: ".$inMessage->property('ReplyToQMgr');
```

### Публикация топиков
Полностью похожа на отправку сообщения, за исключением того что вместо имени очереди
используется имя топика и из топика нельзя читать сообщения.
Имя топика предствдяет собой строку разделенную '/', например 'news/sport/nascar'.
```php
<?php
use rstmpw\ibmmq\MQClient;
use rstmpw\ibmmq\MQMessage;

$mqConnOpts = MQClient::makeConnOpts('SITE_QM', '172.18.0.7', '41414');
$mqServer = new MQClient($mqConnOpts);

// Открываем топик
$mqQueue = $mqServer->openTopic('news/sport/nascar');

// Создаем сообщение и публикуем его в топик
$outMessage = new MQMessage('Latest news about nascar racing');
$mqQueue->put($outMessage);
```
Также поддерживается сокращенный вызов через put1, за исключением того что имя топика должно начинаться с 'TOPIC::'
```php
<?php
use rstmpw\ibmmq\MQClient;
use rstmpw\ibmmq\MQMessage;

$mqConnOpts = MQClient::makeConnOpts('SITE_QM', '172.18.0.7', '41414');
$mqServer = new MQClient($mqConnOpts);

// Создаем сообщение и публикуем его в топик
$outMessage = new MQMessage('Latest news about nascar racing');
$mqServer->put1($outMessage, 'TOPIC::news/sport/nascar/winners/top/1');
```

### Транзакции
Работают аналогично транзакциям в СУБД
```php
<?php
use rstmpw\ibmmq\MQClient;
use rstmpw\ibmmq\MQMessage;

$mqConnOpts = MQClient::makeConnOpts('SITE_QM', '172.18.0.7', '41414');
$mqServer = new MQClient($mqConnOpts);

// Отправляем 2 сообщения вне транзакции
$outMessage = new MQMessage('Put message w/o transaction');
$mqServer->put1($outMessage, 'VV_INF_IN');
$mqServer->put1($outMessage, 'VV_INF_IN');

// Открывем транзакцию
$mqServer->begin();

// Получаем сообщение из очереди VV_INF_IN
$mqQueue = $mqServer->openQueue('VV_INF_IN');
$mqQueue->get();

// Отправляем сообщение в очередь KS_VV_IN с использованием put1
$mqServer->put1(new MQMessage('MSG1'), 'KS_VV_IN');

// Отправляем еще 2 сообщения в очередь KS_VV_IN и одно оттуда забираем через явное открытие очереди
$mqQueueOut = $mqServer->openQueue('KS_VV_IN');
$mqQueueOut->put(new MQMessage('MSG2'));
$mqQueueOut->put(new MQMessage('MSG2'));
$mqQueueOut->get();

// Фиксируем транзакцию
$mqServer->commit();
// Если считать, что перед запуском скрипта очереди были путые, то в итоге в очереди VV_INF_IN будет одно сообщение,
// a в очереди KS_VV_IN - 2 сообщения.
// Если явным образом откатить транзакцию
// $mqServer->rollback();
// или явно ее не закоммитить, то в очереди VV_INF_IN будет 2 сообщения, а KS_VV_IN останется пустой.
```

## Зависимости

- Библиотека работает с PHP 7.0 и выше.
- Для работы требуется доработанное [pecl расширение mqseries](https://github.com/adoy/pecl-networking-mqseries/tree/PHP7).
