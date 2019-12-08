<?php
require_once('config/config.inc.php');

define('REDIRECT_URI', SERVER_URL.'client.php');
define('RESOURCE_URL', SERVER_URL.'resource.php');
 
define('CLIENT_ID', 'testclient');
define('CLIENT_SECRET', 'testpass');
 
session_start();

require_once "page_header.inc.php";

function getUserInfo(){
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
 
 
$userInfo = getUserInfo();
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
    $response_txt = file_get_contents(RESOURCE_URL.'?client_id='.CLIENT_ID.'&state=test&access_token='.$_SESSION['access_token']);
    //echo 'response_txt=',$response_txt,"<br>\n";
    $response = json_decode($response_txt, true);
 
    $userInfo = array(
        'client_user_odin_uri'=>$response['userInfo']['user_odin_uri'],
        'client_user_nickname'=>$response['userInfo']['username'],
        'client_avatar'=>$response['userInfo']['avatar'],
    );
    $_SESSION = array_merge($_SESSION, $userInfo);
 
}
 
// 步骤1，点击此链接跳转到开放认证服务
$auth_url = SERVER_URL.'authorize.php?response_type=code&client_id='.CLIENT_ID.'&state=test&redirect_uri='. urlencode(REDIRECT_URI);
 
if($userInfo){
    echo '<center><h3>欢迎 ',$userInfo['client_user_odin_uri'],' </h3><br><p>昵称： ',$userInfo['client_user_nickname'],'<br><img src="',$userInfo['client_avatar'],'" alt="" /></p><br>
    <a href="?logout=1">退出登录</a></center>';
}else{
    echo '<center><h3>以奥丁号登录的简单示例(PHP)</h3>';
    echo "<button class='btn btn-success' onclick=\"location.href='",$auth_url,"';\">使用奥丁号(ODIN)登录</button></center>";
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
