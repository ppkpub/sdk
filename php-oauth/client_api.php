<?php
/*
配合HTML前端以AJAX方式完成OAuth验证的后端API示例
*/
require_once('config/config.inc.php');

define('RESOURCE_URL', SERVER_URL.'resource.php');
 
define('CLIENT_ID', 'testclient');
define('CLIENT_SECRET', 'testpass');

$array_result=array(
    'status' => 'ERROR',
    'errorCode' => 'UNKNOWN',
);

/*
 * 接收前端页面提供的待确认OAuth授权码等参数
 */
$code = $_GET['code'];
$redirect_uri = $_GET['redirect_uri'];
if (isset($code) && isset($redirect_uri)) {
    // 拿授权码去申请令牌
    $url = SERVER_URL.'token.php';
    $data = array(
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'redirect_uri' => $redirect_uri
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
 
    if(isset($response['userInfo']['user_odin_uri'])){
        $userInfo = array(
            'client_user_odin_uri'=>$response['userInfo']['user_odin_uri'],
            'client_user_nickname'=>$response['userInfo']['username'],
            'client_avatar'=>$response['userInfo']['avatar'],
        );
        
        $array_result=array(
            'status' => 'OK',
            'user_info' => $userInfo,
        );
    }else{
        $array_result=array(
            'status' => 'ERROR',
            'errorCode' => 'FailedToGetUserResource',
        );    
    }
    
    
}

header('Content-type: application/json;charset=UTF-8');
echo json_encode($array_result);

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
