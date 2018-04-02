<?php

namespace rstmpw\ibmmq;

class MQMessage
{
    protected $messageData;
    protected $MQMD = [
        'Version' => MQSERIES_MQMD_VERSION_1,
        'Expiry' => MQSERIES_MQEI_UNLIMITED,
        'Report' => MQSERIES_MQRO_NONE,
        'MsgType' => MQSERIES_MQMT_DATAGRAM,
        'Format' => MQSERIES_MQFMT_NONE,
        'Priority' => 0,
        'Persistence' => MQSERIES_MQPER_PERSISTENT,
        'MsgId' => false
    ];
	protected $RFHeaders = [];

    protected $propetiesList = [
        //TODO Describe all properties and MD versions
    ];

    public function __construct($data)
    {
        $this->messageData=$data;
    }

    public function __destruct() {
		$this->messageData = null;
		$this->MQMD = null;
		$this->RFHeaders = null;
	}

	public function data()
    {
        return $this->messageData;
    }

    // https://www.ibm.com/support/knowledgecenter/SSFKSJ_7.5.0/com.ibm.mq.ref.dev.doc/q097390_.htm
    public function property($name, $value=null)
    {
        if(null === $value) {
			if(!isset($this->MQMD[$name])) return null;
            if(
                \is_resource($this->MQMD[$name])
                && get_resource_type($this->MQMD[$name]) === 'mqseries_bytes'
            ) return mqseries_bytes_val($this->MQMD[$name]);

            return $this->MQMD[$name];
        }
        $this->setPropsArray([$name => $value]);
        return $value;
    }

    public function setPropsArray(array $props)
    {
        foreach($props as $pName => $pVal) {
            //TODO Check property name in $this->propetiesList and adjust MD version
            //TODO Если устанавливаемое свойство относится к более выской версии MQMD, то надо автоматически поднимать версию
            $this->MQMD[$pName]=$pVal;
        }
    }

    public function getAllProps(): array {
		return $this->MQMD;
	}

    /** @deprecated  */
    public function getPropsArray(): array {
        return $this->getAllProps();
    }

    public function header($name, $value=null) {
		if(null === $value) {
			if(!isset($this->RFHeaders[$name])) return null;
			return $this->RFHeaders[$name];
		}

		#array_filter();
		$this->RFHeaders[$name] = $value;
		return $value;
	}

	public function getAllHeaders(): array {
    	return $this->RFHeaders;
	}

	public function hasHeaders(): bool {
    	if(\count($this->RFHeaders)) return true;
    	return false;
	}
}