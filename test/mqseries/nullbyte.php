NULLBYTE HANDLING TEST

<?php
define('EOL', PHP_EOL);

// При отправке сообщения подставляется OriginMsgId, который содержит null-байт
// В итоге на сервере оказывается обрезанный MsgId до null-byte
//
// Если на 57 строке расскоментить MQSERIES_MQPMO_NEW_MSG_ID, то сервер будет каждый раз генерить новый id
// Соответственно периодически сервер сам генерит MsgId с null-байтом.
// И при чтении такого сообщения MsgId также обрезается до null-байта


$OriginMsgId = hex2bin('414D5120534954455F514D2020202020B05ADA58002BB620');
echo 'Origin message id:'.EOL.'414D5120534954455F514D2020202020B05ADA58002BB620'.EOL;
echo 'Origin idLen '.strlen($OriginMsgId).EOL.EOL;



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
	printf("Connx CompCode:%d Reason:%d Text:%s".EOL, $comp_code, $reason, mqseries_strerror($reason));
    exit;
}



$i=0;
while (!strpos(bin2hex($msgId), '00') && $i<1000) {
        $i++;
        echo EOL.$i.EOL;
		//Отправляем сообщение в очередь
		$md = 	array(
			'Version' => MQSERIES_MQMD_VERSION_1,
			'Expiry' => MQSERIES_MQEI_UNLIMITED,
			'Report' => MQSERIES_MQRO_NONE,
			'MsgType' => MQSERIES_MQMT_DATAGRAM,
			'Format' => MQSERIES_MQFMT_STRING,
			'Priority' => 1,
			'Persistence' => MQSERIES_MQPER_PERSISTENT,
			'MsgId' => $OriginMsgId
		);

		$pmo = array(
			'Options' => MQSERIES_MQPMO_FAIL_IF_QUIESCING
#						 | MQSERIES_MQPMO_NEW_MSG_ID
							,
			'Version' => MQSERIES_MQPMO_VERSION_3
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
		}

		$msgId = mqseries_bytes_val($md['MsgId']);
		echo 'PUT '.bin2hex($msgId).' LEN '.strlen($msgId).EOL;





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


		//Получаем сообщение
		$MQMD = [];
		$gmo = [
			'Version' => MQSERIES_MQGMO_VERSION_4,
			'Options' =>
				MQSERIES_MQGMO_FAIL_IF_QUIESCING |
				MQSERIES_MQGMO_NO_WAIT |
				MQSERIES_MQGMO_NO_PROPERTIES
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


		$msgId = mqseries_bytes_val($MQMD['MsgId']);
		echo 'GET '.bin2hex($msgId).' LEN '.strlen($msgId).EOL;


		$comp_code = $reason = null;
		mqseries_close(
			$conn,
			$hobj,
			MQSERIES_MQCO_NONE,
			$comp_code,
			$reason);
		if ($comp_code !== MQSERIES_MQCC_OK) {
			printf("Close CompCode:%d Reason:%d Text:%s".EOL, $comp_code, $reason, mqseries_strerror($reason));
			exit;
		}

}


//Закрываем соединение
$comp_code = $reason = null;
mqseries_disc(
	$conn,
	$comp_code,
	$reason
);
if ($comp_code !== MQSERIES_MQCC_OK) {
	printf("Disc CompCode:%d Reason:%d Text:%s".EOL, $comp_code, $reason, mqseries_strerror($reason));
	exit;
}



