<?php
require 'common.php';

require_once "page_header.inc.php";

$app_redirect_uri=safeReqChrStr('app_redirect_uri');
if(strlen($app_redirect_uri)>0){
    $client_id=time();
    $client_secret=substr(md5(uniqid(mt_rand(), true)),16);
    
    $sql_str = "INSERT INTO oauth_clients (client_id,client_secret,redirect_uri) VALUES ('". $client_id ."','". $client_secret ."','". $app_redirect_uri ."');";
    //echo $sql_str;
    $result=mysqli_query($g_dbLink,$sql_str);
    if($result===false)
    {
        echo '无效参数. Invalid argus';
        exit(-1);
    }else{
        echo '<p>应用已登记成功</p>';
        echo '<p>AppID: ',$client_id,'</p>';
        echo '<p>AppSecret: ',$client_secret,'</p>';
        echo '<p>AppRedirectURI: ',$app_redirect_uri,'</p>';
        exit(0);
    }

}
?>

<h1>奥丁号开放认证服务-登记应用</h1>

<form class="form-horizontal" method="post" action="">
<!--
<div class="form-group">
    <label for="app_odin_uri" class="col-sm-2 control-label">应用的奥丁号标识</label>
    <div class="col-sm-10">
      <input type="text" class="form-control"  name="app_odin_uri" id="app_odin_uri" placeholder="ppk:YourAppODIN#"  >
    </div>
</div>
-->

<div class="form-group">
    <label for="app_redirect_uri" class="col-sm-2 control-label">应用的重定向网址</label>
    <div class="col-sm-10">
      <input type="text" class="form-control"  name="app_redirect_uri" id="app_redirect_uri" placeholder="http(s)://"  >
    </div>
</div>
  
<div class="form-group">
    <label for="use_exist_odin" class="col-sm-2 control-label"></label>
    <div class="col-sm-10">
      <input type='submit' class="btn btn-success"  id="use_exist_odin" value=' 提 交  ' >
    </div>
</div>
</form>