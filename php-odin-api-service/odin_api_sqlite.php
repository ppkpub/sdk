<?php
/* 
解析ODIN命名标识的JSON-API服务的PHP代码,通过同步访问ODIN JAVA管理工具的SQLITE数据库来实现
A php json-api service for parsing ODIN URI using the sqlite database of ODIN-javatool daemon  
-- PPkPub.org
-- 2017-10-09 Ver0.1
*/
define('API_RESULT_OK','OK');
define('API_RESULT_ERROR','ERR');
define('API_RESULT_STATUS','resp_status');
define('API_RESULT_DATA','resp_data');
define('API_RESULT_ERROR_CODE','error_code');
define('API_RESULT_ERROR_DESC','error_desc');
define('API_RESULT_DEBUG_DATA','resp_debug');
define('API_RESULT_DEBUG_ELAPSED_TIME','elapsed_time');
define('API_RESULT_DEBUG_SQL_STAT','sql_stat');
define('API_RESULT_DEBUG_CACHE_STAT','cache_stat');

//设置你的ODIN JAVA管理工具的所在路径
//Set the sqlite database of ODIN-javatool daemon. 
define('YOUR_JAVATOOL_SQLITE_DB','/YOUR_JAVATOOL_PATH/resources/db/ppktest-1.db'); 
define('YOUR_JAVATOOL_SQLITE_DB','E:\MyCoin\PPk\beta\build\resources\db\ppktest-1.db'); 

//确认PHP的PDO和PDO_sqlite模块已经允许使用
//Please make sure that the PDO and PDO_sqlite are enabled
//phpinfo();exit;

$odin_key=safeReqChrStr('odin');

try{
    $conn = new PDO('sqlite:'.YOUR_JAVATOOL_SQLITE_DB);
    if (!$conn){
      responseApiErrorResult(-1,'sqlite connect failed');
      exit;
    }
}catch(PDOException $e){
    responseApiErrorResult(-1,"PDOException:".$e->getMessage());
    exit;
}

//@Mysql_query("Set Names 'UTF8'");

if(is_numeric($odin_key)){
    $sqlstr = "SELECT odins.*,transactions.block_time FROM odins,transactions where (odins.full_odin='$odin_key' or odins.short_odin='$odin_key') AND odins.tx_index=transactions.tx_index order by tx_index desc;";
}else{
    responseApiErrorResult(-2,'Invalid odin:'.$odin_key);
    exit;
}

$sth = $conn->prepare($sqlstr); 
$sth->execute(); 
if($row = $sth->fetch(PDO::FETCH_ASSOC)) 
{ 
  $arrayODIN=array(
    'full_odin'=>$row['full_odin'],
    'short_odin'=>$row['short_odin'],
    'register'=>$row['register'],
    'admin'=>$row['admin'],
    'regist_time'=>$row['block_time'],
  );
  
  if(strlen($row['odin_set'])>0){
      $arrayODIN['setting']=json_decode($row['odin_set'],true);
  }

  responseApiOkResult($arrayODIN);
}else{
  responseApiErrorResult(-2,'No matched result for:'.$odin_key);
}


//安全获取HTTP传入参数，值类型为字符串
//可以指定GET和POST数组，缺省为系统默认全局变量
function safeReqChrStr($argvName,$getArgus=null,$postArgus=null)
{
    if(null==$getArgus) $getArgus=$_GET;
    if(null==$postArgus) $postArgus=$_POST;
    
    $argValue=trim(@$getArgus[$argvName]);
    
    if($argValue=='')
    {
        $argValue=@$postArgus[$argvName];
    }
    if (false==get_magic_quotes_gpc()) 
    {
        $newArgValue = @mysql_real_escape_string($argValue);
        if(strlen($newArgValue)>0)
            $argValue=$newArgValue;
    }
    return trim($argValue); 
}


//用于API处理程序返回标准的出错信息
function responseApiErrorResult($error_code,$error_desc)
{
    $arrayResult=array();
    $arrayResult[API_RESULT_STATUS]=API_RESULT_ERROR;
    $arrayResult[API_RESULT_ERROR_CODE]=$error_code;
    $arrayResult[API_RESULT_ERROR_DESC]=$error_desc;
   
	//if(is_numeric($error_code)){
	//	header('HTTP/1.1 '.$error_code.' '); 
	//}
    return responseApiResult($arrayResult);
}

//用于API处理程序返回标准的成功信息
function responseApiOkResult($arrayReturnVals=NULL)
{
    $arrayResult=array();
    $arrayResult[API_RESULT_STATUS]=API_RESULT_OK;
    
    if(NULL!=$arrayReturnVals)
        $arrayResult[API_RESULT_DATA]=$arrayReturnVals;

    return responseApiResult($arrayResult);
}

//用于API处理程序根据请求返回指定格式的应答数据
function responseApiResult($arrayResult)
{
    global $g_strCrossCallback;

    if(isset($g_strCrossCallback))
    {//输出指定Javascript回调方法，只支持JSON格式
        echo $g_strCrossCallback,'(',json_encode($arrayResult),')';
    }
    else
    {
        echo json_encode($arrayResult);
    }
}
