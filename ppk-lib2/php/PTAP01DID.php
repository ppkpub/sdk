<?php
namespace PPkPub;

/* 
兼容DID的PTAP01数字身份协议实现
PHP SDK for PTAP01 & DID   
-- PPkPub.org
-- 2020-04-26
*/
//require_once('firebase-php-jwt/JWT.php');

require_once('Util.php');
require_once('ODIN.php');
require_once('PTTP.php');



class PTAP01DID
{
  const DID_URI_PREFIX='did:'; //DID标识前缀，保留，建议更换为 \PPkPub\PTAP01DID::DID_URI_PREFIX
  
  const ODIN_EXT_KEY_DID_DOC ='x_did' ; //用于ODIN标识属性文档中的扩展字段名

  //DID定义的身份验证公钥类型,保留，建议统一更换为 \PPkPub\PTAP01DID::DID_KEY_TYPE_***
  const DID_KEY_TYPE_ECC_SECP256K1 = 'EcdsaSecp256k1VerificationKey2019'; 
  const DID_KEY_TYPE_ED25519 = 'Ed25519VerificationKey2018'; 
  const DID_KEY_TYPE_RSA = 'RsaVerificationKey2018'; 

  //常用的身份验证签名类型，建议统一更换为 \PPkPub\PTAP01DID::PPK_SIGN_TYPE_***
  const PTAP01_SIGN_TYPE_BITCOIN_SIGNMSG_OLD = 'bitcoin_secp256k1'; 
  const PTAP01_SIGN_TYPE_BITCOIN_SIGNMSG = 'BitcoinSignMsg'; 
  const PTAP01_SIGN_TYPE_SHA256_RSA = 'SHA256withRSA';

  /*
  //JWT定义的签名算法类型
  const SIGN_SPEC_JWT        =  'JWT';
  const JWT_ALGO_TYPE_RSA_SHA256 = 'RS256';
    */
    
  //按标识获取用户信息
  public static function  getPubUserInfo($user_odin_uri){
    //if(isset($g_cachedUserInfos[$user_odin]))
    //    return $g_cachedUserInfos[$user_odin];

    $user_odin_uri = ODIN::formatPPkURI($user_odin_uri,true);

    $default_user_info=array(
        'user_odin'=> $user_odin_uri,
        'full_odin_uri'=> "",
        'name'=>"",
        'email'=>"",
        'avtar'=>"image/user.png"
    );
  
    $tmp_content = PTTP::getPPkResource($user_odin_uri);
    //print_r($tmp_content);
    if($tmp_content['status_code']==200){
        $default_user_info['full_odin_uri']=$tmp_content['uri'];
        $tmp_user_info=@json_decode($tmp_content['content'],true);
        //print_r($tmp_user_info);
        $default_user_info['original_content']=$tmp_content['content'];
        if($tmp_user_info!=null){
            if(array_key_exists('@type',$tmp_user_info) && $tmp_user_info['@type']=='PPkDID' ){ //DID格式的用户身份定义
                $default_user_info['name']=$tmp_user_info['attributes']['name'];
                $default_user_info['email']=$tmp_user_info['attributes']['email'];
                $default_user_info['avtar']=$tmp_user_info['attributes']['avtar'];
                $default_user_info['register']=\PPkPub\PTAP02ASSET::COIN_TYPE_BYTOM.$tmp_user_info['attributes']['wallet_address'];
                
                $default_user_info['authentication']=$tmp_user_info['authentication'];
            }else if(array_key_exists(self::ODIN_EXT_KEY_DID_DOC,$tmp_user_info)){ //直接使用奥丁号的属性
                $default_user_info['name']=@$tmp_user_info['title'];
                $default_user_info['email']=@$tmp_user_info['email'];
                
                if(Util::startsWith($tmp_user_info['register'],ODIN::PPK_URI_PREFIX) 
                   || Util::startsWith($tmp_user_info['register'],\PPkPub\PTAP02ASSET::COIN_TYPE_BITCOIN))
                    $default_user_info['register']=$tmp_user_info['register'];
                else
                    $default_user_info['register']=\PPkPub\PTAP02ASSET::COIN_TYPE_BITCOIN.$tmp_user_info['register'];
                
                $default_user_info['authentication']=@$tmp_user_info[self::ODIN_EXT_KEY_DID_DOC]['authentication'];
            }
            
            
        }
        $g_cachedUserInfos[$user_odin_uri]=$default_user_info;
    }
    
    //print_r($default_user_info);
    return $default_user_info;

  }

  //验证ODIN标识对应用户签名,新版本待完善
  public static function authSignatureOfODIN($user_odin_uri,$str_original,$user_sign){
    $arr_result = array('code' => 500, 'msg' => '未知错误. Unknown authentication error!');
    
    //获取公钥
    $tmp_user_info = static::getPubUserInfo($user_odin_uri);
    //echo "PTAP01DID:authSignatureOfODIN($user_odin_uri,$str_original,$user_sign) \n";print_r($tmp_user_info);exit;
    $array_authentication=@$tmp_user_info['authentication'];

    if( !is_array($array_authentication) || count($array_authentication)==0 ){
        $arr_result = array('code' => 501, 'msg' => '没有获得对应身份验证设置. Invalid authentication!');
    }else{
        //验证签名
        $array_sign_chunks=explode(':',$user_sign); 
        $str_sign_type = $array_sign_chunks[0];
        $str_sign_value = $array_sign_chunks[1];
        
        //echo "DEBUG : SIGN_TYPE=$str_sign_type";
            
        foreach($array_authentication as $tmp_authentication_set){
            if( $str_sign_type== self::PTAP01_SIGN_TYPE_BITCOIN_SIGNMSG
             || $str_sign_type== self::PTAP01_SIGN_TYPE_BITCOIN_SIGNMSG_OLD
            ){ 
                if($tmp_authentication_set['type']==self::DID_KEY_TYPE_ECC_SECP256K1){
                    $str_api_url = defined('PPK_API_SERVICE_URL') ? 
                                    PPK_API_SERVICE_URL : 'http://tool.ppkpub.org/ppkapi2/';
                    
                    //用比特币签名算法验证签名
                    $str_pubkey = $tmp_authentication_set['publicKeyHex'];
                    $tmp_check_url=$str_api_url.'check_sign.php?pubkey='.urlencode($str_pubkey).'&sign='.urlencode($str_sign_value).'&algo='.urlencode($str_sign_type).'&original='.urlencode($str_original);

                    $result=trim(@file_get_contents($tmp_check_url));

                    if(strcasecmp($result,'OK')==0){
                        //验证通过
                        $arr_result = array('code' => 0, 'msg' => 'Authentication OK');
                    }
                }
            }else { //用RSA算法验证
               $str_pubkey = @$tmp_authentication_set['publicKeyPem'];
               if( strlen($str_pubkey)>0 
                && static::rsaVerify($str_original, $str_pubkey, $str_sign_value,$str_sign_type)){
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
                        .Util::getSafeEchoTextToPage($str_sign_type)
                        .',size='.strlen($str_sign_value).') !'
            );
        }
    }
    
    return $arr_result;
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
  public static function rsaVerify($str_original, $str_pubkey, $sign, $algo )
  {
    $res = false;
    $sign = base64_decode($sign);
    
    if(strpos($str_pubkey,"BEGIN ")===false && strpos($str_pubkey,"END ")===false ){
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

}
