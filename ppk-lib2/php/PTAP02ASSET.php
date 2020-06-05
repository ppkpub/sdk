<?php
namespace PPkPub;

/* 
PTAP02数字资产命名标识协议
PHP SDK for PTAP02 Digital Asset Naming  
-- PPkPub.org
-- 2020-04-26
*/

require_once('Util.php');
require_once('ODIN.php');
require_once('PTTP.php');

class PTAP02ASSET
{
  //常用数字资产命名示例
  const COIN_TYPE_BITCOIN = 'bitcoin:'; //比特币类型前缀
  const COIN_TYPE_BITCOINCASH = 'ppk:bch/';   
  const COIN_TYPE_BYTOM = 'ppk:joy/btm/';   
  const COIN_TYPE_MOV = 'ppk:joy/mov/';
  const COIN_TYPE_MOVTEST = 'ppk:joy/movtest/';   
  
  
  //Obtain the actual wallet address  after removing the possible URI prefix
  //去掉可能的币链标识前缀后得到实际使用的地址或资产标识
  public static function removeCoinPrefix($address_uri,$coin_type){
    if( strcasecmp($coin_type,'bitcoin') == 0 ){ //Special handling for Bitcoin
        $coin_type=\PPkPub\PTAP02ASSET::COIN_TYPE_BITCOIN;
    }
    $tmp_str = Util::startsWith($address_uri,$coin_type) ? substr($address_uri,strlen($coin_type)):$address_uri ;
    
    if(strrpos($tmp_str,\PPkPub\ODIN::PPK_URI_RESOURCE_MARK) == strlen($tmp_str)-1){
        $tmp_str =substr($tmp_str,0,strlen($tmp_str)-1);
    }
    
    return $tmp_str;
  }
}
