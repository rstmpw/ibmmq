RFH a


<?php
$ln = "\n";

echo microtime(true).$ln;

//Соединяемся с менеджером
$mqcno = array(
        'Version' => MQSERIES_MQCNO_VERSION_2,
        'Options' => MQSERIES_MQCNO_STANDARD_BINDING,
        'MQCD' => array(
			'ChannelName' => 'SYSTEM.ADMIN.SVRCONN',
        	'ConnectionName' => '172.18.0.7(41414)',
        	'TransportType' => MQSERIES_MQXPT_TCP
		)
    );
$conn = $comp_code = $reason = null;
mqseries_connx('SITE_QM', $mqcno, $conn, $comp_code,$reason);
if ($comp_code !== MQSERIES_MQCC_OK) {
	printf("Connx CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
    exit;
}

	

//Создаем message handle
		$mqcmho = array(
			'Version' => MQSERIES_MQCMHO_VERSION_1,
			'Options' => MQSERIES_MQCMHO_VALIDATE
		);

		$hmsg = $comp_code = $reason = null;
		mqseries_crtmh($conn, $mqcmho, $hmsg, $comp_code,$reason);
		if ($comp_code !== MQSERIES_MQCC_OK) {
			printf("Crtmh CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
			exit;
		}


//Устанавливаем свойства
		$mqsmpo = array(
			'Version' => MQSERIES_MQSMPO_VERSION_1,
			'Options' => MQSERIES_MQSMPO_SET_FIRST
		);

		$mqpd= array(
			'Version' => MQSERIES_MQPD_VERSION_1,
			'Options' => MQSERIES_MQPD_NONE,
			// 'Support' => MQSERIES_MQPD_SUPPORT_OPTIONAL,
			// 'Context' => MQSERIES_MQPD_NO_CONTEXT,
			// 'CopyOptions' => MQSERIES_MQCOPY_FORWARD
			);




		// NULL
		$propNullName = "usr.Hnull";
		$comp_code = $reason = null;
		mqseries_setmp($conn, $hmsg, $mqsmpo, $propNullName, $mqpd, MQSERIES_MQTYPE_STRING, null,  $comp_code, $reason);
		if ($comp_code !== MQSERIES_MQCC_OK) {
			printf("Setmp CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
			exit;
		}

		// boolean
		$propBoolData =  true;
		$propBoolName = "usr.Hboolean";
		$comp_code = $reason = null;
		mqseries_setmp($conn, $hmsg, $mqsmpo, $propBoolName, $mqpd, MQSERIES_MQTYPE_BOOLEAN, $propBoolData,  $comp_code, $reason);
		if ($comp_code !== MQSERIES_MQCC_OK) {
			printf("Setmp CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
			exit;
		}

		//float
		// Это не ошибка? значение передается строкой? или можно и даже нужно нормальным float,
		// типа $data = 1.00457;
		// или так $data = (float) '0.12345';
		$propFloatData =  12.12;
		$propFloatName = "usr.test.Hfloat";
		$comp_code = $reason = null;
		mqseries_setmp($conn, $hmsg, $mqsmpo, $propFloatName, $mqpd, MQSERIES_MQTYPE_FLOAT32, $propFloatData, $comp_code, $reason);
		if ($comp_code !== MQSERIES_MQCC_OK) {
			printf("Setmp CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
			exit;
		}

		// Строка
		$propStrData =  "Test string";
		$propStrName = "usr.Hstring.v1";
		$comp_code = $reason = null;
		mqseries_setmp($conn, $hmsg, $mqsmpo, $propStrName, $mqpd, MQSERIES_MQTYPE_STRING, $propStrData,  $comp_code, $reason);
		if ($comp_code) {
			printf("1Setmp CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
			var_dump($comp_code);
			var_dump(MQSERIES_MQCC_OK);
			exit;
		}

		// Строка
		$propStrData =  "Test string 2";
		$propStrName = "usr.Hstring.v2";
		$comp_code = $reason = null;
		mqseries_setmp($conn, $hmsg, $mqsmpo, $propStrName, $mqpd, MQSERIES_MQTYPE_STRING, $propStrData,  $comp_code, $reason);
		if ($comp_code) {
			printf("2Setmp CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
			var_dump($comp_code);
			var_dump(MQSERIES_MQCC_OK);
			exit;
		}

		// Строка
		$propStrData =  "Test string 3";
		$propStrName = "Hstring.v3";
		$comp_code = $reason = null;
		mqseries_setmp($conn, $hmsg, $mqsmpo, $propStrName, $mqpd, MQSERIES_MQTYPE_STRING, $propStrData,  $comp_code, $reason);
		if ($comp_code) {
			printf("3Setmp CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
			var_dump($comp_code);
			var_dump(MQSERIES_MQCC_OK);
			exit;
		}

		//int
		$propIntData =  1501456014;
		$propIntName = "Hint";
		$comp_code = $reason = null;
		mqseries_setmp($conn, $hmsg, $mqsmpo, $propIntName, $mqpd, MQSERIES_MQTYPE_INT32, $propIntData, $comp_code, $reason);
		if ($comp_code !== MQSERIES_MQCC_OK) {
			printf("Setmp CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
			exit;
		}


//Отправляем сообщение в очередь
$md = 	array(
	'Version' => MQSERIES_MQMD_VERSION_1,
	'Expiry' => MQSERIES_MQEI_UNLIMITED,
	'Report' => MQSERIES_MQRO_NONE,
	'MsgType' => MQSERIES_MQMT_DATAGRAM,
	'Format' => MQSERIES_MQFMT_STRING,
	'Priority' => 1,
	'Persistence' => MQSERIES_MQPER_PERSISTENT
);

$pmo = array(
	'Options' => MQSERIES_MQPMO_NEW_MSG_ID ,
	'Version' => MQSERIES_MQPMO_VERSION_3,
	'NewMsgHandle' => $hmsg,
	'Action' => MQSERIES_MQACTP_NEW
);

$obj = 	array(
	'ObjectQMgrName' => 'SITE_QM',
	'ObjectName' => 'MQSERIES_TEST'
);

$comp_code = $reason = null;
mqseries_put1(
	$conn,
	$obj,
	$md,
	$pmo,
	'Ping',
	$comp_code,
	$reason
);
if ($comp_code !== MQSERIES_MQCC_OK) {
	printf("PUT1 CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
} else {
	echo "Put1 message ".$ln.base64_encode(mqseries_bytes_val($md[MsgId])).$ln.$ln;
}


//Удаляем message handle
		$mqdmho = array(
			'Version' => MQSERIES_MQDMHO_VERSION_1,
			'Options' => MQSERIES_MQDMHO_NONE
		);
		$comp_code = $reason = null;
		mqseries_dltmh ($conn, $hmsg,  $mqdmho,  $comp_code,$reason);
		if ($comp_code !== MQSERIES_MQCC_OK) {
			printf("Dltmh CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
			exit;
		}


die('end');





// Открываем очередь и создаем новый message handle
$MQOD = [
	'ObjectQMgrName' => 'SITE_QM',
	'ObjectName' => 'MQSERIES_TEST'
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
	printf("Open CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
	exit;
}

$mqcmho = array(
	'Version' => MQSERIES_MQCMHO_VERSION_1,
	'Options' => MQSERIES_MQCMHO_VALIDATE
);

$hmsg = $comp_code = $reason = null;
mqseries_crtmh($conn, $mqcmho, $hmsg, $comp_code,$reason);
if ($comp_code !== MQSERIES_MQCC_OK) {
	printf("Crtmh CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
	exit;
}

//Получаем сообщение
$MQMD = [];
$gmo = [
	'Version' => MQSERIES_MQGMO_VERSION_4,
	'Options' =>
		MQSERIES_MQGMO_FAIL_IF_QUIESCING |
		MQSERIES_MQGMO_NO_WAIT |
		MQSERIES_MQGMO_PROPERTIES_IN_HANDLE,
	'MsgHandle' => $hmsg
];

$MessageDataLen = 0;
$MessageData = $comp_code = $reason = null;

mqseries_get(
	$conn,
	$hobj,
	$MQMD,
	$gmo,
	4194304,
	$MessageData,
	$MessageDataLen,
	$comp_code,
	$reason);
if ($comp_code !== MQSERIES_MQCC_OK) {
	printf("Get CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
	exit;
}

echo "Get MessageId: ".$ln.base64_encode(mqseries_bytes_val($MQMD['MsgId'])).$ln;
echo "MessageData: $MessageData ".$ln.$ln;

//Получаем подряд все свойства сообщения
$i=0;
$inqPropName = '%';

while($i<10) {
	echo "<h1>".++$i."</h1>";

	//Узнаем длинну значения следующего свойства
	$mqimpo = array(
		'Options' => MQSERIES_MQIMPO_INQ_NEXT | MQSERIES_MQIMPO_QUERY_LENGTH
	);
	$inqPropNameQL = $inqPropName;
	$inqPropType = MQSERIES_MQTYPE_AS_SET;
	$inqPropData = null;
	$inqPropDataLen = 0;
	$mqpd = [];
	$comp_code = $reason = null;
	mqseries_inqmp(
		$conn,
		$hmsg,
		$mqimpo,
		$inqPropNameQL,
		$mqpd,
		$inqPropType,
		0,
		$inqPropData,
		$inqPropDataLen,
		$comp_code,
		$reason
	);
	if ($comp_code !== MQSERIES_MQCC_OK) {
		printf("inqmp1 getDataLen CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
		var_dump($inqPropName, $inqPropType, $inqPropData, $inqPropDataLen, MQSERIES_MQPROP_INQUIRE_ALL_USR, MQSERIES_MQTYPE_AS_SET, MQSERIES_MQIMPO_INQ_NEXT, MQSERIES_MQIMPO_QUERY_LENGTH);
		exit;
	}

	echo "INQ LEN $ln";
	echo "PropName: $inqPropNameQL $ln";
	echo "PropDefinedType: $inqPropType $ln";
	echo "PropDataLen: $inqPropDataLen $ln";
	echo "PropData: $inqPropData $ln $ln";


	$inqPropNameIP = $inqPropName;
	$inqPropType = MQSERIES_MQTYPE_AS_SET;
	$inqPropData = null;
	$bufferLen = $inqPropDataLen;
	$inqPropDataLen = 0;

	//Получаем значение свойства уже известного размера
	$mqimpo = array(
		'Options' => MQSERIES_MQIMPO_INQ_PROP_UNDER_CURSOR
	);
	$mqpd= array(
		'Version' => MQSERIES_MQPD_VERSION_1,
		'Options' => MQSERIES_MQPD_NONE,
	);
	$comp_code = $reason = null;
	mqseries_inqmp(
		$conn,
		$hmsg,
		$mqimpo,
		$inqPropNameIP,
		$mqpd,
		$inqPropType,
		100,
		$inqPropData,
		$inqPropDataLen,
		$comp_code,
		$reason
	);
	if ($comp_code !== MQSERIES_MQCC_OK) {
		printf("inqmp2 getData CompCode:%d Reason:%d Text:%s", $comp_code, $reason, mqseries_strerror($reason));
		if($comp_code == MQSERIES_MQCC_FAILED) echo " MQCC_FAILED";
		//exit;
	}



	echo "INQ DATA $ln";
	var_dump($inqPropNameIP);
	var_dump($mqimpo);
	var_dump($mqpd);
	echo "PropName: $inqPropNameIP $ln";
	echo "PropDefinedType: $inqPropType $ln";
	echo "PropPHPType: ".gettype($inqPropData).$ln;
	echo "PropDataLen: $inqPropDataLen $ln";
	echo "PropData: $inqPropData $ln $ln";


}



//Удаляем message handle и закрываем очередь
$mqdmho = array(
	'Version' => MQSERIES_MQDMHO_VERSION_1,
	'Options' => MQSERIES_MQDMHO_NONE
);
$comp_code = $reason = null;
mqseries_dltmh ($conn, $hmsg,  $mqdmho,  $comp_code,$reason);
if ($comp_code !== MQSERIES_MQCC_OK) {
	printf("Dltmh CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
	exit;
}

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




//Закрываем соединение
$comp_code = $reason = null;
mqseries_disc(
	$conn,
	$comp_code,
	$reason
);
if ($comp_code !== MQSERIES_MQCC_OK) {
	printf("Disc CompCode:%d Reason:%d Text:%s<br>\n", $comp_code, $reason, mqseries_strerror($reason));
	exit;
}



