<?php
/**
 * 众安开放平台api接口调用Demo-PHP版
 *
 * *************************************************************************************
 * 系统要求：
 * 1.PHP版本 >= 5.2.0, PHP7 测试通过
 * 2.开启curl扩展
 * 3.开启openssl扩展
 * 4.时区设置为东八区(Asia/Shanghai)
 * *************************************************************************************
 *
 * *************************************************************************************
 * 准备工作：
 * 1.找开放平台接口对接人提供对应环境的网关地址(gateUrl),appKey并交换rsa公钥 (partnerPublicKey)
 * 2.如出现无权访问的情况，则需开通相关api的访问权限
 * *************************************************************************************
 *
 * @link      https://www.zhongan.com
 * @copyright Copyright (c) 2013 众安保险
 */

require_once 'classes/RequestHandler.class.php';

//如果报timestamp相关的错 需设置时区参数为Asia/Shanghai(东八区)
date_default_timezone_set('Asia/Shanghai');

try {
    //初始化request，传入环境参数
    //RequestHandler::ENV_TEST | RequestHandler::ENV_UAT | RequestHandler::ENV_PROD
    $request = new RequestHandler(RequestHandler::ENV_TEST);

    //可自行设定版本参数, 未设置时默认为1.0.0
    $request->setVersion('1.0.0');

    //组装请求业务参数，具体参数请查看对应的api文档
    $params = array(
        'identityNo' => '410482198209279874',
        'userName' => '张三'
    );
    //获取请求结果，第一个参数为开放平台api的serviceName，第二个字段为开放平台api的业务级输入参数
    //如果$res含有 errorCode和errorMsg字段，则说明该请求出现错误，需视情况作出处理
    $res = $request->doRequest('zhongan.user.person.addByIdentityNo', $params);
    print_r($res);

    //如果请求有错误，可以获取debug信息追踪错误 (仅限test和uat环境， prod环境默认不添加debug信息)
    $debugInfo = $request->getDebugInfo();
    print_r($debugInfo);

    //如果通过doRequest获取的请求结果不符合预期，可自行获取返回的原始业务参数进行后续处理
    $rawBizContent = $request->getRawBizContent();
    print_r($rawBizContent);

} catch (Exception $e) {
    //可以在这里添加你的异常处理逻辑
    print_r($e->getMessage());
}