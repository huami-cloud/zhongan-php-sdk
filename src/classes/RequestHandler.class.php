<?php
/**
 * @link      https://www.zhongan.com
 * @copyright Copyright (c) 2013 众安保险
 */
require_once 'RSA.class.php';
require_once 'HttpCilent.class.php';
require_once 'ZaException.class.php';
/**
 * RequestHandler 众安开放平台请求处理类
 */
class RequestHandler
{
    /**
     * 环境常量 test 测试环境 uat 预发布环境 prod 生产环境
     */
    const ENV_TEST = 'test';
    const ENV_UAT = 'uat';
    const ENV_PROD = 'prod';
    /**
     * 参数未设置标识符 uat和prod配置默认为未设置，请联系众安开放平台相关人员索取对应配置信息
     */
    const NOT_SET_YET = 'not_set_yet';
    /**
     * 返回数据验签失败错误码，和其他错误码有冲突时可自行修改该参数值
     */
    const SIGN_INVALID = 403001;
    /**
     * 返回数据bizContent非法(无bizContent或为空串)的错误码，和其他错误码有冲突时可自行修改该参数值
     */
    const BIZCONTENT_INVALID = 403002;
    /**
     * @var array 环境参数配置
     */
    private static $_config = array(
        self::ENV_TEST => array(
            'gateUrl' => 'http://120.27.164.86:8080/Gateway.do',
            'appKey' => '3ee877531b114794eb18bbcff859c482',
            'partnerPublicKey' => '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDIgHnOn7LLILlKETd6BFRJ0Gqg
S2Y3mn1wMQmyh9zEyWlz5p1zrahRahbXAfCfSqshSNfqOmAQzSHRVjCqjsAw1jyq
rXaPdKBmr90DIpIxmIyKXv4GGAkPyJ/6FTFY99uhpiq0qadD/uSzQsefWo0aTvP/
65zi3eof7TcZ32oWpwIDAQAB
-----END PUBLIC KEY-----',
            'ownPrivateKey' => '-----BEGIN RSA PRIVATE KEY-----
MIICXwIBAAKBgQD23llSmXxQlEZSRyQID9sJXIqX4XNjoeoU2GWlc+B2XqwBvqL1
Jg9bGuJl//Miw9JyrE9SnOBfxGaBIz80RqD7GDuc+a7M8OsZYELZJ5SuhwcdBozb
xMj63E0QxhGdeeSfDKDY9QatZ54dkPM57l4uNrLB9/fI2y3AgKreIeJ4ZQIDAQAB
AoGBALY21i1OluCPIPyX//NnaKAXS0Dhqp7uou2x8AzYY+Ra6pD7GiLibdEsHdF1
wwt1CH+VyZLLsh1dxN8qmftG6ogYaUxqajem8zbilWev0R0eViPbsEVssm3V7i6b
OygPFCMIsgD2EbP8zXOZ1DIZTX6rnbLrR62vLAofSR+/PA/dAkEA/15RIG3k8yEC
Fct0KB2U5XSwVEc4N75Dbdk5//ct0HREFHpwGf7bnn6wqo9o54RlJlp3JVTqMs+C
HymlJDZ1AwJBAPd6poLQAzFDG9k6OyVdIbBUoF7S5IH3TqN25nqbGqbhuBo+A8C6
5EIQMpQOB7rMbZJRz9DsdUZU2TPlLSfXXHcCQQDV5rvPjR18ZYaomN24CGdC98YH
Igy97HnwlkcV14ahl/G6sYAa1jZBgV8bzroRSv2q7ZXlSEZPvy8ASVLRjWffAkEA
vt7X4fhxHeN2bRoeV/j2bLs4XSoml56YBjdEF7fc3G0mwwalelYqilFX0RzpFUdq
EvoKYEafRLlYNFBDfYD6jQJBANS/jvYBZPcaQUR8Gs69jp+Wr6hOm6pk6BF0hz/h
Y6BbAVoysred7QzIOH6flqaESf8jM6Cxh9u/FdGD15/mPKY=
-----END RSA PRIVATE KEY-----',
        ),
        //uat环境配置，在发布前务必找开放平台接口对接人配置好相关参数
        self::ENV_UAT => array(
            'gateUrl' => self::NOT_SET_YET,
            'appKey' => self::NOT_SET_YET,
            'partnerPublicKey' => self::NOT_SET_YET,
            'ownPrivateKey' => self::NOT_SET_YET,
        ),
        //prod环境配置，在发布前务必找开放平台接口对接人配置好相关参数
        self::ENV_PROD => array(
            'gateUrl' => self::NOT_SET_YET,
            'appKey' => self::NOT_SET_YET,
            'partnerPublicKey' => self::NOT_SET_YET,
            'ownPrivateKey' => self::NOT_SET_YET,
        )
    );
    /**
     * @var string 当前环境
     */
    private $_env;
    /**
     * @var array 当前环境相关配置
     */
    private $_envConfig;
    /**
     * @var string 接口版本，默认为1.0.0
     */
    private $_version = '1.0.0';
    /**
     * @var RSA rsa示例
     */
    private static $_rsa = null;
    /**
     * @var array debug信息
     */
    private $_debugInfo = array();
    /**
     * @var array 错误码对应数组 第一个元素始终为0， 处理errorCode为0的情况
     */
    private $_errorCodeMap = array(0);
    /**
     * @var string 返回的原始业务参数字符串(已解密，对于部分返回数据不规范的接口，可以自行获取该字符串做对应的业务处理)
     */
    protected $_rawBizContent = '';

    /**
     * @param string $env 环境参数，实例化时需传入此参数
     * @throws Exception
     */
    public function __construct($env)
    {
        if (!isset(self::$_config[$env])) {
            throw new Exception(
                sprintf(
                    '环境必须为(%s)中的一个，请修改！',
                    implode('|', array_keys(self::$_config))
                )
            );
        }
        $this->_env       = $env;
        $this->_envConfig = self::$_config[$env];
        $this->_addDebugInfo(array($this->_env => $this->_envConfig));
        foreach (array('gateUrl', 'appKey', 'partnerPublicKey', 'ownPrivateKey') as $key) {
            if (
                !isset($this->_envConfig[$key])
                || !strlen($this->_envConfig[$key])
                || $this->_envConfig[$key] === self::NOT_SET_YET
            ) {
                throw new Exception("请先配置环境参数[{$key}]");
            }
        }
    }

    /**
     * 设置version参数，对于同一个环境可能有多个version切换的情况，可通过此方法设置version
     * @param string $version 接口版本
     * @return $this
     */
    public function setVersion($version)
    {
        $this->_version = $version;
        return $this;
    }

    /**
     * 发起请求
     * @param string $serviceName 接口serviceName
     * @param string $bizParams   业务级参数数组
     * @return mixed
     * @throws Exception
     */
    public function doRequest($serviceName, $bizParams)
    {
        try {
            $requestParams = array(
                'serviceName' => $serviceName,
                'appKey'      => $this->_envConfig['appKey'],
                'format'      => 'json',
                'signType'    => 'RSA',
                'charset'     => 'UTF-8',
                'version'     => $this->_version,
                'timestamp'   => date('YmdHis'). mt_rand(100, 999),
                'bizContent'  => $this->_buildBizContent($bizParams),
            );
            $requestParams['sign'] = $this->_getRsa()->sign($requestParams);
            $http = new HttpClient();
            $this->_addDebugInfo(array('requestParams' => $requestParams));
            $res = $http->post($this->_envConfig['gateUrl'], $requestParams);
            $this->_addDebugInfo(array('response' => $res));
            $data = json_decode($res['data'], true);
            $this->_addDebugInfo(array('data' => $data));
            if (isset($data['sign']) && !$this->_verifySign($data)) { //如果返回数据验签失败
                $this->_addDebugInfo(array('error' => '返回数据验签失败'));
                throw new Exception('返回数据验签失败！', self::SIGN_INVALID);
            }
            $this->_checkError($data); //检查系统级别的错误
            if (isset($data['bizContent']) && strlen($data['bizContent'])) { //如果返回数据包含bizContent且不为空串，则解密
                $this->_rawBizContent = $this->_getRsa()->decrypt($data['bizContent']);
                $bizContent = json_decode($this->_rawBizContent, true);
                $this->_addDebugInfo(array('bizContent' => $bizContent));
                if (isset($bizContent['resultJson'])) {
                    $bizContent = json_decode($bizContent['resultJson'], true);
                }
                $this->_checkError($bizContent); //检查业务级别的错误
                return $bizContent;
            } else {
                throw new Exception('返回bizContent为空或不存在!', self::BIZCONTENT_INVALID);
            }
        } catch (Exception $e) {
            $errorCode = $e instanceof ZaException ? $this->_decodeErrorCode($e->getCode()) : $e->getCode();
            return array(
                'errorCode' => $errorCode,
                'errorMsg' => $e->getMessage()
            );
        }
    }

    /**
     * 组装业务参数
     * @param array $bizParams 业务参数数组
     * @return string
     */
    private function _buildBizContent($bizParams)
    {
        if (is_array($bizParams) && empty($bizParams)) {
            $bizParams = '{}'; //参数为空的时候，bizContent必须为空map {}而不是空list []
        }
        return $this->_getRsa()->encrypt($bizParams);
    }

    /**
     * 获取rsa实例
     * @return null|RSA
     */
    private function _getRsa()
    {
        if (!self::$_rsa instanceof RSA) {
            self::$_rsa = new RSA(
                $this->_envConfig['ownPrivateKey'],
                $this->_envConfig['partnerPublicKey']
            );
        }
        return self::$_rsa;
    }

    /**
     * 返回数据验签
     * @param array $data 返回数据数组
     * @return bool
     */
    private function _verifySign($data)
    {
        $sign = $data['sign'];
        unset($data['sign']);
        return $this->_getRsa()->verify($data, $sign);
    }

    /**
     * 添加debug信息
     * @param mixed $info debug信息
     */
    private function _addDebugInfo($info)
    {
        if ($this->_env !== self::ENV_PROD) { //生产环境不添加debug信息
            array_push($this->_debugInfo, $info);
        }
    }

    /**
     * 获取debug信息
     * @return array
     */
    public function getDebugInfo()
    {
        return $this->_debugInfo;
    }

    /**
     * 检查返回数据中的错误信息
     * @param array $data 返回数据数组
     * @throws Exception
     */
    protected function _checkError($data)
    {
        if (isset($data['errorMsg']) || isset($data['errorCode']) || isset($data['errorMessage'])) {
            //对errorMsg为空的情况，统一errorMsg为未知错误
            if (isset($data['errorMsg'])) {
                $errorMsg = $data['errorMsg'];
            } elseif (isset($data['errorMessage'])) {
                $errorMsg = $data['errorMessage'];
            } else {
                $errorMsg = 'unknown error';
            }
            //接口返回errorCode统一转为整数 如未设置则默认errorCode为0
            $errorCode = isset($data['errorCode']) ? $data['errorCode'] : 0;
            throw new ZaException($errorMsg, $this->_encodeErrorCode($errorCode));
        }
    }

    /**
     * 编码errorCode，对于非int型的errorCode，抛出异常时需先编码成int型
     * @param mixed $errorCode 需编码的errorCode
     * @return int
     */
    protected function _encodeErrorCode($errorCode)
    {
        return array_push($this->_errorCodeMap, $errorCode) - 1;
    }

    /**
     * 解码errorCode
     * @param int $errorCode 需解码的errorCode $e->getCode()的结果
     * @return mixed
     */
    protected function _decodeErrorCode($errorCode)
    {
        return $this->_errorCodeMap[$errorCode];
    }

    /**
     * 获取原始的返回业务参数字符串，对于部分返回数据不规范的接口，doRequest()的处理无法满足要求，则需要通过获取原始返回数据做对应的处理
     * @return string
     */
    public function getRawBizContent()
    {
        return $this->_rawBizContent;
    }
}