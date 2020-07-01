<?php
namespace PPkPub;

/* 
访问PTTP协议网络资源的PHP代码
PHP SDK for getting PTTP URI   
-- PPkPub.org
-- 2020-06-25
*/

require_once('Util.php');
require_once('ODIN.php');

//require_once('firebase-php-jwt/JWT.php');

class PTTP
{
  //PTTP常用定义
  const PROTOCOL_VER = 2;
  
  const SPEC_NONE  =  'none';
  const SPEC_PAST  =  'past.';
  const SPEC_PAST_HEADER_V1_PUBLIC  =  'v1.public.';
  //const SPEC_PAST_HEADER_V2_PUBLIC  =  'v2.public';
  
  const SIGN_MARK_INTEREST  =  'INTEREST';
  const SIGN_MARK_DATA  =  'DATA';
  
  const PTTP_KEY_CACHE_AS_LATEST = "cache_as_latest";
  const PTTP_KEY_PEERS = "peers";
  
  //最新版本的缓存策略取值
  const CACHE_AS_LATEST_PUBLIC = 'public'; 
  const CACHE_AS_LATEST_PRIVATE = 'private'; 
  const CACHE_AS_LATEST_NO_STORE = 'no-store'; 
  const CACHE_AS_LATEST_MAX_AGE = 'max-age='; //可与public和private组合使用，如"public,max-age=30"
  
  //获取PPk资源
  //会自动处理301/302转向得到最终内容
  public static function  getPPkResource($ppk_uri,$hop=1,$enable_cache = true)
  {
    $tmp_data = STATIC::getPPkData($ppk_uri,$enable_cache );
    //echo "PTTP::getPPkResource(".$ppk_uri.") ";print_r($tmp_data);
    $status_code = $tmp_data['status_code'];
    if($status_code==200){
        return  array(
                    'status_code' => 200,
                    'uri' => $tmp_data['uri'],
                    'content' => $tmp_data['content'],
                );
    }else if( ($status_code==301 || $status_code==302) && hop>0  ){
        $redirect_uri = $tmp_data['content'];
        return  getPPkResource($redirect_uri,$hop-1,$enable_cache );
    }else{
        return  array(
                    'status_code' => $status_code,
                    'uri' => @$tmp_data['uri'],
                    'status_detail' => @$tmp_data['metainfo']['status_detail'],
                ); 
    }
  }

  //获取PPk原始数据报文
  //不处理状态
  public static function  getPPkData($ppk_uri,$enable_cache = true){
    global $gArrayCachedPPkResource; //用于在当前请求处理过程中缓存获得的PPk数据,2020-01-03
    
    /*
    if( strcasecmp(substr($ppk_uri,0,strlen(DID_URI_PREFIX)),DID_URI_PREFIX)==0){ //兼容以did:起始的用户标识
        $ppk_uri=substr($ppk_uri,strlen(DID_URI_PREFIX));
    }
    */
    
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
    
    $array_ppk_uri = \PPkPub\ODIN::splitPPkURI($ppk_uri);
    //print_r($array_ppk_uri);
    $odin_chunks=$array_ppk_uri['odin_chunks'];
    $parent_odin_path=$array_ppk_uri['parent_odin_path'];
    $leaf_resource_id=$array_ppk_uri['resource_id'];

    if(strlen($parent_odin_path)==0){//根标识
        $tmp_uri = ODIN::PPK_URI_PREFIX.$leaf_resource_id.ODIN::PPK_URI_RESOURCE_MARK;
        $result = STATIC::getRootOdinSettingByRemoteAPI($tmp_uri);
        
        if($result['code']!=0){
            return array('status_code'=>$result['code'],'status_detail'=>$result['msg']);
        }
        
        return array(
                    'status_code' => 200,
                    'uri' => $tmp_uri,
                    'content' => json_encode($result['setting']),
                );
        
    }else{
        //逐层解析多级扩展标识的父路径
        $parent_ppk_uri="";
        $obj_parent_odin_setting=null;
        for( $kk=0;$kk<count($odin_chunks);$kk++ ){ 
            $tmp_chunk = $odin_chunks[$kk];
            
            //echo '--> parent_ppk_uri=',$parent_ppk_uri,"\n";

            if($obj_parent_odin_setting==null){
                $parent_ppk_uri = ODIN::PPK_URI_PREFIX.$tmp_chunk ;
                
                $result = STATIC::getRootOdinSettingByRemoteAPI($parent_ppk_uri);
        
                if($result['code']!=0){
                    return array('status_code'=>$result['code'],'status_detail'=>$result['msg']);
                }
                
                $obj_parent_odin_setting=$result['setting'];
                
            }else{
                //print_r($obj_parent_odin_setting);
                $parent_ppk_uri .= '/'.$tmp_chunk ;
                $obj_grandparent_odin_set = $obj_parent_odin_setting;
                $obj_parent_odin_setting = null;

                $ap_set=@$obj_grandparent_odin_set->ap_set;
                if( $ap_set==null ){
                    return array('status_code'=>775,'status_detail'=>"invalid ap_set for ".$parent_ppk_uri);
                }
                
                $tmp_uri = $parent_ppk_uri.ODIN::PPK_URI_RESOURCE_MARK;
                //echo 'tmp_uri=',$tmp_uri,"\n";
                
                foreach( $ap_set as $tmp_ap_id => $tmp_ap ){
                    $tmp_ap_url = $tmp_ap->url;
                    //echo 'tmp_ap_url=',$tmp_ap_url,"\n";
                    
                    $tmp_ap_content = STATIC::getApContent( $tmp_ap_url, $tmp_uri );
                    //echo 'tmp_ap_content=',$tmp_ap_content,"\n";
                    
                    if(strlen($tmp_ap_content)>0){
                        $obj_parent_odin_setting = @json_decode($tmp_ap_content,false);
                    }
                    
                    if($obj_parent_odin_setting!=null)
                        break;
                }
                
                if($obj_parent_odin_setting!=null){
                    //检查ap_set和vd_set参数是否存在，如不存在则相应自动继承使用更上一级父标识的ap_set和vd_set
                    if( !is_null(@$obj_parent_odin_setting->ap_set) ){
                        $obj_grandparent_odin_set->ap_set = $obj_parent_odin_setting->ap_set;
                    }
                    if( !is_null(@$parent_odin_settingt->vd_set) ){
                        $obj_grandparent_odin_set->vd_set = $parent_odin_settingt->vd_set;
                    }
                }
                
                $obj_parent_odin_setting=$obj_grandparent_odin_set;
            }
        }
    
    
        //获取叶子资源内容
        $leaf_resource_uri = $parent_ppk_uri.'/'.$leaf_resource_id.ODIN::PPK_URI_RESOURCE_MARK;
        //echo "Try to get leaf resource : ",$leaf_resource_uri,"\n";
        //print_r($obj_parent_odin_setting);

        $ap_set=@$obj_parent_odin_setting->ap_set;
        if( $ap_set==null ){
            return array('status_code'=>775,'status_detail'=>"invalid ap_set for ".$leaf_resource_uri);
        }
        
        $str_ap_data=null;
        foreach( $ap_set as $tmp_ap_id => $tmp_ap ){
            $tmp_ap_url = $tmp_ap->url;
            //echo 'tmp_ap_url=',$tmp_ap_url,"\n";
            
            $str_ap_data = STATIC::getApData( $tmp_ap_url, $leaf_resource_uri );
            if(strlen($str_ap_data)>0){
                break;
            }
            
        }
        //echo '$str_ap_data=',$str_ap_data;
        
        $tmp_data= strlen($str_ap_data)==0 ? null : @json_decode($str_ap_data,true);

        if($tmp_data == null ){
            return array('status_code'=>775,'status_detail'=>"failed to get ap data ");
        }else{
            if($enable_cache)
                $gArrayCachedPPkResource[$ppk_uri]=$tmp_data;
            
            $tmp_metainfo=@json_decode($tmp_data['metainfo'],true);
            
            $tmp_data['status_code']=$tmp_metainfo['status_code'];
            $tmp_data['metainfo']=$tmp_metainfo;
            
            return $tmp_data;
        }
    }
  }

  public static function getApData( $ap_url, $str_resource_uri , $obj_parent_odin_setting = null )
  {
    //$array_pttp_interest=array('ver'=>1,'interest'=>array('uri'=>$str_resource_uri));
    //$str_pttp_interest=json_encode($array_pttp_interest);
    //$tmp_url=$ap_url.'?pttp='.urlencode($str_pttp_interest);
    
    $tmp_url=$ap_url;
    $tmp_scheme = \PPkPub\Util::getUriScheme($ap_url);
    if( $tmp_scheme == "http" || $tmp_scheme == "https" ){
      $tmp_url = $ap_url.'?pttp='.urlencode($str_resource_uri);
    }else if(  $tmp_scheme == 'ppk' ){
      if( \PPkPub\Util::endsWith($ap_url,'/') ) {
        $tmp_url = $ap_url."pttp(".\PPkPub\Util::strToHex($str_resource_uri).")*";
      }
    }
    
    $str_ap_data = Util::fetchUriContent($tmp_url);
    
    //echo 'PTTP::getApData() tmp_url=',$tmp_url,"\n";
    //echo 'PTTP::getApData() str_ap_data=',$str_ap_data,"\n";
    //exit;
    
    //验证签名待加
    
    return $str_ap_data;
  }
  
  public static function getApContent( $ap_url, $str_resource_uri, $obj_parent_odin_setting = null )
  {
    $str_ap_data = static::getApData( $ap_url, $str_resource_uri,$obj_parent_odin_setting );
    //echo "PTTP::getApContent(): str_ap_data=$str_ap_data\n";
    $obj_ap_data = @json_decode($str_ap_data,true);
    //echo "PTTP::getApContent(): obj_ap_data=";print_r($obj_ap_data);
    
    $str_ap_content = @$obj_ap_data['content'];
    
    return $str_ap_content;
  }

  public static function getRootOdinSettingByRemoteAPI($root_odin_uri)
  {
    $str_api_url = defined('PPK_API_SERVICE_URL') ? 
                    PPK_API_SERVICE_URL : 'http://tool.ppkpub.org/ppkapi2/';

    $str_ap_content = STATIC::getApContent( $str_api_url, $root_odin_uri);
	//echo "getRootOdinSettingByRemoteAPI(",$root_odin_uri,") str_api_url=", $str_api_url ," str_ap_content=",$str_ap_content;
 
    $obj_setting=@json_decode($str_ap_content,false);
    
    if($obj_setting==null)
        return array('code'=>775,"msg"=>"Failed to parse the ROOT ODIN!");
                 
    $str_pns_url = trim(@$obj_setting->pns_url);
    
    if(strlen($str_pns_url)>0){
        //设置了标识托管服务
        $obj_setting = STATIC::mergeRootOdinSettingFromPNS($obj_setting, $str_pns_url,$root_odin_uri);
    }
    
    return array('code'=>0,"setting"=>$obj_setting);
    
 }
 
 public static function mergeRootOdinSettingFromPNS($obj_setting, $str_pns_url,$root_odin_uri)
 {
    $str_pns_result = STATIC::getApContent($str_pns_url,$root_odin_uri);
    //echo "str_pns_result=",$str_pns_result,"\n";exit;
    $obj_pns_setting=@json_decode($str_pns_result,false);
    //$array_setting = array_merge($array_setting,$obj_pns_setting);
    
    if(isset($obj_pns_setting)){
        //合并对象属性字段
        $ignore_pns_keys=array('register','admin','auth','pns_url'); //需过滤的敏感基础字段（只以BTC链上数据为准）
        
        foreach($obj_pns_setting as $pns_key => $pns_value) {
            if(!in_array( $pns_key , $ignore_pns_keys))
                $obj_setting->$pns_key = $pns_value; 
        } 
        
        //将"pns_url"字段名改为"from_pns_url"表示已经调用pns解析处理
        $obj_setting->from_pns_url = $str_pns_url;
        unset($obj_setting->pns_url);
        
        //print_r($obj_setting); 
        //echo json_encode($obj_setting);
        //exit(-1);
    }
    return $obj_setting;
 }

}
