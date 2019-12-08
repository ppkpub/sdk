<?php
/* 
ODIN标识的数据查询API服务的PHP代码,通过同步访问ODIN JAVA管理工具的SQLITE数据库来实现
A php json-api service for querying registered ODINs using the sqlite database of ODIN-javatool daemon  
-- PPkPub.org
-- 2019-04-15 Ver0.1
*/


//设置你的ODIN JAVA管理工具的所在路径
//Set the sqlite database of ODIN-javatool daemon. 
define('YOUR_JAVATOOL_PATH','YourPath'); 

//确认PHP的PDO和PDO_sqlite模块已经允许使用
//Please make sure that the PDO and PDO_sqlite are enabled
//For PHP 7.1: sudo apt-get install php7.1-sqlite3 
//For PHP 7.2: sudo apt-get install php7.2-sqlite3

//在URL中指定例如 https://tool.ppkpub.org/odin/query.php?address=1HVSDUmW3abkitZUoZsYMKZ2PbiKhr8Rdo
$address=safeReqChrStr('address');
$start=safeReqNumStr('start');
if(strlen($start)==0)
    $start=0;

$limit=safeReqNumStr('limit');
if(strlen($limit)==0)
    $limit=10000;


if(strlen($address)>0){
  respAddressRegisterODINs($address,$start,$limit);
}else{
  respApiException( '403',"Forbidden : Not supported api " );
}

function respAddressRegisterODINs($address,$start,$limit){
    try{
        $conn = new PDO('sqlite:'.YOUR_JAVATOOL_PATH.'/resources/db/ppk-1.db');
        if (!$conn){
          respApiException( '503','sqlite connect failed');
          exit;
        }
    }catch(PDOException $e){
        respApiException( '503', "PDOException:".$e->getMessage() );
        exit;
    }
    
    $sqlstr = "SELECT count(*) as total FROM odins where register='$address';";
    $sth = $conn->prepare($sqlstr); 
    $sth->execute(); 
    $row = $sth->fetch(PDO::FETCH_ASSOC);
    $total=$row['total'];
    
    $sqlstr = "SELECT * FROM odins where register='$address' order by short_odin desc limit $start,$limit ;";
    //$sqlstr = "SELECT * FROM odins where register='$address' order by short_odin desc;";
    $sth = $conn->prepare($sqlstr); 
    $sth->execute(); 
    
    $tmp_list=array();
    while($row = $sth->fetch(PDO::FETCH_ASSOC)) 
    { 
      $tmp_list[]=array(
        "full"  => $row['full_odin'], 
        "short" => $row['short_odin'],
      );
    }
    
    $obj_resp=array(
        "status"  => 'OK', 
        "total" => $total,
        "start" => $start,
        "limit" => $limit,
        "list" => $tmp_list,
      );
      
    echo json_encode($obj_resp);
}

/*
 应答处理异常状态
*/
function respApiException( $status_code,$status_detail ) {
  $obj_resp=array(
    "status"  => $status_code, 
    "detail" => $status_detail,
  );
  
  echo json_encode($obj_resp);
}

//安全获取HTTP传入参数，值类型为字符串
//可以指定GET和POST数组，缺省为系统默认全局变量
function safeReqChrStr($argvName,$getArgus=null,$postArgus=null){
    if(null==$getArgus) $getArgus=$_GET;
    if(null==$postArgus) $postArgus=$_POST;
    
    $argValue=trim(@$getArgus[$argvName]);
    
    if($argValue=='')
    {
        $argValue=@$postArgus[$argvName];
    }
    
    if (false==get_magic_quotes_gpc()) 
    {
        $newArgValue = addslashes($argValue);
        if(strlen($newArgValue)>0)
            $argValue=$newArgValue;
    }
    return trim($argValue); 
}

//安全获取HTTP传入参数，值类型为数字
//可以指定GET和POST数组，缺省为系统默认全局变量
function safeReqNumStr($argvName,$getArgus=null,$postArgus=null){
    if(null==$getArgus) $getArgus=$_GET;
    if(null==$postArgus) $postArgus=$_POST;
    
    $argValue=trim(@$getArgus[$argvName]);
    if($argValue=="")
    {
        $argValue=@$postArgus[$argvName];
    }

    if(!is_numeric($argValue))
    {
        return "";
    }
    
    return trim($argValue); 
}