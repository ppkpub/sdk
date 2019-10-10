<?php
/**
 * Author: shanhuhai
 * Date: 09/11/2017 14:46
 * Mail: 441358019@qq.com
 */

// 步骤5 ，认证服务器发放令牌

require_once __DIR__ . '/common.php';
$server->handleTokenRequest(OAuth2\Request::createFromGlobals())->send();

