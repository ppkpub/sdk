<!doctype html>
<html lang="UTF-8">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>ODIN登录示例</title>
</head>
<body>

<?php
//echo phpinfo();
//ini_set("display_errors", "On"); 
//error_reporting(E_ALL | E_STRICT);

define('SERVER_URL', 'https://tool2.ppkpub.org/oauth/');
define('REDIRECT_URI', 'https://tool2.ppkpub.org/oauth/client.php');
define('RESOURCE_URL', SERVER_URL.'resource.php');
 
define('CLIENT_ID', 'testclient');
define('CLIENT_SECRET', 'testpass');
 
session_start();
function userInfo(){
    if(isset($_SESSION['client_user_odin_uri'])) {
        return $_SESSION;
    } else {
        return false;
    }
}
 
 
if(isset($_REQUEST['logout'])) {
    unset($_SESSION['client_user_odin_uri']);
    session_destroy();
}
 
 
$userInfo = userInfo();
/*
 * 接收用户中心返回的授权码
 */
if (isset($_REQUEST['code']) && $_SERVER['REQUEST_URI']) {
    //将认证服务器返回的授权码从 URL 中解析出来
    $code = substr($_SERVER['REQUEST_URI'], strpos($_SERVER['REQUEST_URI'], 'code=')+5, 40);
 
    // 拿授权码去申请令牌
    $url = SERVER_URL.'token.php';
    $data = array(
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'redirect_uri' => REDIRECT_URI
    );
    
    //自定义的处理判断
    $response_txt = cUrlHttpPost($url, $data);
    //echo 'response_txt=',$response_txt,"<br>\n";
    
    $response = json_decode($response_txt, true);
 
    // 将令牌缓存到 SESSION中，方便后续访问
    $_SESSION['access_token'] = $response['access_token'];
 
    // 步骤6 使用令牌获取用户信息
    $response_text = file_get_contents(RESOURCE_URL.'?client_id='.CLIENT_ID.'&state='.CLIENT_SECRET.'&access_token='.$_SESSION['access_token']);
    $response = json_decode($response_text, true);
 
    $userInfo = array(
        'client_user_odin_uri'=>$response['userInfo']['user_odin_uri'],
        'client_user_nickname'=>$response['userInfo']['username'],
        'client_avatar'=>$response['userInfo']['avatar'],
    ); ;
    $_SESSION = array_merge($_SESSION, $userInfo);
 
}
 
// 步骤1，点击此链接跳转到开放认证服务
$auth_url = SERVER_URL.'authorize.php?response_type=code&client_id='.CLIENT_ID.'&state='.CLIENT_ID.'&redirect_uri='. REDIRECT_URI;
 
if($userInfo){
    echo '欢迎 ',$userInfo['client_user_odin_uri'],' 头像 <img src="',$userInfo['client_avatar'],'" alt="" />
    <a href="?logout=1">退出登录</a>';
}else{
    echo '<center><h3>以奥丁号登录的简单示例</h3>';
    echo "<button onclick=\"location.href='",$auth_url,"';\">使用奥丁号(ODIN)登录</button></center>";
}
?>

</body>
</html>

<?php
function cUrlHttpPost($url, $post_data){
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // post数据
    curl_setopt($ch, CURLOPT_POST, 1);
    // post的变量
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

    $output = curl_exec($ch);
    curl_close($ch);

    //打印获得的数据
    //print_r($output);
    return $output;
}
