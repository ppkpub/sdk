<?php
require_once __DIR__."/common.php";

$_SESSION['authorize_querystring'] = $_SERVER['QUERY_STRING'];

// 判断如果没有登录则跳转到登录界面
if(!isset($g_logonUserInfo) && strpos($_SERVER['REQUEST_URI'], 'login.php') === false) {
    header('Location: '.SERVER_URL.'login.php');
    exit;
}

$request = OAuth2\Request::createFromGlobals();

$response = new \OAuth2\Response();

if(!$server->validateAuthorizeRequest($request, $response)) {

    $response->send();
    die;
}

if(empty($_POST)) {
    require_once "page_header.inc.php";
    
    echo '<br><br><center><form method="post">
  <label>当前奥丁号是 <font color="#FF7026">',$g_currentUserODIN,'</font><br> 是否确认授权登录下述应用?</label><br />
  <font color="#FF7026">',$_GET['redirect_uri'],'</font><br /><br />
  <input type="submit" class="btn btn-primary"  name="authorized" value=" 　是　 ">　　　
  <input type="submit" class="btn btn-warning"  name="notauthorized" value=" 　否　 ">
</form>  
    <a href="login.php?action=relogin">换一个奥丁号</a>
    </center>
';
}else{
    // print the authorization code if the user has authorized your client
    $is_authorized = (strlen($_POST['authorized'])>0);
    $server->handleAuthorizeRequest($request, $response, $is_authorized,$g_currentUserODIN);

    $response->send();
}