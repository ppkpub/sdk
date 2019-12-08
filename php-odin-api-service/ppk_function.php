<?php
/* 
PPk开放协议常用方法（如处理兴趣包和应答数据包)
A php sdk for processing PTTP interest and responding data   
-- PPkPub.org
-- 2018-12-10 Ver0.1
*/
define('PPK_URI_PREFIX',"ppk:");

/*
 应答处理异常状态
*/
function respPttpException( $status_code,$status_detail ) {
    echo generatePttpData(AP_DEFAULT_ODIN.'/'.$status_code.'#1.0',$status_code,$status_detail,'text/html','','no-store');
}

/*
 生成PTTP应答数据包
*/
function generatePttpData( $str_resp_uri,$status_code,$status_detail,$str_content_type,$str_resp_content,$str_cache_control='public' ) {
  $array_metainfo=array();

  //对于非text类型的正文内容都缺省采用base64编码
  if( strcasecmp(substr($str_content_type,0,4),'text') !==0 )   {
    $str_resp_content=base64_encode($str_resp_content);
    $array_metainfo['content_encoding']='base64';
    //$str_resp_content=base64_encode(gzcompress($str_resp_content));
    //$array_metainfo['content_encoding']='gzip';
  }
 
  $array_metainfo['content_type']=$str_content_type;
  $array_metainfo['content_length']=strlen($str_resp_content);
  $array_metainfo['ap_node']=AP_NODE_NAME;
  $array_metainfo['cache-control']=$str_cache_control;
  
  $obj_data=array(
    "uri"=>$str_resp_uri,
    "utc"=>time(),
    "status_code" => $status_code,
    "status_detail" => $status_detail,
    "metainfo" => $array_metainfo,
    "content"=>$str_resp_content
  );
  
  $str_encoded_data = json_encode($obj_data);
  $str_sign=generatePttpSign(DEFAULT_SIGN_HASH_ALGO,DEFAULT_SIGN_PRIVATE_KEY,$str_encoded_data);
  
  $obj_resp=array(
    "ver"  => 1, 
    "data" => $str_encoded_data,
    "sign" => $str_sign
  );
  
  return json_encode($obj_resp);
}

//生成签名
function generatePttpSign($str_hash_algo,$str_private_key,$str_resp_data){
  //echo "generatePttpSign() str_resp_data_hex=",strToHex($str_resp_data),"\n";
  if(strlen($str_hash_algo)==0 || strlen($str_private_key)==0 ){
    return "";
  }
  
  $vd_prv_key="-----BEGIN PRIVATE KEY-----\n".$str_private_key."-----END PRIVATE KEY-----";

  $str_resp_sign=rsaSign($str_resp_data,$vd_prv_key,$str_hash_algo);

  $pttp_sign=$str_hash_algo."withRSA:".$str_resp_sign;
  
  return $pttp_sign;

}

//For generating signature using RSA private key
function rsaSign($data,$strValidationPrvkey,$algo){
    //$p = openssl_pkey_get_private(file_get_contents('private.pem'));
    $p=openssl_pkey_get_private($strValidationPrvkey);
    openssl_sign($data, $signature, $p,$algo);
    openssl_free_key($p);
    return base64_encode($signature);
}


//将根标识中的英文字母按ODIN标识规范转换成对应数字
function convertLetterToNumberInRootODIN($original_odin){  
 $converted_odin="";
 $original_odin=strtoupper($original_odin);
 $odin_len=strlen($original_odin);
 for($kk=0;$kk<$odin_len;$kk++){  
    $chr = substr($original_odin,$kk,1);  
    switch($chr){
        case 'O':
          $chr='0';
          break;
        case 'I':
        case 'L':
        case 'A':
          $chr='1';
          break;
        case 'B':
        case 'C':
        case 'Z':
          $chr='2';
          break;
        case 'D':
        case 'E':
        case 'F':
          $chr='3';
          break;
        case 'G':
        case 'H':
          $chr='4';
          break;
        case 'J':
        case 'K':
        case 'S':
          $chr='5';
          break;
        case 'M':
        case 'N':
          $chr='6';
          break;
        case 'P':
        case 'Q':
        case 'R':
          $chr='7';
          break;
        case 'T':
        case 'U':
        case 'V':
          $chr='8';
          break;
        case 'W':
        case 'X':
        case 'Y':
          $chr='9';
          break;
        default:
          break;
    }
    $converted_odin=$converted_odin.$chr;
 }  
 return is_numeric($converted_odin) ? $converted_odin : null;  
} 