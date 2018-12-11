<?php
/* 
解析ODIN命名标识的API服务的PHP代码,通过同步访问ODIN JAVA管理工具的SQLITE数据库来实现
A php service for parsing ODIN URI using the sqlite database of ODIN-javatool daemon  
-- PPkPub.org
-- 2018-12-10 Ver0.1
*/
require_once('ppk_function.php');

//设置你的ODIN JAVA管理工具的所在路径
//Set the sqlite database of ODIN-javatool daemon. 
define('YOUR_JAVATOOL_PATH','YourPath'); 

//设置你的API节点信息
define('AP_NODE_NAME', 'Root ODIN Parser AP by PHP, 20181210' );      //AP节点名称
define('AP_DEFAULT_ODIN', PPK_URI_PREFIX.'YourOdin/' ); //缺省使用的ODIN标识前缀
define('DEFAULT_SIGN_HASH_ALGO', 'SHA256' );      //缺省的签名用哈希算法，如果启用签名，需要打开PHP的openssl支持
define('DEFAULT_SIGN_PRIVATE_KEY', '' ); //缺省的签名用私钥，为空字符串时表示不需要签名

//确认PHP的PDO和PDO_sqlite模块已经允许使用
//Please make sure that the PDO and PDO_sqlite are enabled
//For PHP 7.1: sudo apt-get install php7.1-sqlite3 
//For PHP 7.2: sudo apt-get install php7.2-sqlite3
//phpinfo();exit;

//在URL中指定例如 ?pttp_interest={"ver":1,"interest":{"uri":"ppk:426896.1290/"}}  
$array_pttp_interest=array();
$str_pttp_interest='';
$str_pttp_uri='';

$str_pttp_interest=trim($_GET['pttp_interest']);

if(strlen($str_pttp_interest)>0){
  //提取出兴趣uri
  $array_pttp_interest=json_decode($str_pttp_interest,true);
  $str_pttp_uri=$array_pttp_interest['interest']['uri'];
}

if(!isset($str_pttp_uri)){
  respPttpException( '400',"Bad Request : no valid uri " );
  exit(-1);
}

if( 0!=strncasecmp($str_pttp_uri,PPK_URI_PREFIX,strlen(PPK_URI_PREFIX)) ){
  respPttpException( '400',"Bad Request : Invalid ppk-uri " );
  exit(-1);
}

$odin_chunks=array();
$parent_odin_path="";
$resource_id="";
$req_resource_versoin="";
$resource_filename="";

$tmp_chunks=explode("#",substr($str_pttp_uri,strlen(PPK_URI_PREFIX)));
if(count($tmp_chunks)>=2){
  $req_resource_versoin=$tmp_chunks[1];
}

$odin_chunks=explode("/",$tmp_chunks[0]);
if(count($odin_chunks)==1){
  $parent_odin_path="";
  $resource_id=$odin_chunks[0];
}else{
  $resource_id=$odin_chunks[count($odin_chunks)-1];
  $odin_chunks[count($odin_chunks)-1]="";
  $parent_odin_path=implode('/',$odin_chunks);
}

//echo "str_pttp_uri=$str_pttp_uri\n";
//echo "parent_odin_path=$parent_odin_path , resource_id=$resource_id , req_resource_versoin=$req_resource_versoin \n";

if(strlen($parent_odin_path)==0){ //根标识
    respRootOdinSetting($resource_id);
}else{ //扩展标识
    respPttpException( '403',"Forbidden : Only support to parse root ODIN " );
}

//按PTTP协议应答根标识的注册信息
function respRootOdinSetting($root_odin){
    try{
        $conn = new PDO('sqlite:'.YOUR_JAVATOOL_PATH.'/resources/db/ppk-1.db');
        if (!$conn){
          respPttpException( '503','sqlite connect failed');
          exit;
        }
    }catch(PDOException $e){
        respPttpException( '503', "PDOException:".$e->getMessage() );
        exit;
    }
    
    $root_odin=convertLetterToNumberInRootODIN($root_odin);
    
    if(is_numeric($root_odin)){
        $sqlstr = "SELECT * FROM odins where full_odin='$root_odin' or short_odin='$root_odin';";
    }else{
        respPttpException( '400', 'Invalid odin:'.$root_odin );
        exit;
    }
    
    $sth = $conn->prepare($sqlstr); 
    $sth->execute(); 
    if($row = $sth->fetch(PDO::FETCH_ASSOC)) 
    { 
      $full_odin=$row['full_odin'];
      
      if(strlen($row['odin_set'])>0){
          $arraySetting=@json_decode($row['odin_set'],false);
      }
      if( !isset($arraySetting) ){
          $arraySetting=@json_decode('{}',false);
      }
      
      $arraySetting->register=$row['register'];
      $arraySetting->admin=$row['admin'];
      
      $str_resp_content=json_encode($arraySetting);
      
      $str_resp_uri=PPK_URI_PREFIX.$full_odin."#1.0";

      $str_pttp_data=generatePttpData( $str_resp_uri,'200','OK','text/json',$str_resp_content );
      echo $str_pttp_data;
    }else{
      respPttpException( '404', 'No matched result for:'.$root_odin );
    }
}

