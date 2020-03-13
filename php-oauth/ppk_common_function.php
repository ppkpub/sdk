<?php
/* 
PPk开放协议常用方法（如处理兴趣包和应答数据包)
A php sdk for processing PTTP interest and responding data   
-- PPkPub.org
-- 2020-02-21
*/
require_once('ppk_common_define.php');
require_once('common_func.php');

/*
//解析PPK资源地址
function parsePPkURI($ppk_uri){
    if( 0!=strncasecmp($ppk_uri,PPK_URI_PREFIX,strlen(PPK_URI_PREFIX)) ){
      return null;
    }

    $odin_chunks=array();
    $parent_odin_path="";
    $resource_id="";
    $req_resource_versoin="";
    $resource_filename="";

    $tmp_chunks=explode("#",substr($ppk_uri,strlen(PPK_URI_PREFIX)));
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
    
    return array(
            'root_odin':$odin_chunks[0],
            'parent_odin_path':$parent_odin_path,
            'resource_id':$resource_id,
            'resource_versoin':$req_resource_versoin,
        );
}
*/

//获取PPk资源信息
function  getPPkResource($ppk_uri,$enable_cache = true){
    global $gArrayCachedPPkResource; //用于在当前请求处理过程中缓存获得的PPk数据,2020-01-03
    
    if( strcasecmp(substr($ppk_uri,0,strlen(DID_URI_PREFIX)),DID_URI_PREFIX)==0){ //兼容以did:起始的用户标识
        $ppk_uri=substr($ppk_uri,strlen(DID_URI_PREFIX));
    }
    //echo "<br>\nppk_uri=",$ppk_uri,"<br>\n";
    if($enable_cache){
        if(isset($gArrayCachedPPkResource)){
            if(isset($gArrayCachedPPkResource[$ppk_uri])){
                //echo "Matched cache<br>\n";
                return $gArrayCachedPPkResource[$ppk_uri];
            }
        }else{
            $gArrayCachedPPkResource=array();
        }
    }
    
    $str_api_url = defined(PTTP_NODE_API_URL) ? 
                    PTTP_NODE_API_URL : 'https://tool.ppkpub.org/ppkapi/';
    
    $ppk_url=$str_api_url.'?pttp_interest='.urlencode('{"ver":1,"hop_limit":6,"interest":{"uri":"'.$ppk_uri.'"}}');
    $tmp_ppk_resp_str=file_get_contents($ppk_url);
    //echo "<br>\nppk_url=",$ppk_url,',$tmp_ppk_resp=',$tmp_ppk_resp_str,"<br>\n";
    $tmp_obj_resp=@json_decode($tmp_ppk_resp_str,true);
    //print_r($tmp_obj_resp);
    $tmp_data=@json_decode($tmp_obj_resp['data'],true);
    
    if($enable_cache)
        $gArrayCachedPPkResource[$ppk_uri]=$tmp_data;
    
    return $tmp_data;
}


//按标识获取用户信息
function  getPubUserInfo($user_odin){
    if(isset($g_cachedUserInfos[$user_odin]))
        return $g_cachedUserInfos[$user_odin];

    $default_user_info=array(
        'user_odin'=> $user_odin,
        'full_odin_uri'=> "",
        'name'=>"",
        'email'=>"",
        'avtar'=>"image/user.png"
    );
  
    $tmp_data=getPPkResource($user_odin);
    //print_r($tmp_data);
    if($tmp_data['status_code']==200){
        $default_user_info['full_odin_uri']=$tmp_data['uri'];
        $tmp_user_info=@json_decode($tmp_data['content'],true);
        //print_r($tmp_user_info);
        $default_user_info['original_content']=$tmp_data['content'];
        if($tmp_user_info!=null){
            if(array_key_exists('@type',$tmp_user_info) && $tmp_user_info['@type']=='PPkDID' ){ //DID格式的用户身份定义
                $default_user_info['name']=$tmp_user_info['attributes']['name'];
                $default_user_info['email']=$tmp_user_info['attributes']['email'];
                $default_user_info['avtar']=$tmp_user_info['attributes']['avtar'];
                $default_user_info['register']=COIN_TYPE_BYTOM.$tmp_user_info['attributes']['wallet_address'];
            }else if(array_key_exists('register',$tmp_user_info)){ //直接使用奥丁号的属性
                $default_user_info['name']=@$tmp_user_info['title'];
                $default_user_info['email']=@$tmp_user_info['email'];
                
                if(startsWith($tmp_user_info['register'],PPK_URI_PREFIX) || startsWith($tmp_user_info['register'],COIN_TYPE_BITCOIN))
                    $default_user_info['register']=$tmp_user_info['register'];
                else
                    $default_user_info['register']=COIN_TYPE_BITCOIN.$tmp_user_info['register'];
            }
            
            $default_user_info['authentication']=$tmp_user_info['authentication'];
        }
        $g_cachedUserInfos[$user_odin]=$default_user_info;
    }
    
    //print_r($default_user_info);
    return $default_user_info;

}

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

  //$str_resp_sign=rsaSign($str_resp_data,$vd_prv_key,);
  $str_resp_sign=rsaSign($str_resp_data,$vd_prv_key,$str_hash_algo);

  $pttp_sign=$str_hash_algo."withRSA:".$str_resp_sign;
  
  return $pttp_sign;

}

//验证ODIN标识对应用户签名
function authSignatureOfODIN($user_odin_uri,$str_original,$user_sign){
    $arr_result = array('code' => 500, 'msg' => '未知错误. Unknown authentication error!');
    
    //获取公钥
    $tmp_user_info = getPubUserInfo($user_odin_uri);
    $array_authentication=$tmp_user_info['authentication'];

    if( !is_array($array_authentication) || count($array_authentication)==0 ){
        $arr_result = array('code' => 501, 'msg' => '没有获得对应身份验证设置. Invalid authentication!');
    }else{
        //验证签名
        $array_sign_chunks=explode(':',$user_sign); 
        $str_sign_type = $array_sign_chunks[0];
        $str_sign_value = $array_sign_chunks[1];
        
        //echo "DEBUG : SIGN_TYPE=$str_sign_type";
            
        foreach($array_authentication as $tmp_authentication_set){
            if( $str_sign_type==PPK_SIGN_TYPE_BITCOIN_SIGNMSG ){ 
                if($tmp_authentication_set['type']==DID_KEY_TYPE_ECC_SECP256K1){
                    //用比特币签名算法验证签名
                    $str_pubkey = $tmp_authentication_set['publicKeyHex'];
                    $tmp_check_url=PTTP_NODE_API_URL.'check_sign.php?pubkey='.urlencode($str_pubkey).'&sign='.urlencode($str_sign_value).'&algo='.urlencode($str_sign_type).'&original='.urlencode($str_original);

                    $result=trim(file_get_contents($tmp_check_url));

                    if(strcasecmp($result,'OK')==0){
                        //验证通过
                        $arr_result = array('code' => 0, 'msg' => 'Authentication OK');
                    }
                }
            }else { //用RSA算法验证
               $str_pubkey = $tmp_authentication_set['publicKeyPem'];
               if( strlen($str_pubkey)>0 
                && rsaVerify($str_original, $str_pubkey, $str_sign_value,$str_sign_type)){
                   //验证通过
                   $arr_result = array('code' => 0, 'msg' => 'Authentication OK'); 
               }
            }
        
            if( $arr_result['code']==0)
                break;
        }
        
        if($arr_result['code']!=0){
            $arr_result = array(
                'code' => 503, 
                'msg' => '签名验证未通过. Invalid signature (type='
                        .getSafeEchoTextToPage($str_sign_type)
                        .',size='.strlen($str_sign_value).') !'
            );
        }
    }
    
    return $arr_result;
}

//For generating signature using RSA private key
function rsaSign($data,$strValidationPrvkey,$algo){
    //$p = openssl_pkey_get_private(file_get_contents('private.pem'));
    $p=openssl_pkey_get_private($strValidationPrvkey);
    openssl_sign($data, $signature, $p,$algo);
    openssl_free_key($p);
    return base64_encode($signature);
}


/**
     * Verify the RSA signature
     *
     * @param string $str_original 
     * @param string $str_pubkey 
     * @param string $sign (base64 encoded)
     * @param string $algo (for example: SHA256withRSA)
     *
     * @return bool
     */
function rsaVerify($str_original, $str_pubkey, $sign, $algo )
{
    $res = false;
    $sign = base64_decode($sign);
    
    if(strpos($str_pubkey,"BEGIN PUBLIC KEY")===false){
        $str_pubkey = "-----BEGIN PUBLIC KEY-----\n".$str_pubkey.'-----END PUBLIC KEY-----';
    }
    
    $php_algo='';
    switch($algo){
        case 'SHA256withRSA':
            $php_algo='SHA256';
            break;
        case 'SHA1withRSA':
            $php_algo='SHA1';
            break;
        case 'MD5withRSA':
            $php_algo='MD5';
            break;
        default:
            $php_algo=$algo;
    }
    
    if ($sign !== false && openssl_verify($str_original, $sign, $str_pubkey,$php_algo) == 1) {
        $res = true;
    }

    return $res;
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


//递归获得指定数字短标识的对应字母转义名称组合
$LetterEscapeNumSet = array(0=>"O",1=>"AIL",2=>"BCZ",3=>"DEF",4=>"GH",5=>"JKS",6=>"MN",7=>"PQR",8=>"TUV",9=>"WXY");

function getEscapedListOfShortODIN($short_odin){ 
    if(strlen($short_odin)>5){
        return array($short_odin);
    }
    $listEscaped=array();
    return  getEscapedLettersOfShortODIN($listEscaped,''.$short_odin,0,"");
}

function getEscapedLettersOfShortODIN($listEscaped,$original,$posn,$pref){ 
    Global $LetterEscapeNumSet;
    $tmpNum = 0 + substr($original,$posn,1);

    $tmpLetters=$LetterEscapeNumSet[$tmpNum];
    for($tt=0;$tt<strlen($tmpLetters);$tt++){
      $new_str=$pref.substr($tmpLetters,$tt,1);
      
      if($posn<strlen($original)-1){
        $listEscaped=getEscapedLettersOfShortODIN($listEscaped,$original,$posn+1,$new_str);
      }else{
        $listEscaped[]=$new_str;
      }
    }

    return $listEscaped;
}
