<?php

require_once 'src/MQClient.php';
require_once 'src/MQObject.php';
require_once 'src/MQMessage.php';


use rstmpw\ibmmq;


$mqConnOpts = ibmmq\MQClient::makeConnOpts('SITE_QM', '172.18.0.7', '41414');
$mq = new ibmmq\MQClient($mqConnOpts);
$queue = $mq->openQueue('MQSERIES_TEST');


$message = new ibmmq\MQMessage('Message from '.date('Y-m-d H:i:s'));
$message->header('usr.Test.String', 'Стандартный каталог для кастомных заголовков usr');
$message->header('usr.Test.Int', 100);
$message->header('usr.Test.Bool', true);
$message->header('usr.Test.Float', 2.15);
$message->header('usr.SomeH', 'Некая SomeH в каталоге usr, вернется как просто SomeH');
$message->header('TestH', 'Просто TestH, при отправке будет приведен к usr.TestH и вернется соответственно как просто TestH');
//$message->header('Test', 'Нельзя установить промежуточный уровень, когда есть дочерние.');
//$message->header('usr.SomeH.second', 'И наоборот тоже - определить дочерние от уже сучествующего');
$message->header('catalog.some', 'some в каталоге catalog');
$message->header('catalog', 'А вот так можно сделать, тк это приведется к usr.catalog');
$message->header('usr.Notice', 'В остальных случаях все будет возвращаться как и отправлялось');
var_dump($message->getAllHeaders());

//Через put1
//$mq->put1($message, 'MQSERIES_TEST');

//Через object и put
$queue->put($message);

echo PHP_EOL.'OUT '.$message->data().PHP_EOL.bin2hex($message->property('MsgId')).PHP_EOL;




$getMessage = $queue->get();
echo PHP_EOL.'IN  '.$getMessage->data().PHP_EOL.bin2hex($getMessage->property('MsgId')).PHP_EOL;
var_dump($getMessage->getAllHeaders());
