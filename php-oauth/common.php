<?php
require_once('ppk_common_define.php');
require_once('ppk_common_function.php');

require_once('config/config.inc.php');

require_once('OAuth2/Autoloader.php');
OAuth2\Autoloader::register();

$dsn    = 'mysql:dbname='.$dbname.';host='.$dbhost;
$storage = new \OAuth2\Storage\Pdo(array('dsn' => $dsn, 'username' => $dbuser, 'password' => $dbpass));

$server = new OAuth2\Server($storage);

$server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));
$server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));

//初始化数据库连接
$g_dbLink=@mysqli_connect($dbhost,$dbuser,$dbpass,$dbname) or die("Can not connect to the mysql server!");
@mysqli_query($g_dbLink,"Set Names 'UTF8'");

session_start();

$g_logonUserInfo=getLogonUserInfo();
if($g_logonUserInfo!=null){
  $g_currentUserODIN=$g_logonUserInfo["user_uri"];
  $g_currentUserLevel=$g_logonUserInfo["level"];
}else{
  $g_currentUserODIN='';
  $g_currentUserLevel=0;
}

$g_cachedUserInfos=array();

//获取当前登录用户信息
function getLogonUserInfo(){
    global $g_dbLink;
    
    //echo 'seesion_id=[',session_id(),']';
    $qruuid=generateSessionSafeUUID();
    $sql = "select * from oauth_ppk_login where qruuid='" . $qruuid . "'";
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
    $qruuid=generateSessionSafeUUID();
    $sql = "delete from oauth_ppk_login where qruuid='" . $qruuid . "'";
    //echo $sql;
    mysqli_query($g_dbLink,$sql);
}

function getLang($cn_str){
    return $cn_str;
}
