<?php

namespace rstmpw\ibmmq;

use \RuntimeException;
use \InvalidArgumentException;

class MQClient {
    protected static $defQueueOpenOpts = [
        MQSERIES_MQOO_INPUT_AS_Q_DEF,
        MQSERIES_MQOO_FAIL_IF_QUIESCING,
        MQSERIES_MQOO_OUTPUT,
        MQSERIES_MQOO_INQUIRE
    ];
    protected static $defTopicOpts = [
        MQSERIES_MQOO_OUTPUT,
        MQSERIES_MQOO_FAIL_IF_QUIESCING
    ];

    protected static $defCrtMsgHandleOpts = [
		'Version' => MQSERIES_MQCMHO_VERSION_1,
		'Options' => MQSERIES_MQCMHO_VALIDATE
	];

    protected static $defDelMsgHandleOpts = [
		'Version' => MQSERIES_MQDMHO_VERSION_1,
		'Options' => MQSERIES_MQDMHO_NONE
	];

    protected static $defSetMsgPropOpts = [
		'Version' => MQSERIES_MQSMPO_VERSION_1,
		'Options' => MQSERIES_MQSMPO_SET_FIRST
	];

    protected static $defMsgPropDscr = [
		'Version' => MQSERIES_MQPD_VERSION_1,
		'Options' => MQSERIES_MQPD_NONE,
		// 'Support' => MQSERIES_MQPD_SUPPORT_OPTIONAL,
		// 'Context' => MQSERIES_MQPD_NO_CONTEXT,
		// 'CopyOptions' => MQSERIES_MQCOPY_FORWARD
	];

    protected $connHandle;
    protected $connOpts;
    protected $openedObjects = [];
    protected $inTransaction = false;


    public static function makeConnOpts ($QMName, $ServerAddr, $ServerPort='1414', $ChannelName='SYSTEM.ADMIN.SVRCONN', $MaxMsgLength=4194304) :array
    {
      return [
          'QMName'=> $QMName,
          'MQCNO'=> [
              'Version' => MQSERIES_MQCNO_VERSION_2,
              'Options' => MQSERIES_MQCNO_STANDARD_BINDING,
              'MQCD' => [
                  'ChannelName' => $ChannelName,
                  'ConnectionName' => "$ServerAddr($ServerPort)",
                  'TransportType' => MQSERIES_MQXPT_TCP,
				  'MaxMsgLength' => $MaxMsgLength,
				  'DiscInterval' => 0
              ]
          ]
      ];
    }

    public function __construct(array $ConnOpts)
    {
        $CC = $RC = null;
        mqseries_connx(
            $ConnOpts['QMName'],
            $ConnOpts['MQCNO'],
            $this->connHandle,
            $CC,
            $RC
        );
        if ($CC !== MQSERIES_MQCC_OK) {
            //TODO Log errors
            throw new RuntimeException(mqseries_strerror($RC), $RC);
        }
        $this->connOpts=$ConnOpts;
    }

	public function __get($name)
	{
		if(isset($this->{$name})) return $this->{$name};
		return null;
	}

    public function __destruct()
    {
        if($this->inTransaction)
            $this->rollback();

        foreach($this->openedObjects as $cObj) {
            $cObj->close();
        }

        $CC = $RC = null;
        mqseries_disc(
            $this->connHandle,
            $CC,
            $RC
        );

        //TODO Log warning if MQCC != OK
    }

    public function openQueue(string $QName, array $OpenOpts=null) :MQObject {
        if(null === $OpenOpts) $OpenOpts = self::$defQueueOpenOpts;

        $openParams = [
            'MQOD'=> [
                'ObjectQMgrName' => $this->connOpts['QMName'],
                'ObjectName' => $QName
            ],
            'Options'=> $OpenOpts
        ];

        $obj = new MQObject($this, $openParams);
        $this->openedObjects[] = $obj;
        return $obj;
    }

    public function openTopic($TopicName, array $OpenOpts=null):MQObject {
        if(null === $OpenOpts) $OpenOpts = self::$defTopicOpts;

        $openParams = [
            'MQOD'=> [
                'Version' => MQSERIES_MQOD_VERSION_4,
                'ObjectType' => MQSERIES_MQOT_TOPIC,
                'ObjectString' => $TopicName
            ],
            'Options'=> $OpenOpts
        ];

        $obj = new MQObject($this, $openParams);
        $this->openedObjects[] = $obj;
        return $obj;
    }

    // To put message to topic use 'TOPIC::Any/Topic/String' for objName params
    public function put1(MQMessage $MQMessage, $objName, array $putParams=[]) {
        $openParams = [
            'MQOD'=> [],
            'Options'=> self::$defTopicOpts
        ];

        if(0 === strpos($objName, 'TOPIC::')) { //pub to topic
            $openParams['MQOD'] = [
                    'Version' => MQSERIES_MQOD_VERSION_4,
                    'ObjectType' => MQSERIES_MQOT_TOPIC,
                    'ObjectString' => substr($objName, 7)
            ];
        } else { //put to queue
            $openParams['MQOD'] = [
                    'ObjectQMgrName' => $this->connOpts['QMName'],
                    'ObjectName' => $objName
            ];
        }

        if( $MQMessage->property('MsgId') === false && !(
                isset($putParams['Options']) &&
                \in_array(MQSERIES_MQPMO_NEW_MSG_ID, $putParams['Options'], true)
            )
        )
            $putParams['Options'][] = MQSERIES_MQPMO_NEW_MSG_ID;

        if( $this->inTransaction && !(
                isset($putParams['Options']) &&
                \in_array(MQSERIES_MQGMO_SYNCPOINT, $putParams['Options'], true)
            )
        ) {
            $putParams['Options'][] = MQSERIES_MQGMO_SYNCPOINT;
        }

		$msgHandle = null;
		if($MQMessage->hasHeaders()) {
			$msgHandle = $this->createMsgHandle();
			foreach($MQMessage->getAllHeaders() as $HName => $HValue) {
				$this->setMsgProp($msgHandle, $HName, $HValue);
			}

			if(!isset($putParams['Version']) || $putParams['Version'] < MQSERIES_MQPMO_VERSION_3)
				$putParams['Version'] = MQSERIES_MQPMO_VERSION_3;

			$putParams['NewMsgHandle'] = $msgHandle;
			$putParams['Action'] = MQSERIES_MQACTP_NEW;
		}


        $MQMD = $MQMessage->getAllProps();
        $putParams['Options'] = array_reduce($putParams['Options'], function($res, $cur) { return $res | $cur; }, 0);

        $CC = $RC = null;
        mqseries_put1(
            $this->connHandle,
            $openParams['MQOD'],
            $MQMD,
            $putParams,
            $MQMessage->data(),
            $CC,
            $RC);

        if ($CC !== MQSERIES_MQCC_OK) {
            //TODO Log errors
            throw new RuntimeException(mqseries_strerror($RC), $RC);
        }

		if(null !== $msgHandle) $this->deleteMsgHandle($msgHandle);

		$MQMessage->setPropsArray($MQMD);
        return $this;
    }

    public function begin() {
        if($this->inTransaction)
            throw new RuntimeException ('Try to open nested transaction - not supported');
        $this->inTransaction = true;
    }

    public function commit() {
        if(!$this->inTransaction)
            throw new RuntimeException ('Try to commit not opened transaction');

        $CC = $RC = null;
        mqseries_cmit(
            $this->connHandle,
            $CC,
            $RC
        );
        if ($CC !== MQSERIES_MQCC_OK) {
            $this->rollback();
            //TODO Log errors
            throw new RuntimeException(mqseries_strerror($RC), $RC);
        }
        $this->inTransaction = false;
    }

    public function rollback() {
        if(!$this->inTransaction)
            throw new RuntimeException ('Try to rollback not opened transaction');

        $CC = $RC = null;
        mqseries_back(
            $this->connHandle,
            $CC,
            $RC
        );
        if ($CC !== MQSERIES_MQCC_OK) {
            //TODO Log errors
            throw new RuntimeException(mqseries_strerror($RC), $RC);
        }
        $this->inTransaction = false;
    }


	public function createMsgHandle(array $mqcmho = null) {
		if(null === $mqcmho) $mqcmho = self::$defCrtMsgHandleOpts;

		$msgHandle = $CC = $RC = null;
		mqseries_crtmh(
			$this->connHandle,
			$mqcmho,
			$msgHandle,
			$CC,
			$RC
		);
		if ($CC !== MQSERIES_MQCC_OK) {
			//TODO Log errors
			throw new RuntimeException(mqseries_strerror($RC), $RC);
		}
		return $msgHandle;
	}

	public function deleteMsgHandle($msgHandle, array $mqdmho = null) {
		if(null === $mqdmho) $mqdmho = self::$defDelMsgHandleOpts;

		$CC = $RC = null;
		mqseries_dltmh(
			$this->connHandle,
			$msgHandle,
			$mqdmho,
			$CC,
			$RC
		);
		if ($CC !== MQSERIES_MQCC_OK) {
			//TODO Log errors
			throw new RuntimeException(mqseries_strerror($RC), $RC);
		}
    }

    public function setMsgProp($msgHandle, string $name, $value, array $mqpd = null, array $mqsmpo = null) {
		if(null === $mqsmpo) $mqsmpo = self::$defSetMsgPropOpts;
		if(null === $mqpd) $mqpd = self::$defMsgPropDscr;

		switch(\gettype($value)) {
			case 'string':
				$valType = MQSERIES_MQTYPE_STRING;
				break;
			case 'integer':
				$valType = MQSERIES_MQTYPE_INT32;
				break;
			case 'boolean':
				$valType = MQSERIES_MQTYPE_BOOLEAN;
				break;
			case 'double':
				$valType = MQSERIES_MQTYPE_FLOAT32;
				break;
			default:
				throw new InvalidArgumentException('Value of message header must be a scalar type');
		}


		$CC = $RC = null;
		mqseries_setmp(
			$this->connHandle,
			$msgHandle,
			$mqsmpo,
			$name,
			$mqpd,
			$valType,
			$value,
			$CC,
			$RC
		);
		if ($CC !== MQSERIES_MQCC_OK) {
			//TODO Log errors
			throw new RuntimeException(mqseries_strerror($RC), $RC);
		}
	}

}