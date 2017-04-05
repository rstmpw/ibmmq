<?php

namespace rstmpw\ibmmq;

use \RuntimeException;
use \InvalidArgumentException;


class MQObject {
    private $MQClient=null;
    private $connHandle=null;
    private $objHandle=null;
    private $openParams=null;
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
        $this->openParams = $openParams;

        if($openParams['MQOD']['ObjectType'] == MQSERIES_MQOT_Q) {
            if ($bufferLen)
                $this->bufferLen = $bufferLen;
            elseif (array_search(MQSERIES_MQOO_INQUIRE, $openParams['Options']))
                $this->bufferLen=$this->inq(MQSERIES_MQIA_MAX_MSG_LENGTH);
        }
    }

    public function __destruct()
    {
       $this->close();
    }

    public function close() {
        if(is_null($this->objHandle)) return;

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
        if( $MQMessage->property('MsgId') === false && !(
                isset($putParams['Options']) &&
                array_search(MQSERIES_MQPMO_NEW_MSG_ID, $putParams['Options'])
            )
        )
            $putParams['Options'][] = MQSERIES_MQPMO_NEW_MSG_ID;

        if( $this->MQClient->inTransaction && !(
                isset($putParams['Options']) &&
                array_search(MQSERIES_MQGMO_SYNCPOINT, $putParams['Options'])
            )
        ) {
            $putParams['Options'][] = MQSERIES_MQGMO_SYNCPOINT;
        }

        $MQMD = $MQMessage->getPropsArray();
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

        $MQMessage->setPropsArray($MQMD);
        return $this;
    }

    public function get(array $getParams=['Options' => [MQSERIES_MQGMO_FAIL_IF_QUIESCING, MQSERIES_MQGMO_NO_WAIT]], $MQMD=[])
    {
        if( $this->MQClient->inTransaction && !(
                isset($getParams['Options']) &&
                array_search(MQSERIES_MQGMO_SYNCPOINT, $getParams['Options'])
            )
        ) {
            $getParams['Options'][] = MQSERIES_MQGMO_SYNCPOINT;
        }

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
            if($RC == 2033) return false; //No more message available
            //TODO Log errors
            throw new RuntimeException(mqseries_strerror($RC), $RC);
        }

        $Message = new MQMessage($MessageData);
        $Message->setPropsArray($MQMD);
        return $Message;
    }

    public function inq($selectors)
    {
        if(is_int($selectors))
            $selectors = [$selectors];

        if(!is_array($selectors))
            throw new InvalidArgumentException('Selectors must be int or array of ints');

        $selectorsCount = count($selectors);
        $intAttrCount = $charAttrCount = 0;
        $intAttrMap = $charAttrMap = [];
        foreach($selectors as $cKey => $cSelector) {
            if(in_array($cSelector, $this->INQCharAttrs)) {
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

        if($selectorsCount == 1) {
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
}