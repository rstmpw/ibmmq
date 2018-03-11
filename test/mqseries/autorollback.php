AUTO ROLLBACK TEST

<?php

define('EOL', PHP_EOL);

//Соединяемся с менеджером
$mqcno = array(
        'Version' => MQSERIES_MQCNO_VERSION_2,
        'Options' => MQSERIES_MQCNO_STANDARD_BINDING,
        'MQCD' => array(
			'ChannelName' => 'SYSTEM.ADMIN.SVRCONN',
        	'ConnectionName' => '172.18.0.7(41414)',
        	'TransportType' => MQSERIES_MQXPT_TCP,
            'MaxMsgLength' => '104857600'
		)
    );
$conn = $comp_code = $reason = null;
mqseries_connx('SITE_QM', $mqcno, $conn, $comp_code,$reason);
if ($comp_code !== MQSERIES_MQCC_OK) {
	printf("Connx CompCode:%d Reason:%d Text:%s".EOL, $comp_code, $reason, mqseries_strerror($reason));
    exit;
}
echo "Connected".EOL;


// Открываем очередь и создаем новый message handle
$MQOD = [
	'ObjectQMgrName' => 'SITE_QM',
	'ObjectName' => 'MQSERIES_TEST1'
];
$mqoo =  MQSERIES_MQOO_INPUT_AS_Q_DEF |
		 MQSERIES_MQOO_FAIL_IF_QUIESCING |
		 MQSERIES_MQOO_OUTPUT |
		 MQSERIES_MQOO_INQUIRE;
$hobj = $comp_code = $reason = null;
mqseries_open(
	$conn,
	$MQOD,
	$mqoo,
	$hobj,
	$comp_code,
	$reason
);
if ($comp_code !== MQSERIES_MQCC_OK) {
	printf("Open CompCode:%d Reason:%d Text:%s".EOL, $comp_code, $reason, mqseries_strerror($reason));
	exit;
}


//Получаем сообщение
$MQMD = [];
$gmo = [
	'Version' => MQSERIES_MQGMO_VERSION_4,
	'Options' =>
		MQSERIES_MQGMO_FAIL_IF_QUIESCING |
		MQSERIES_MQGMO_NO_WAIT |
		MQSERIES_MQGMO_NO_PROPERTIES |
		MQSERIES_MQGMO_SYNCPOINT
];

$MessageDataLen = 0;
$MessageData = $comp_code = $reason = null;

mqseries_get(
	$conn,
	$hobj,
	$MQMD,
	$gmo,
    104857600,
	$MessageData,
	$MessageDataLen,
	$comp_code,
	$reason);
if ($comp_code !== MQSERIES_MQCC_OK) {
	printf("Get CompCode:%d Reason:%d Text:%s".EOL, $comp_code, $reason, mqseries_strerror($reason));
	exit;
}

$msgId = mqseries_bytes_val($MQMD['MsgId']);
echo 'GET '.bin2hex($msgId).' BACKOUT '.$MQMD['BackoutCount'].EOL;



//Закрываем объект
$comp_code = $reason = null;
mqseries_close(
	$conn,
	$hobj,
	MQSERIES_MQCO_NONE,
	$comp_code,
	$reason);
if ($comp_code !== MQSERIES_MQCC_OK) {
	printf("Close CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
	exit;
}

echo "Message geted".EOL;


// 0. Нормальный коммит сообщения
/*
	$comp_code = $reason = null;
	mqseries_cmit(
		$conn,
		$comp_code,
		$reason
	);
	if ($comp_code !== MQSERIES_MQCC_OK) {
		printf("Close CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
		exit;
	}
*/

// 1. Нормальный откат сообщения
// При завершении скрипта сообщение снова в очереди
// Точно также должно произойти в случае фатальной ошибки
/*
	$comp_code = $reason = null;
	mqseries_back(
		$conn,
		$comp_code,
		$reason
	);
	if ($comp_code !== MQSERIES_MQCC_OK) {
		printf("Close CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
		exit;
	}

	mqseries_disc(
		$conn,
		$comp_code,
		$reason
	);
*/



// 2. Фатальная ошибка
// При таком раскаладе сообщение также должно быть возвращено в очередь, т.к. коммита не было

//	require ('dfgdrgergdfsdfef');



// 3. Утечка памяти
/*
	$i=1000000000000000;
	while ($i*100000) {
		$e.=$i;
	}
*/



// 4. Явное принудительно завершение скрипта (если запустить из консоли и прибить его через Ctrl+C или kill)
// Тут все срабатывает как надо, деструкторы не вызываются, сообщение возвращается в очередь
/*
	echo "Sleep 60s. Press Ctrl+C";
	sleep(60);
*/





// В конце скрипта сооединение не закрываем должным образом
// В случае если есть незакоммиченые соообщения, но соединение клиентом нормально закрывается через MQDISC,
// сервер MQ вызывает неявный коммит.
/*
echo "MQDISC".PHP_EOL;
mqseries_disc(
	$conn,
	$comp_code,
	$reason
);
*/


