<?php

namespace rstmpw\ibmmq;

class MQMessage
{
    private $messageData=null;
    private $MQMD = [
        'Version' => MQSERIES_MQMD_VERSION_1,
        'Expiry' => MQSERIES_MQEI_UNLIMITED,
        'Report' => MQSERIES_MQRO_NONE,
        'MsgType' => MQSERIES_MQMT_DATAGRAM,
        'Format' => MQSERIES_MQFMT_STRING,
        'Priority' => 0,
        'Persistence' => MQSERIES_MQPER_PERSISTENT,
        'MsgId' => false
    ];

    public function __construct($data)
    {
        $this->messageData=$data;
    }

    public function data()
    {
        return $this->messageData;
    }

    // https://www.ibm.com/support/knowledgecenter/SSFKSJ_7.5.0/com.ibm.mq.ref.dev.doc/q097390_.htm
    public function property($name, $value=null)
    {
        if(is_null($value)) {
            if(!isset($this->MQMD[$name])) return false;

            if(
                gettype($this->MQMD[$name]) === 'resource'
                && get_resource_type($this->MQMD[$name]) == 'mqseries_bytes'
            ) return mqseries_bytes_val($this->MQMD[$name]);

            return $this->MQMD[$name];
        }
        $this->setRawProps([$name => $value]);
    }

    public function setRawProps(array $props)
    {
        foreach($props as $pName => $pVal) {
            //TODO Если устанавливаемое свойство относится к более выской версии MQMD, то надо автоматически поднимать версию
            $this->MQMD[$pName]=$pVal;
        }
    }

    public function getRawProps() {
        return $this->MQMD;
    }
}