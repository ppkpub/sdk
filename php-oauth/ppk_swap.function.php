<?php
/*      PPK JoyAsset SwapService DEMO         */
/*         PPkPub.org  20180925           */  
/*    Released under the MIT License.     */

require_once('common_func.php');

//获取用户指定币种的钱包地址URI
function getCoinAddressURI($coin_type,$owner_uri){
    $tmp_address = bindedAddress($coin_type,$owner_uri);
    if($tmp_address!=null)
        return $coin_type.$tmp_address;
    
    
    //如果未登记附加地址，则尝试使用该ODIN标识注册者的默认钱包地址
    $tmp_owner_info=getPubUserInfo($owner_uri);
    if(startsWith($tmp_owner_info['register'],$coin_type)){
        return $tmp_owner_info['register'];
    }else if($coin_type==COIN_TYPE_BITCOINCASH){
        if(startsWith($tmp_owner_info['register'],COIN_TYPE_BITCOIN)){
            return COIN_TYPE_BITCOINCASH.removeCoinPrefix($tmp_owner_info['register'],COIN_TYPE_BITCOIN);
        }
    }else if($coin_type==COIN_TYPE_BITCOIN){
        if(startsWith($tmp_owner_info['register'],COIN_TYPE_BITCOIN)){
            return $tmp_owner_info['register'];
        }
    }
        
    return "";
}   


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
function  getPPkResource($ppk_uri){
    if( strcasecmp(substr($ppk_uri,0,strlen(DID_URI_PREFIX)),DID_URI_PREFIX)==0){ //兼容以did:起始的用户标识
        $ppk_uri=substr($ppk_uri,strlen(DID_URI_PREFIX));
    }
    //echo '$ppk_uri=',$ppk_uri;
    $ppk_url=PTTP_NODE_API_URL.'?pttp_interest='.urlencode('{"ver":1,"hop_limit":6,"interest":{"uri":"'.$ppk_uri.'"}}');
    $tmp_ppk_resp_str=file_get_contents($ppk_url);
    //echo '$ppk_url=',$ppk_url,',$tmp_ppk_resp=',$tmp_ppk_resp_str;
    $tmp_obj_resp=@json_decode($tmp_ppk_resp_str,true);
    //print_r($tmp_obj_resp);
    $tmp_data=@json_decode($tmp_obj_resp['data'],true);
    
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
                $default_user_info['name']=@$tmp_user_info['attributes']['name'];
                $default_user_info['email']=@$tmp_user_info['attributes']['email'];
                $default_user_info['avtar']=@$tmp_user_info['attributes']['avtar'];
                $default_user_info['register']=COIN_TYPE_BYTOM.$tmp_user_info['attributes']['wallet_address'];
                $default_user_info['pubkey']=@$tmp_user_info['authentication'][0]['publicKeyPem'];
            }else if(array_key_exists('register',$tmp_user_info)){ //直接使用奥丁号的属性
                $default_user_info['name']=@$tmp_user_info['title'];
                $default_user_info['email']=@$tmp_user_info['email'];
                
                if(startsWith($tmp_user_info['register'],PPK_URI_PREFIX) || startsWith($tmp_user_info['register'],COIN_TYPE_BITCOIN))
                    $default_user_info['register']=$tmp_user_info['register'];
                else
                    $default_user_info['register']=COIN_TYPE_BITCOIN.$tmp_user_info['register'];

                   
                
                $default_user_info['pubkey']= strlen(@$tmp_user_info['vd_set']['pubkey'])>0 
                                             ? $tmp_user_info['vd_set']['pubkey'] : $tmp_user_info['authentication'][0]['publicKeyHex'];
            }
        }
        $g_cachedUserInfos[$user_odin]=$default_user_info;
    }
    return $default_user_info;

}

//按用户BTC地址获取所拥有的ODIN根标识列表
function  getUserOwnedRootODINs($user_btc_address,$start=0,$limit=100){
    $odin_list=array();
    
    if( startsWith($user_btc_address,'bitcoin:')){
        $user_btc_address=substr($user_btc_address,8);
    }

    $ppk_url='http://tool.ppkpub.org/odin/query.php?address='.$user_btc_address.'&start='.$start.'&limit='.$limit;
    $tmp_ppk_resp_str=file_get_contents($ppk_url);
    //echo '$ppk_url=',$ppk_url,',$tmp_ppk_resp=',$tmp_ppk_resp_str;
    $tmp_obj_resp=@json_decode($tmp_ppk_resp_str,true);
    if($tmp_obj_resp['status']=='OK'){
        $odin_list=$tmp_obj_resp['list'];
    }
    
    return $odin_list;
}

//获取拍卖交易状态码对应文字名称
function getStatusLabel($status_code){
    $tmp_status_str=null;
    switch($status_code){
        case PPK_ODINSWAP_STATUS_BID:
            $tmp_status_str = '报价中';
            break;
        case PPK_ODINSWAP_STATUS_ACCEPT:
            $tmp_status_str = '达成意向';
            break;
        case PPK_ODINSWAP_STATUS_PAID:
            $tmp_status_str = '已付款';
            break;
        case PPK_ODINSWAP_STATUS_TRANSFER:
            $tmp_status_str = '拍卖方已发出过户';
            break;
        case PPK_ODINSWAP_STATUS_CANCEL:
            $tmp_status_str = '交易取消';
            break;
        case PPK_ODINSWAP_STATUS_EXPIRED:
            $tmp_status_str = '到期确拍中';
            break;
        case PPK_ODINSWAP_STATUS_NONE:
            $tmp_status_str = '到期流拍';
            break;
        case PPK_ODINSWAP_STATUS_UNCONFIRM:
            $tmp_status_str = '等待确拍超时而流拍';
            break;
        case PPK_ODINSWAP_STATUS_UNPAID:
            $tmp_status_str = '等待支付超时而流拍';
            break;
        case PPK_ODINSWAP_STATUS_FINISH:
            $tmp_status_str = '已完成';
            break;
        case PPK_ODINSWAP_STATUS_LOSE:
            $tmp_status_str = '未中标';
            break;
    }
    
    if($tmp_status_str!=null)
        return getLang($tmp_status_str);
    else
        return getLang('未知').'['.$status_code.']';
} 


//自动更新已到期的拍卖纪录状态
function autoUpdateExpiredSells(){ 
    Global $g_dbLink;
    $nowtime=time();
    
    //更新到期但有效参拍的状态
    $sql_str="update sells,bids set sells.status_code='".PPK_ODINSWAP_STATUS_EXPIRED."',update_utc='".time()."' where sells.end_utc<=".$nowtime." and sells.status_code=".PPK_ODINSWAP_STATUS_BID." and sells.sell_rec_id=bids.sell_rec_id and bids.status_code=".PPK_ODINSWAP_STATUS_BID." ;";
    //echo $sql_str;
    $result=@mysqli_query($g_dbLink,$sql_str);
    
    //更新剩下的到期但无有效参拍的记录状态
    $sql_str="update sells set status_code='".PPK_ODINSWAP_STATUS_NONE."' where end_utc<=".$nowtime." and status_code=".PPK_ODINSWAP_STATUS_BID.";";
    $result=@mysqli_query($g_dbLink,$sql_str);
    
    //更新已达成意向的其它未中标的记录状态
    $sql_str="update bids set status_code='".PPK_ODINSWAP_STATUS_LOSE."' where 	status_code='".PPK_ODINSWAP_STATUS_BID."' and sell_rec_id	in (select sell_rec_id from sells where status_code=".PPK_ODINSWAP_STATUS_ACCEPT.");";
    //echo $sql_str;
    $result=@mysqli_query($g_dbLink,$sql_str);
    
    //更新未及时确拍达成意向的报价状态
    $sql_str="update sells set sells.status_code='".PPK_ODINSWAP_STATUS_UNCONFIRM."' where sells.end_utc<=".($nowtime-PPK_ODINSWAP_OVEETIME_SECONDS)." and sells.status_code=".PPK_ODINSWAP_STATUS_EXPIRED."  ;";
    //echo $sql_str;
    $result=@mysqli_query($g_dbLink,$sql_str);
    
    //更新未及时付款的报价状态
    $sql_str="update sells set sells.status_code='".PPK_ODINSWAP_STATUS_UNPAID."' where sells.accepted_utc<=".($nowtime-PPK_ODINSWAP_OVEETIME_SECONDS)." and sells.status_code=".PPK_ODINSWAP_STATUS_ACCEPT."  ;";
    //echo $sql_str;
    $result=@mysqli_query($g_dbLink,$sql_str);
}

//获取奥丁号配置管理权限对应文字名称
function getOdinAuthSetLabel($set_code){
    switch($set_code){
        case 0:
            return getLang('注册者或管理者任一方都可以修改配置');
        case 1:
            return getLang('只有管理者能修改配置');
        case 2:
            return getLang('注册者和管理者必须共同确认才能修改配置');
        default:
            return getLang('无效设置').'['.$set_code.']';
    }
}

//构建包含报价确认信息的数据对象
function genAcceptBidArray( $source_owner_odin,$source_address_uri, $dest_owner_odin,$dest_address_uri, $asset_id,$full_odin_uri,$coin_type,$bid_amount,$service_uri ){
  global $gArrayCoinTypeSet;
  //组织交易信息数据块
  $str_coin_symbol=getCoinSymbol($coin_type);
  //if($str_coin_symbol!=$coin_type){
  //    $str_coin_symbol = $str_coin_symbol . '('.$coin_type.')';
  //}
  
  $str_data = PPK_ODINSWAP_FLAG  
      .":accepted to sell ODIN[" .$asset_id
      ."] to (".$dest_owner_odin
      .") for ". trimz($bid_amount) 
      ." " . $str_coin_symbol;    

  $tmp_array=array(
    'from_uri' => $source_address_uri,
    'to_uri' => $dest_address_uri,
    'asset_uri' => $coin_type,
    'amount_satoshi' => $gArrayCoinTypeSet[$coin_type]['min_transfer_amount'],
    'fee_satoshi' => $gArrayCoinTypeSet[$coin_type]['base_miner_fee'],
    'data' => $str_data,
    'data_size' => strlen($str_data), //for test
  );

  return $tmp_array;
}

//构建包含支付报价信息的数据对象
function genPayBidArray( $source_owner_odin,$source_address_uri, $dest_owner_odin,$dest_address_uri, $asset_id,$full_odin_uri,$coin_type,$bid_amount,$service_uri ){
  global $gArrayCoinTypeSet;
  
  //组织交易信息数据块
  $str_coin_symbol=getCoinSymbol($coin_type);
  
  $str_data = PPK_ODINSWAP_FLAG  
      .": paid " . trimz($bid_amount) 
      ." ". $str_coin_symbol
      ." to (".$dest_owner_odin
      .") for ODIN[". $asset_id 
      ."]";  
  
  $amount_satoshi = round($bid_amount*pow(10,$gArrayCoinTypeSet[$coin_type]['decimals']));
  
  $tmp_array=array(
    'from_uri' => $source_address_uri,
    'to_uri' => $dest_address_uri,
    'asset_uri' => $coin_type,
    'amount_satoshi' => $amount_satoshi,
    'fee_satoshi' => $gArrayCoinTypeSet[$coin_type]['base_miner_fee'],
    'data' => $str_data, 
    'data_size' => strlen($str_data), //for test
  );
  
  return $tmp_array;
}

//For generating signature(base64 encoded) using RSA private key
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
