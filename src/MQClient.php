<?php

namespace rstmpw\ibmmq;

use \RuntimeException;

class MQClient {
    private static $defQueueOpenOpts = [
        MQSERIES_MQOO_INPUT_AS_Q_DEF,
        MQSERIES_MQOO_FAIL_IF_QUIESCING,
        MQSERIES_MQOO_OUTPUT,
        MQSERIES_MQOO_INQUIRE
    ];
    private static $defTopicOpts = [
        MQSERIES_MQOO_OUTPUT,
        MQSERIES_MQOO_FAIL_IF_QUIESCING
    ];

    private $connHandle = null;
    private $connOpts = null;
    private $openedObjects = [];
    private $inTransaction = false;

    public function __get($name)
    {
        if(isset($this->{$name})) return $this->{$name};
        return null;
    }

    public static function makeConnOpts ($QMName, $ServerAddr, $ServerPort='1414', $ChannelName='SYSTEM.ADMIN.SVRCONN')
    {
      return [
          'QMName'=> $QMName,
          'MQCNO'=> [
              'Version' => MQSERIES_MQCNO_VERSION_2,
              'Options' => MQSERIES_MQCNO_STANDARD_BINDING,
              'MQCD' => [
                  'ChannelName' => $ChannelName,
                  'ConnectionName' => "$ServerAddr($ServerPort)",
                  'TransportType' => MQSERIES_MQXPT_TCP
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

    public function openQueue($QName, array $OpenOpts=null) {
        if(!$OpenOpts) $OpenOpts = self::$defQueueOpenOpts;

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

    public function openTopic($TopicName, array $OpenOpts=null) {
        if(!$OpenOpts) $OpenOpts = self::$defTopicOpts;

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

        if(substr($objName, 0, 7) === 'TOPIC::') { //pub to topic
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
                array_search(MQSERIES_MQPMO_NEW_MSG_ID, $putParams['Options'])
            )
        )
            $putParams['Options'][] = MQSERIES_MQPMO_NEW_MSG_ID;

        if( $this->inTransaction && !(
                isset($putParams['Options']) &&
                array_search(MQSERIES_MQGMO_SYNCPOINT, $putParams['Options'])
            )
        ) {
            $putParams['Options'][] = MQSERIES_MQGMO_SYNCPOINT;
        }

        $MQMD = $MQMessage->getPropsArray();
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
        //TODO Log warning if MQCC != OK
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
        //TODO Log warning if MQCC != OK
        $this->inTransaction = false;
    }
}