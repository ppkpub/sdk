<?php
/* 
解析ODIN命名标识的API服务的PHP代码
A php json-api service for parsing ODIN URI using the sqlite database of ODIN-javatool daemon  
-- PPkPub.org
-- 2020-07-01
*/
ini_set("display_errors", "On"); 
error_reporting(E_ALL | E_STRICT);

define('PPK_LIB_DIR_PREFIX','../ppk-lib2/php/');   //此处配置PPK SDK的引用路径

//Include PPk Lib
require_once(PPK_LIB_DIR_PREFIX.'Util.php');
require_once(PPK_LIB_DIR_PREFIX.'ODIN.php');
require_once(PPK_LIB_DIR_PREFIX.'PTTP.php');
require_once(PPK_LIB_DIR_PREFIX.'AP.php');
require_once(PPK_LIB_DIR_PREFIX.'PTAP01DID.php');

//设置你的ODIN JAVA管理工具的所在路径
//Set the sqlite database of ODIN-javatool daemon. 
//define('YOUR_JAVATOOL_PATH','G:/PPk-debug'); 
define('YOUR_JAVATOOL_PATH','/home/ppk/ppktool'); 

//设置你的API节点信息
define('PPK_AP_NODE_NAME', 'ODIN Parser AP by PHP, 20200422' );      //AP节点名称

//对应答数据的签名设置 ，不设私钥时为匿名
$gDefaultKeySet=array(
    'keysize' => '2048', 
    "prvkey" => "-----BEGIN PRIVATE KEY-----\r\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCf5r6mXWqPDfhy6iP68MOKgbmu\r\nxNcsr6EMtBjWepeSsa37nSXPdP3YiI8zUNbOtHPvJys+Rp6wex0KgHjM9FDLQ4kchDMZ+ld2uplt\r\nyEll9MwgVgOg+g+eU0fzFviFDwugRG1jHHB10aId+9iFMRgVAGHhyP4DESx7H925aKb07mrUj08c\r\nrpqfCVI2qaHLPEsCcVQpKqvZytTLtl8JOL3M3rclc6wm1aL91Jm4rVEhJ6PrOpCCSJBx4iioVcGN\r\nFWlglB6JDebDe6hmq2HzR7xSpPVlerIPzY5uxveh8pEWpuYSG4uLr4YlqSBmxiz8Ez9Kl8gXRafL\r\nk9ZN6ZkJZqNfAgMBAAECggEAawCudAXvWOuwZrXoffS/5eAJsbpng6/Dxgx+0ogXBkOAefAfbUSM\r\n2moH6f8ewBRhwJglh/caGl9If86ZCA42Qs9e4YZV6/xqqzkTkzOEaoX2U2074G12Jiz06OdmRyRa\r\nU1V3HevaFf3Czu3JZtgDlYo79ivaT5MegQZCCeDOWPhj5Dbx7EJjSUnXlTk5EWUWGiGgVZd25opc\r\ndP8pPf6pKTPbaC5FpWBeAUgmf8/umHN1/DdN4IvRot6f1/2pG6xq0aTJjQwRHyIZ/NlVwZrCjDa1\r\nMvKBvyBuRvRQrrzmhsvGJ694WUtGFjzuY2Y6RttaNkF6BW2WQsVWUs+p59DTAQKBgQDSmYWFU1sR\r\ndh0El9vz1d0r3FlDzeyQqjbWlAb08VuvtHtCaJQziJfcD6QJv3g22VYvH6lZhGKYwiAiibjzeJA0\r\nNzWFqZH35EGyxRdD++1qHt2uaGb1PT0h0budgIo6RGSefYHPwzRyrkda266uZQgb25hCkC1STNNW\r\ne8bU/VnchwKBgQDCX05mHRPvdE+UI+OLVvtUk4J5knzjoGTPbfllEP/Zp2Vj8xRwkJIARCwIsFIQ\r\nnJw6tFPLWmXQj6DCRmn9qG2iQpbBrTQ3GHWuGHP5MpAvyqc76VNQMVlV3/6bBfaNKBOxSQKGNzpb\r\nF7npOAkIFjd+3ARgaLJevUKMMez9EZdQaQKBgQCIdyi5KzlwyCunhUvm5idKO5+wOyjW2SVtyD/1\r\nysxRv867So72EcXtuEjgdCzOxeh94rNXKVzGhcxS9RFe2zn+S+Qnt6i5jDQyRlp8GCxQvq2BTW+h\r\n7EvHtWMwfVGUziqxNLzAR0qeIWZlbsLziM5HVvWD9G8ZzGSJvu0pqP3o0wKBgDWbiYjaPjRNlOEb\r\noNc+TyT9Zf/XqgAxrXwULbN1I8tIwsr1MM724H2YT7i5wHh6aRA7ydyM+wWxhzntp6/g8xPMX73c\r\n4kjLwzEX52x0SJYVw1ffuy2j3qqzk4n39A7sXboIk9ymgL685XZwEPWdAwNG9SIN3hwJAMCfyGfn\r\noR75AoGAATnK6gjwx1x++H2i527Mg8t4BlHULRf0UAn4/SL06WvKAXL6vAx5VicvR/6NGPtrlbeU\r\n2dukAGv1YovaWe5mTyJNtSn64Nz45coNtRIWvA4WsLLedBC2ib3H/L7WgYjOXesiouD0K2zARfFY\r\nAKFdMhmZre61FSiy+oA+zriTyjI=\r\n-----END PRIVATE KEY-----\r\n", //正确
    //"prvkey"=>"-----BEGIN PRIVATE KEY-----\r\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCFO8tVKcJS0jWZnH+hu1grS7Vk\r\nJwrTfLgadaPPplMxeGRA8eyIBbVabykLc33TvDjRPOy/xaiPFusJjnYorQYfndQrPIhcbEdebIZ5\r\nz5YAXbyVEFz8D4kUVxeoTxMNAacIMXge+XD0J7eWoHTAFfegpzxKAoPQ48GK59bh/Cap0zNqNNVD\r\nlgwGWXoIA+1bBOLhi5kNV87tPugWhIiunmfuSiMV+/WFw3TJSjE21c+GXAE9lTq6k+tPk8tPX7cL\r\n8jGNWwsWKo04YjctJnJRg2oN5CnJqVHf5EfejPrByisayXXWBy04AjJED0v/Xs4F6oSIvpnfMiyi\r\n9drYlvVU5Hh7AgMBAAECggEAMRRbzN17NiM1l4atBZkL27ch9Ojk9g9Fiom3dHDiyKB+3eXAqkLg\r\nEZZNWmiK+4qkq39z/xkDBOL+ZiDSqh1C2ja7x8meud0xVTlJOGod6bieFZNXjYrzhkim8FtguzPW\r\nohAHHfHpiCdxW81z9Kai981jSigvAq5Dx7Wr1MecPoaD+Pjbsxov6vpAjXauvVvstgP1Z12+f5Fi\r\nTlGoubIt58Gv1AckUbZ//Ecr1MQhphGvrX0vXcJhUMKZBdgT/Bj8aqekmgkjQbTnh2UzekDm7mbn\r\nRkiGPYBnHOYS3WBaNRdj587jqg4z28moYpFaSn18tD2iC6BKQPqMmlkqs7ie4QKBgQDL3eaJWOwc\r\n+WOKpsqXQMm5bmzfAkL2Wbd92oWjXQVrG6uM26/9fvFIM4k0knCz2A6+vKM8rwwFVigpaWVha8Om\r\nAABdp76eVBYYnG78epOVSQGlT8dzl7zHSNoIQed+ixY6JObCmZ3aYDn0Nv3qwUhCLrt94URQF/pR\r\ne750RC8FYwKBgQCnTedN2NRGaiVsqFeOLPNZ8+MTQCx5Falevit8HQjytQ9f5W8QbYWd9DOM8OnN\r\nH/Y0SC8VR2HRn7ZOvNgbWDwHIla4L+dT9Z9Y3M18a3j9r7idO/qMdjCSnmyPl4pedW2ImZ6xkfcv\r\n+hXl1J5BEnVZeJcdrNkUPhMKEEmt6RQYCQKBgEx9RDryFxzD4TorXEWltEoTiVue0Jr3jGX28D8b\r\n7qWCKzpdTsmwsDyjwW3tJ8YCYX3k7uYc00jJS6ZF+hi0QyLsSzbYciebavLu9qFaKDdRvgFVToMr\r\nQlQPHGcOuxl6e+ty3vXShyxhAD2FyH0k6cSTHhubwnK+nFeoMwwSbQX7AoGBAJ8cGKlRV/grhLIE\r\nm1gMadcXedJaCrGRJ0WCSCq+Fj90cE28DlcqQZPJpakZiNDa37QzHgv3mhDY+nGBaWkADf6e6qg6\r\nbp7LjqLdQtNcBnIFRubKHuqskF8wKYCaFy7kMKpjpqercNEA3wh3n5W1L0NKyzSeqMh2jHbarKen\r\nbcO5AoGBAJsXCwUbiJXJ6xS1V6FYHpEg1REaFi8H1FRUEHRoLWBRUSm/hicsLyW2UNGZnZLTk6SH\r\nxxR3AZ1fhRouRwru+ysxkDfi/2Ewxra7m7Yp0bE3j+rrbzYPlTzJjEkxboArLnJ780MfnjXuk+dQ\r\nVuXFgzUQcO1ViOAu01qYSpVtIVyH\r\n-----END PRIVATE KEY-----\r\n", //错误
    "pubkey" => "-----BEGIN PUBLIC KEY-----\r\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAn+a+pl1qjw34cuoj+vDDioG5rsTXLK+h\r\nDLQY1nqXkrGt+50lz3T92IiPM1DWzrRz7ycrPkaesHsdCoB4zPRQy0OJHIQzGfpXdrqZbchJZfTM\r\nIFYDoPoPnlNH8xb4hQ8LoERtYxxwddGiHfvYhTEYFQBh4cj+AxEsex/duWim9O5q1I9PHK6anwlS\r\nNqmhyzxLAnFUKSqr2crUy7ZfCTi9zN63JXOsJtWi/dSZuK1RISej6zqQgkiQceIoqFXBjRVpYJQe\r\niQ3mw3uoZqth80e8UqT1ZXqyD82Obsb3ofKRFqbmEhuLi6+GJakgZsYs/BM/SpfIF0Wny5PWTemZ\r\nCWajXwIDAQAB\r\n-----END PUBLIC KEY-----\r\n",
);


//确认PHP的PDO和PDO_sqlite模块已经允许使用
//Please make sure that the PDO and PDO_sqlite are enabled
//For PHP 7.1: sudo apt-get install php7.1-sqlite3 
//For PHP 7.2: sudo apt-get install php7.2-sqlite3
//phpinfo();exit;

//在URL中指定例如 tool.ppkpub.org/odin/?pttp_interest={"ver":1,"interest":{"uri":"ppk:0%23"}}  
//在或者 tool.ppkpub.org/odin/?pttp_interest=ppk:0%23  

$array_req = \PPkPub\AP::parsePttpInterest();
$str_pttp_uri = $array_req['uri'];
$force_pns = \PPkPub\Util::safeReqChrStr('force_pns')=='on';

if(!isset($str_pttp_uri)){
  respPttpException( '', '400',"Bad Request : no valid uri " );
  exit(-1);
}

//echo "str_pttp_uri=$str_pttp_uri\n";
//echo "parent_odin_path=$parent_odin_path , resource_id=$resource_id , req_resource_versoin=$req_resource_versoin \n";

//区分处理特殊情况
if($str_pttp_uri=='ppk:btm*' || $str_pttp_uri=='ppk:btm' ){ //临时测试
  $str_resp_uri=$str_resp_uri; 
  //$str_resp_content='{"ver":1,"auth":"0","vd_set":{"algo":"SHA256withRSA","pubkey":"MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDWCECab3U+C8I9B6qdvnl2gBqsf\/kPBtTHC2Lr\r\nuWtCiOWH3tdHzSmY5qckqSCfnijO8lGSqP2xFc8e55KimASTjp2lxLVQ\/\/HyAFXb52+XkA+zxrfx\r\nZ2SZrxqT7MejNvSMP183zgr5k8\/C1idMUFe4mAwVg5IIJKYpKmOwa5dN7QIDAQAB\r\n"},"ap_set":{"0":{"url":"http:\/\/ppk001.sinaapp.com\/bns\/ap\/"}},"register":"1bytomWCiMPqfsSQvmSoM6aeFtGhF6wRE","admin":"1CwHQdTih2FPduihqDCYTkh9DQvn5UmCN6","authentication":[{"type":"EcdsaSecp256k1VerificationKey2019","publicKeyHex":"04a5a2f172a7a39a61614a1f5063bf4c7b5c510a6d66adb7447c48101128a0ab0d351c29a7abbe3d75f6a9aed963c4a402e153231efe2c2245d9c65bf8fbf00962"},{"type":"EcdsaSecp256k1VerificationKey2019","publicKeyHex":"0370f22ed04fa8ffe08aef479087d3aa3902fc653c0cf9e88064caf02ccd5f7726"},{"type":"RsaVerificationKey2018","publicKeyPem":"-----BEGIN PUBLIC KEY-----\r\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDWCECab3U+C8I9B6qdvnl2gBqsf\/kPBtTHC2Lr\r\nuWtCiOWH3tdHzSmY5qckqSCfnijO8lGSqP2xFc8e55KimASTjp2lxLVQ\/\/HyAFXb52+XkA+zxrfx\r\nZ2SZrxqT7MejNvSMP183zgr5k8\/C1idMUFe4mAwVg5IIJKYpKmOwa5dN7QIDAQAB\r\n\r\n-----END PUBLIC KEY-----\r\n"}]}';   

  $str_resp_content='{"invoked_pns_url":"test","ver":2,"auth":"0","admin":"test","title":"btmdemo","register":"test","ap_set":{"0":{"url":"http://btmdemo.ppkpub.org/ap2/"}}}';           
  
  respPttpData( $str_resp_uri,'200','OK','text/json',$str_resp_content );
/*
}else if( $str_pttp_uri=='ppk:ap2*' ||  $str_pttp_uri=='ppk:ap2' 
       || $str_pttp_uri=='ppk:apb*' ||  $str_pttp_uri=='ppk:apb'
       || $str_pttp_uri=='ppk:172*' ||  $str_pttp_uri=='ppk:172'
       || $str_pttp_uri=='ppk:513390.2015*' ||  $str_pttp_uri=='ppk:513390.2015'
) {   //临时测试
  $str_resp_uri=$str_pttp_uri; 

  $str_resp_content='{"invoked_pns_url":"test","ver":2,"auth":"0","vd_set":{"format":"PEM","pubkey":"-----BEGIN PUBLIC KEY-----\r\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA2+wHK7OcAme56NAVFcdentFBqv8KuWXu\r\nl9fTWh6MyXx5uBTV8Xq1mJ7QI+ewthFfUWXN2YllEbLd7foe4P+2jlgtCWuysX0B28i/gfwWQ4wr\r\nYR4MXaazV4GsG7YYJ0eRmQyZm5QU0Qmj8ApOJKUMyCJTzGYyKvfHsMxIMIRrsbJvZq3OP32e6yYL\r\njskFmMfI7ZKmk5nd3VJmQzRCJEuBPONfHta/60cLvc7q/PzJESUZMLzmaD//RJQHqvYs3zuPM5Vc\r\nQVZoo6qdB7f+0gv/WBs1aP8N96CFMovxPmPrHExcc6mxea1SlJuSQaXgo4OOrFjz5HeLNHUy2HlZ\r\nWmSb1wIDAQAB\r\n-----END PUBLIC KEY-----\r\n"},"admin":"1HVSDUmW3abkitZUoZsYMKZ2PbiKhr8Rdo","title":"ns for ap2","register":"1HVSDUmW3abkitZUoZsYMKZ2PbiKhr8Rdo","ap_set":{"0":{"url":"http://tool.ppkpub.org/ap2/"}}}';   
  
  respPttpData( $str_resp_uri,'200','OK','text/json',$str_resp_content );
}else if( $str_pttp_uri=='ppk:arc*' ||  $str_pttp_uri=='ppk:arc' ) {   //临时测试
  $str_resp_uri=$str_pttp_uri; 

  $str_resp_content='{"invoked_pns_url":"test","ver":2,"auth":"0","vd_set":{"format":"PEM","pubkey":"-----BEGIN PUBLIC KEY-----\r\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAxSdHHo9jcuFXafpahLK2JTaDNcVmqlL1\r\neZrBZXOWyLCktSoohhwQIR2aWHAm9cOJPMFSmMVaL8kKokxGptzVQOA2dpTzTgR2nlMFm6pSCUZP\r\nuVdN6CEwHcJgFS77VjZoLugNQ8L3qxqTOYeJZCtDXipJRv1jLlzlDuC0XPP2d84Aeay2Us8j47V1\r\nOKPEWylcO7vUvZUQV4fLMWCWFN5Vb1lN4b2qbvRRZfpd5snLB9tZYRVmuLT6P2PLmDihmNmNYdx8\r\n07uu/evZYncefsc2cLAEiMi1A8LP60LEkgaIzvVQiKOWlahnsxII6IGTb5gu0oFIp68WgrfoRWO3\r\nOaw71QIDAQAB\r\n-----END PUBLIC KEY-----\r\n"},"admin":"1HVSDUmW3abkitZUoZsYMKZ2PbiKhr8Rdo","title":"test for ap2","register":"1HVSDUmW3abkitZUoZsYMKZ2PbiKhr8Rdo","ap_set":{"0":{"url":"http://tool.ppkpub.org/ap2/"}}}';   
  
  respPttpData( $str_resp_uri,'200','OK','text/json',$str_resp_content );
*/
}else if( $str_pttp_uri=='ppk:bch*' ||  $str_pttp_uri=='ppk:bch' ) {   //临时测试
  $str_resp_uri=$str_pttp_uri; 

  $str_resp_content='{"invoked_pns_url":"test","ver":2,"auth":"0","admin":"test","title":"test for bch","register":"test","ap_set":{"0":{"url":"http://tool.ppkpub.org/ap2/"}}}';   
  
  respPttpData( $str_resp_uri,'200','OK','text/json',$str_resp_content );
/*}else if( $str_pttp_uri=='ppk:joy*' ||  $str_pttp_uri=='ppk:joy' ) {   //临时测试
  $str_resp_uri=$str_pttp_uri; 

  $str_resp_content='{"invoked_pns_url":"test","ver":2,"auth":"0","admin":"test","title":"for test ","register":"test","ap_set":{"0":{"url":"http://tool.ppkpub.org/ap2/"}}}';   
  
  respPttpData( $str_resp_uri,'200','OK','text/json',$str_resp_content );
  */
/*
}else if(\PPkPub\Util::startsWith($str_pttp_uri,'ppk:joy/btmid/') || \PPkPub\Util::startsWith($str_pttp_uri,'ppk:527064.583/btmid/')){ 
  //echo 'http://btmdemo.ppkpub.org/asset/ap/btmid.php?pttp_interest=',urlencode($str_pttp_interest);
  echo simpleGetPage('http://tool.ppkpub.org/asset/ap/btmid.php?pttp_interest='.urlencode($str_pttp_interest));
}else if(\PPkPub\Util::startsWith($str_pttp_uri,'ppk:joy/btm/') || \PPkPub\Util::startsWith($str_pttp_uri,'ppk:527064.583/btm/')){ 
  echo simpleGetPage('http://tool.ppkpub.org/ap/btm/?pttp_interest='.urlencode($str_pttp_interest));
}else if(\PPkPub\Util::startsWith($str_pttp_uri,'ppk:joy/movtest/asset/') || \PPkPub\Util::startsWith($str_pttp_uri,'ppk:527064.583/movtest/asset/')){ 
  echo simpleGetPage('http://tool.ppkpub.org/ap/movtest/asset.php?pttp_interest='.urlencode($str_pttp_interest));
}else if(\PPkPub\Util::startsWith($str_pttp_uri,'ppk:joy/movtest/') || \PPkPub\Util::startsWith($str_pttp_uri,'ppk:527064.583/movtest/')){ 
  echo simpleGetPage('http://tool.ppkpub.org/ap/movtest/?pttp_interest='.urlencode($str_pttp_interest));
}else if(\PPkPub\Util::startsWith($str_pttp_uri,'ppk:bch/') || \PPkPub\Util::startsWith($str_pttp_uri,'ppk:514499.1525/')){ 
  echo simpleGetPage('http://tool.ppkpub.org/ap/bch/?pttp_interest='.urlencode($str_pttp_interest));
}else if(\PPkPub\Util::startsWith($str_pttp_uri,'ppk:btm/') || \PPkPub\Util::startsWith($str_pttp_uri,'ppk:519502.2699/')){ 
  echo simpleGetPage('http://ppk001.sinaapp.com/bns/ap/?pttp_interest='.urlencode($str_pttp_uri));
  //echo simpleGetPage('http://localhost:8081/bns/ap/?pttp_interest='.urlencode($str_pttp_interest));
*/
}else{
    //一般处理
    //echo 'doPTTP str_pttp_uri=',$str_pttp_uri,"\n";
    $array_ppk_uri = \PPkPub\ODIN::splitPPkURI($str_pttp_uri);

    //只负责解析根标识
    if( strlen($array_ppk_uri['parent_odin_path'])==0 )
        respRootOdinSetting($array_ppk_uri['resource_id'],$force_pns);
    else
        //respRootOdinSetting($array_ppk_uri['odin_chunks'][0]);
        respPttpException( '', '503',"Bad Request : only support root ODIN like ppk:joy " );
}



function respRootOdinSetting($root_odin,$force_pns=false)
{
    $str_root_odin_uri = \PPkPub\ODIN::PPK_URI_PREFIX.$root_odin.\PPkPub\ODIN::PPK_URI_RESOURCE_MARK ;
    
    $result = getRootOdinSettingFromLocalDB($root_odin);
    
    if($result['code']!=0){
        respPttpException($str_root_odin_uri ,$result['code'], $result['msg'] );
        exit;
    }
    $obj_setting = $result['setting'];
    //print_r($obj_setting);
    
    $str_pns_url = trim(@$obj_setting->pns_url);
    
    if(strlen($str_pns_url)==0){
        if(empty(@$obj_setting->ap_set)){
            //测试使用默认的PNS服务
            $str_pns_url = 'http://tool.ppkpub.org/ap2/';
            $obj_setting->pns_url = $str_pns_url;
        } 
    }

    if($force_pns && strlen($str_pns_url)>0){
        //设置了标识托管服务
        //注意使用自动解析PSN服务，可能会导致安卓客户端处理程序出现下述JSON解码错误，所以默认是关闭的 20200624
        //java.lang.NullPointerException: Attempt to invoke virtual method 'int java.lang.String.length()' on a null object reference
   	    //  at org.json.JSONTokener.nextCleanInternal(JSONTokener.java:116)
	    //  at org.json.JSONTokener.nextValue(JSONTokener.java:94)
        $obj_setting = \PPkPub\PTTP::mergeRootOdinSettingFromPNS($obj_setting, $str_pns_url,$str_root_odin_uri);
    }

    
    $str_resp_content=json_encode($obj_setting);

    $str_resp_uri=\PPkPub\ODIN::PPK_URI_PREFIX.$root_odin."*";

    respPttpData( $str_resp_uri,'200','OK','text/json',$str_resp_content );
}

function getRootOdinSettingFromLocalDB($root_odin)
{
    try{
        $conn = new PDO('sqlite:'.YOUR_JAVATOOL_PATH.'/resources/db/ppk-1.db');
        if (!$conn){
          return array('code'=>503,"msg"=>"sqlite connect failed");
        }
    }catch(PDOException $e){
        return array('code'=>503,"msg"=>"PDOException:".$e->getMessage() );
    }
    
    $root_number=\PPkPub\ODIN::convertLetterToNumberInRootODIN($root_odin);
    
    if( !is_numeric($root_number) ){
        return array('code'=>400,"msg"=>'Invalid odin:'.$root_odin);
    }
    
    $sqlstr = "SELECT * FROM odins where full_odin='$root_number' or short_odin='$root_number';";
    
    $sth = $conn->prepare($sqlstr); 
    $sth->execute(); 
    $row = $sth->fetch(PDO::FETCH_ASSOC);
    if(!$row) 
    {
        return array('code'=>404, "msg"=> 'No matched result for:'.$root_odin  );
    }
    
    $full_odin=$row['full_odin'];

    if(strlen($row['odin_set'])>0){
      $objSetting=@json_decode($row['odin_set'],false);
    }
    if( !isset($objSetting) ){
      $objSetting=@json_decode('{}',false);
    }
    
    $objSetting->register = $row['register'];
    $objSetting->admin = $row['admin'];

    //Add DID document
    //$str_did_uri=DID_URI_PREFIX.\PPkPub\ODIN::PPK_URI_PREFIX.$root_odin.\PPkPub\ODIN::PPK_URI_RESOURCE_MARK;
    $str_did_uri=\PPkPub\ODIN::PPK_URI_PREFIX.$root_odin.\PPkPub\ODIN::PPK_URI_RESOURCE_MARK;
    
    $array_authentication=array();
    
    if( isset($objSetting->vd_set) 
      && isset($objSetting->vd_set->pubkey)){ //现在只支持RSA
       $str_pubkey_pem = \PPkPub\Util::startsWith($objSetting->vd_set->pubkey,"-----BEGIN ") 
                ? $objSetting->vd_set->pubkey
                : "-----BEGIN PUBLIC KEY-----\n".$objSetting->vd_set->pubkey."-----END PUBLIC KEY-----";
       $array_authentication[]=array(
            "type"=> \PPkPub\PTAP01DID::DID_KEY_TYPE_RSA,
            "publicKeyPem"=> $str_pubkey_pem,
        ); 
    }
    
    $tmp_content=getBtcAddressPubkey($row['register']);
    if(strlen($tmp_content)>0){
        $array_authentication[]=array(
            "type"=> \PPkPub\PTAP01DID::DID_KEY_TYPE_ECC_SECP256K1,
            "publicKeyHex"=>$tmp_content,
        );
    }

    if($row['register']!=$row['admin']){
        $tmp_content=getBtcAddressPubkey($row['admin']);
        if(strlen($tmp_content)>0){
            $array_authentication[]=array(
                "type"=>\PPkPub\PTAP01DID::DID_KEY_TYPE_ECC_SECP256K1,
                "publicKeyHex"=>$tmp_content,
            );
        }
    }

      
    
    $objSetting->x_did =  array(
        '@context' => 'https://www.w3.org/ns/did/v1',
        'id' => $str_did_uri,  
        'title' => @$objSetting->title,  
        'authentication' => $array_authentication
    );
    //print_r($objSetting);
    
    return array('code'=>0,"full_odin"=>$full_odin,"setting"=>$objSetting);
    
}

function getBtcAddressPubkey($address)
{
    $cache_filename='./cache/'.urlencode($address);
    $tmp_content=@file_get_contents($cache_filename);
    if(strlen($tmp_content)==0){  
        $tmp_url='https://blockchain.info/q/pubkeyaddr/'.$address;
        $tmp_content=@file_get_contents($tmp_url);
        
        if(strlen($tmp_content)>0){
            @file_put_contents($cache_filename,$tmp_content);
        }
    }
    return $tmp_content;
}



/*
 应答处理异常状态
*/
function respPttpException( $str_pttp_uri, $status_code,$status_detail ) 
{
    global $gDefaultKeySet;

    \PPkPub\AP::respPttpException( 
            $str_pttp_uri, //AP_DEFAULT_ODIN.'/'.$status_code.'*1.0', 
            null,
            $status_code,
            $status_detail,
            'text/html',
            '',
            null,
            $gDefaultKeySet
        );
}


/*
 生成PTTP应答数据包
*/
function respPttpData( $str_resp_uri,$status_code,$status_detail,$str_content_type,$str_resp_content,$str_cache_as_latest='public,max-age=600' ) 
{
  global $gDefaultKeySet;
  
  \PPkPub\AP:: respPttpData(  
      $str_resp_uri,
      null,
      $status_code,
      $status_detail,
      $str_content_type,
      $str_resp_content,
      $str_cache_as_latest,
      $gDefaultKeySet 
  );

}

