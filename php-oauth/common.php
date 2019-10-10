<?php
// error reporting (this is a demo, after all!)
ini_set('display_errors',1);error_reporting(E_ALL);

define('COIN_TYPE_BITCOIN','bitcoin:');   
define('COIN_TYPE_BITCOINCASH','ppk:bch/');   
define('COIN_TYPE_BYTOM','ppk:joy/btm/');   

define('DID_URI_PREFIX','did:'); //DID标识前缀
define('PPK_URI_PREFIX','ppk:'); //ppk标识前缀
define('PPK_URI_RES_FLAG','#');  //ppk标识资源版本前缀

define('PTTP_NODE_API_URL','http://tool.ppkpub.org/odin/');   //此处配置PTTP协议代理节点

$dbhost = "localhost";                                    
$dbuser = 'testuser';
$dbpass = 'test123';
$dbname = "odinoauth"; 

$dsn    = 'mysql:dbname='.$dbname.';host='.$dbhost;


require_once('OAuth2/Autoloader.php');
OAuth2\Autoloader::register();

define('CLIENT_URL', 'https://tool2.ppkpub.org/oauth/');
define('SERVER_URL', 'https://tool2.ppkpub.org/oauth/');

$storage = new \OAuth2\Storage\Pdo(array('dsn' => $dsn, 'username' => $dbuser, 'password' => $dbpass));

$server = new OAuth2\Server($storage);

$server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));
$server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));

require_once('ppk_swap.function.php');

//初始化数据库连接
$g_dbLink=@mysqli_connect($dbhost,$dbuser,$dbpass,$dbname) or die("Can not connect to the mysql server!");
@mysqli_query($g_dbLink,"Set Names 'UTF8'");

session_start();

$g_logonUserInfo=getLogonUserInfo();
if($g_logonUserInfo!=null){
  $g_currentUserODIN=$g_logonUserInfo["user_uri"];
  //$g_currentUserName=$g_logonUserInfo["name"];
  $g_currentUserLevel=$g_logonUserInfo["level"];
  //$g_currentUserAvtar=$_SESSION["swap_user_avtar_url"];
}else{
  $g_currentUserODIN='';
  //$g_currentUserName='';
  $g_currentUserLevel=0;
}

$g_cachedUserInfos=array();

//获取当前登录用户信息
function getLogonUserInfo(){
    global $g_dbLink;
    
    //echo 'seesion_id=[',session_id(),']';
    $qruuid=session_id();
    $sql = "select * from qrcodelogin where qruuid='" . $qruuid . "'";
    //echo $sql;
    $rs = mysqli_query($g_dbLink,$sql);
    if (!$rs) {
      return null;  
    }
    $row = mysqli_fetch_assoc($rs);

    if (empty($row['user_odin_uri']))
        return null;
        
    return array(
            'user_uri' => $row["user_odin_uri"],
            'name' => $row['user_odin_uri'],  //待完善用户名称
            'level' => $row['status_code']
        );
}

//撤消当前登录用户信息
function unsetLogonUser(){
    global $g_dbLink;
    $qruuid=session_id();
    $sql = "delete from qrcodelogin where qruuid='" . $qruuid . "'";
    //echo $sql;
    mysqli_query($g_dbLink,$sql);
}


function getLang($cn_str){
    return $cn_str;
}
