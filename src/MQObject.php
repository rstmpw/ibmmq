<?php

namespace rstmpw\ibmmq;

use \RuntimeException;
use \InvalidArgumentException;


class MQObject {
	protected static $defGetMsgOpts = [
		'Version' => MQSERIES_MQGMO_VERSION_4,
		'Options' => [
			MQSERIES_MQGMO_FAIL_IF_QUIESCING,
			MQSERIES_MQGMO_NO_WAIT,
			MQSERIES_MQGMO_PROPERTIES_IN_HANDLE
		]
	];

    private $MQClient;
    private $connHandle;
    private $objHandle;
    private $bufferLen=4194304;

    //TODO Add length attrs
    private $INQCharAttrs = [
        MQSERIES_MQCA_ALTERATION_DATE,
        MQSERIES_MQCA_ALTERATION_TIME,
        MQSERIES_MQCA_BACKOUT_REQ_Q_NAME,
        MQSERIES_MQCA_BASE_Q_NAME,
        MQSERIES_MQCA_CLUS_CHL_NAME,
        MQSERIES_MQCA_CLUSTER_NAME,
        MQSERIES_MQCA_CLUSTER_NAMELIST,
        MQSERIES_MQCA_CREATION_DATE,
        MQSERIES_MQCA_CREATION_TIME,
        MQSERIES_MQCA_INITIATION_Q_NAME,
        MQSERIES_MQCA_PROCESS_NAME,
        MQSERIES_MQCA_Q_DESC,
        MQSERIES_MQCA_Q_NAME,
        MQSERIES_MQCA_REMOTE_Q_MGR_NAME,
        MQSERIES_MQCA_REMOTE_Q_NAME,
        MQSERIES_MQCA_TRIGGER_DATA,
        MQSERIES_MQCA_XMIT_Q_NAME,
        MQSERIES_MQCA_NAMELIST_DESC,
        MQSERIES_MQCA_NAMELIST_NAME,
        MQSERIES_MQCA_APPL_ID,
        MQSERIES_MQCA_ENV_DATA,
        MQSERIES_MQCA_PROCESS_DESC,
        MQSERIES_MQCA_PROCESS_NAME,
        MQSERIES_MQCA_USER_DATA,
        MQSERIES_MQCA_CHANNEL_AUTO_DEF_EXIT,
        MQSERIES_MQCA_CLUSTER_WORKLOAD_DATA,
        MQSERIES_MQCA_CLUSTER_WORKLOAD_EXIT,
        MQSERIES_MQCA_COMMAND_INPUT_Q_NAME,
        MQSERIES_MQCA_DEAD_LETTER_Q_NAME,
        MQSERIES_MQCA_DEF_XMIT_Q_NAME,
        MQSERIES_MQCA_INSTALLATION_DESC,
        MQSERIES_MQCA_INSTALLATION_NAME,
        MQSERIES_MQCA_INSTALLATION_PATH,
        MQSERIES_MQCA_PARENT,
        MQSERIES_MQCA_Q_MGR_DESC,
        MQSERIES_MQCA_Q_MGR_IDENTIFIER,
        MQSERIES_MQCA_Q_MGR_NAME,
        MQSERIES_MQCA_REPOSITORY_NAME,
        MQSERIES_MQCA_REPOSITORY_NAMELIST
    ];

    public function __construct(MQClient $MQClient, array $openParams, $bufferLen=null)
    {
        $CC = $RC = null;
        mqseries_open(
            $MQClient->connHandle,
            $openParams['MQOD'],
            array_reduce($openParams['Options'], function($res, $cur) { return $res | $cur; }, 0),
            $this->objHandle,
            $CC,
            $RC);

        if ($CC !== MQSERIES_MQCC_OK) {
            //TODO Log errors
            throw new RuntimeException(mqseries_strerror($RC), $RC);
        }

        $this->MQClient = $MQClient;
        $this->connHandle = $MQClient->connHandle;

        if($openParams['MQOD']['ObjectType'] === MQSERIES_MQOT_Q) {
            if ($bufferLen)
                $this->bufferLen = $bufferLen;
            elseif (\in_array(MQSERIES_MQOO_INQUIRE, $openParams['Options'], true))
                $this->bufferLen=$this->inq(MQSERIES_MQIA_MAX_MSG_LENGTH);
        }
    }

    public function __destruct()
    {
       $this->close();
    }

    public function close() {
        if(null === $this->objHandle) return;

        $CC = $RC = null;
        mqseries_close(
            $this->connHandle,
            $this->objHandle,
            MQSERIES_MQCO_NONE,
            $CC,
            $RC);

        if ($CC !== MQSERIES_MQCC_OK) {
            //TODO Log errors
        }
        $this->objHandle = null;
    }

    public function put(MQMessage $MQMessage, array $putParams=[])
    {
        if($MQMessage->property('MsgId') === false && !(
                isset($putParams['Options']) &&
                \in_array(MQSERIES_MQPMO_NEW_MSG_ID, $putParams['Options'], true)
            )
        )
            $putParams['Options'][] = MQSERIES_MQPMO_NEW_MSG_ID;

        if( $this->MQClient->inTransaction && !(
                isset($putParams['Options']) &&
                \in_array(MQSERIES_MQGMO_SYNCPOINT, $putParams['Options'], true)
            )
        ) {
            $putParams['Options'][] = MQSERIES_MQGMO_SYNCPOINT;
        }

		$msgHandle = null;
		if($MQMessage->hasHeaders()) {
			$msgHandle = $this->MQClient->createMsgHandle();
			foreach($MQMessage->getAllHeaders() as $HName => $HValue) {
				$this->MQClient->setMsgProp($msgHandle, $HName, $HValue);
			}

			if(!isset($putParams['Version']) || $putParams['Version'] < MQSERIES_MQPMO_VERSION_3)
				$putParams['Version'] = MQSERIES_MQPMO_VERSION_3;

			$putParams['NewMsgHandle'] = $msgHandle;
			$putParams['Action'] = MQSERIES_MQACTP_NEW;
		}

        $MQMD = $MQMessage->getAllProps();
        $putParams['Options'] = array_reduce($putParams['Options'], function($res, $cur) { return $res | $cur; }, 0);

        $CC = $RC = null;
        mqseries_put(
            $this->connHandle,
            $this->objHandle,
            $MQMD,
            $putParams,
            $MQMessage->data(),
            $CC,
            $RC);

        if ($CC !== MQSERIES_MQCC_OK) {
            //TODO Log errors
            throw new RuntimeException(mqseries_strerror($RC), $RC);
        }

		if(null !== $msgHandle) $this->MQClient->deleteMsgHandle($msgHandle);

        $MQMessage->setPropsArray($MQMD);
        return $this;
    }

    public function get(array $getParams = null, array $MQMD = ['Version' => MQSERIES_MQMD_VERSION_2])
    {
    	if(null === $getParams) $getParams = self::$defGetMsgOpts;

        if( $this->MQClient->inTransaction && !(
                isset($getParams['Options']) &&
                \in_array(MQSERIES_MQGMO_SYNCPOINT, $getParams['Options'], true)
            )
        ) {
            $getParams['Options'][] = MQSERIES_MQGMO_SYNCPOINT;
        }


        $msgHandle = $this->MQClient->createMsgHandle();
        $getParams['MsgHandle'] = $msgHandle;


        $MessageData='';
        $MessageDataLen=0;

        if(isset($getParams['Options']))
            $getParams['Options'] = array_reduce($getParams['Options'], function($res, $cur) { return $res | $cur; }, 0);

        if(isset($getParams['MatchOptions']))
            $getParams['MatchOptions'] = array_reduce($getParams['MatchOptions'], function($res, $cur) { return $res | $cur; }, 0);

        $CC = $RC = null;
        mqseries_get(
            $this->connHandle,
            $this->objHandle,
            $MQMD,
            $getParams,
            $this->bufferLen,
            $MessageData,
            $MessageDataLen,
            $CC,
            $RC);
        if ($CC !== MQSERIES_MQCC_OK) {
            if($RC === 2033) return false; //No more message available
            //TODO Log errors
            throw new RuntimeException(mqseries_strerror($RC), $RC);
        }

        $Message = new MQMessage($MessageData);
        $Message->setPropsArray($MQMD);

        while(false !== ($header = $this->getNextMessageHeader($msgHandle))) {
        	$Message->header($header[0], $header[1]);
		}

		$this->MQClient->deleteMsgHandle($msgHandle);
        return $Message;
    }

    public function inq($selectors)
    {
        if(\is_int($selectors))
            $selectors = [$selectors];

        if(!\is_array($selectors))
            throw new InvalidArgumentException('Selectors must be int or array of ints');

        $selectorsCount = \count($selectors);
        $intAttrCount = $charAttrCount = 0;
        $intAttrMap = $charAttrMap = [];
        foreach($selectors as $cKey => $cSelector) {
            if(\in_array($cSelector, $this->INQCharAttrs, true)) {
                //TODO Также надо определять и сохранять макс длину каждого поля
                $charAttrMap[$charAttrCount] = $cKey;
                $charAttrCount++;
            } else {
                $intAttrMap[$intAttrCount] = $cKey;
                $intAttrCount++;
            }
        }

        $intAttrs = $charAttrs = [];
        $CC = $RC = null;
        mqseries_inq(
            $this->connHandle,
            $this->objHandle,
            $selectorsCount, $selectors,
            $intAttrCount, $intAttrs,
            $charAttrCount*100, $charAttrs,
            $CC,
            $RC);

        if ($CC !== MQSERIES_MQCC_OK) {
            //TODO Log errors
            throw new RuntimeException(mqseries_strerror($RC), $RC);
        }

        if($selectorsCount === 1) {
            if($charAttrCount) return trim($charAttrs);
            return $intAttrs[0];
        }

        $result = [];
        foreach($intAttrs as $cKey => $cVal) {
            $result[$intAttrMap[$cKey]] = $cVal;
        }
        //TODO Заплатка, строковые атрибуты приходят одной строой, которые надо порубить
        //по макс размеру запрашиваемых атрибутов
        if($charAttrCount) {
            $result['charAttrs']=$charAttrs;
        }
        return $result;
    }

	protected function getNextMessageHeader($msgHandle) {
    	//Get prop buf len
		$mqimpo = array(
			'Options' => MQSERIES_MQIMPO_INQ_NEXT | MQSERIES_MQIMPO_QUERY_LENGTH
		);
		$MQPD = [];
		$inqPropName = '%';
		$inqPropType = MQSERIES_MQTYPE_AS_SET;
		$inqPropData = null;
		$inqPropDataLen = 0;

		$CC = $RC = null;
		mqseries_inqmp(
			$this->MQClient->connHandle,
			$msgHandle,
			$mqimpo,
			$inqPropName,
			$MQPD,
			$inqPropType,
			0,
			$inqPropData,
			$inqPropDataLen,
			$CC,
			$RC
		);
		if ($CC !== MQSERIES_MQCC_OK) {
			if($RC === 2471) return false; //No more props available
			//TODO Log errors
			throw new RuntimeException(mqseries_strerror($RC), $RC);
		}

		//Get prop data
		$mqimpo = array(
			'Options' => MQSERIES_MQIMPO_INQ_PROP_UNDER_CURSOR
		);
		$inqPropData = null;
		$CC = $RC = null;
		mqseries_inqmp(
			$this->MQClient->connHandle,
			$msgHandle,
			$mqimpo,
			$inqPropName,
			$MQPD,
			$inqPropType,
			$inqPropDataLen,
			$inqPropData,
			$inqPropDataLen,
			$CC,
			$RC
		);
		if ($CC !== MQSERIES_MQCC_OK) {
			//TODO Log errors
			throw new RuntimeException(mqseries_strerror($RC), $RC);
		}

		return [$inqPropName, $inqPropData];
	}
}