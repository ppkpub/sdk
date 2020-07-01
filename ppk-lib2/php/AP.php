<?php
namespace PPkPub;

/* 
AP节点实现相关方法（如处理兴趣包和应答数据包)
A php sdk for processing PTTP interest and responding data   
-- PPkPub.org
-- 2020-06-16
*/

require_once('Util.php');
require_once('ODIN.php');
require_once('PTTP.php');

require_once('paseto-php/vendor/autoload.php');

use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Purpose;
use ParagonIE\Paseto\Keys\AsymmetricSecretKey;
use ParagonIE\Paseto\Keys\AsymmetricPublicKey;
use ParagonIE\Paseto\Protocol\Version1;
use ParagonIE\Paseto\Parsing\PasetoMessage;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\ConstantTime\Binary;

class AP
{
  //最大可直接应答的文件内容大小，单位:字节KB
  const DEFAULT_MAX_FILE_KB = 1024 ; 
  
  //路径属性作为最新版本的缓存控制（缓存时间3600秒即1小时）
  const DIR_CACHE_AS_LATEST = 'public,max-age=3600'; 
  
  //静态文件内容作为最新版本的缓存控制（缓存86400秒约1天），建议用于长时间不变的静态内容
  const FILE_CACHE_AS_LATEST = 'public,max-age=86400';  //31560000秒约1年
  
  //动态内容作为最新版本的缓存控制，缓存时间600秒即10分钟
  //（注意：如果每次调用结果都不一样，且要确保获得都是新内容，则直接采用PTTP定义的no-store取值）
  const DYNAMIC_CACHE_AS_LATEST = 'public,max-age=600'; 

  /*
  Parse PTTP interest
  */
  public static function parsePttpInterest($str_pttp_interest=null)
  { 
    $str_pttp_uri = null;
    $array_interest_option = null;
    
    if($str_pttp_interest==null) //Parse from HTTP GET/POST
        $str_pttp_interest=Util::originalReqChrStr('pttp');
    
    if(strlen($str_pttp_interest)>0){
      //兼容提取兴趣uri
      if(strncasecmp($str_pttp_interest,ODIN::PPK_URI_PREFIX,strlen(ODIN::PPK_URI_PREFIX))==0){
          $str_pttp_uri=$str_pttp_interest;
      }else{
          $array_pttp_interest=json_decode($str_pttp_interest,true);
          if(array_key_exists($array_pttp_interest,"uri") ){ 
              $str_pttp_uri = $array_pttp_interest['uri'];
              $array_interest_option = @$array_pttp_interest['option'];
          }
          
      }
    }
    
    //如果缺少资源定位符，则自动补上
    if(strpos($str_pttp_uri,ODIN::PPK_URI_RESOURCE_MARK) ===false )
        $str_pttp_uri .= ODIN::PPK_URI_RESOURCE_MARK;
    
    //检查URI前缀是否为ppk:
    $str_prefix = substr($str_pttp_uri,0,4);
    if( 0!=strcmp($str_prefix,ODIN::PPK_URI_PREFIX) ){
      if( 0!=strcasecmp($str_prefix,ODIN::PPK_URI_PREFIX ) ){
        //无效前缀
        $str_pttp_uri = '' ;
      }else{
        //更正大小写
        $str_pttp_uri = ODIN::PPK_URI_PREFIX.substr($str_pttp_uri,4);
      }
    }

    return array(
             'uri' => $str_pttp_uri,
             'option' => $array_interest_option
           );
  }
  
  
  
  /*
   Response PTTP Data
  */
  public static function outputPttpData(  
      $str_pttp_data 
  ){
      @ob_clean(); 
      header("Access-Control-Allow-Origin:*");  //允许AJAX跨域调用
      header('Content-Type: text/json; charset=UTF-8');
      header('Cache-Control: no-store');  //禁用HTML的缓存，避免冲突

      echo $str_pttp_data;
  }
  
  public static function respPttpData(  
      $str_resp_uri,
      $str_local_uri,
      $status_code,
      $status_detail,
      $str_content_type,
      $str_resp_content,
      $str_cache_as_latest=null ,
      $array_local_key_set=null 
  ){
      static::outputPttpData(
         static::generatePttpData(
              $str_resp_uri,
              $str_local_uri,
              $status_code,
              $status_detail,
              $str_content_type,
              $str_resp_content,
              $str_cache_as_latest,
              $array_local_key_set
           )
      );
           
  }
  
  

  /*
   应答处理异常状态
  */
  public static function respPttpException( 
        $str_resp_uri, 
        $str_local_uri,
        $status_code,
        $status_detail,
        $str_content_type='text/html',
        $str_resp_content='',
        $str_cache_as_latest=null ,
        $array_local_key_set=null
  ) {
      if($str_cache_as_latest==null)
            $str_cache_as_latest=STATIC::getDefaultCacheAsLast($str_resp_uri);
      
      static::respPttpData(
              $str_resp_uri,
              $str_local_uri,
              $status_code,
              $status_detail,
              $str_content_type,
              $str_resp_content,
              $str_cache_as_latest,
              $array_local_key_set
           );
  }
  
  /*
   应答跳转报文
  */
  public static function respPttpRedirect( 
        $str_req_uri,
        $str_local_uri ,  //根据str_local_uri来自动获取密钥文件,为null时需指定用于签名的密钥参数array_local_key_set
        $str_content_type,
        $str_redirect_uri,  
        $array_local_key_set = null ,
        $redirect_status_code = 302,
        $redirect_status_detail = 'Moved Temporarily'
  ){
      
      if( 0!=strncasecmp($str_redirect_uri,ODIN::PPK_URI_PREFIX,strlen(ODIN::PPK_URI_PREFIX)) ){
          //转向目标地址不是ppk网址时，去掉尾部多出的资源标志符
          $str_redirect_uri;
          if(strrpos($str_redirect_uri,ODIN::PPK_URI_RESOURCE_MARK) == strlen($str_redirect_uri)-1 )
              $str_redirect_uri = substr($str_redirect_uri,0,strlen($str_redirect_uri)-1);
      }
    
      
      static::respPttpData(
              $str_req_uri,
              $str_local_uri,
              $redirect_status_code,
              $redirect_status_detail,
              $str_content_type,
              $str_redirect_uri, //将转向目标地址作为正文内容
              static::getDefaultCacheAsLast($str_req_uri),
              $array_local_key_set
           );
  }
  
  /*
   根据URI生成缺省的最新版本缓存策略
  */
  public static function getDefaultCacheAsLast($str_uri){  
    return strlen(ODIN::getPPkResourceVer($str_uri))>0 ? PTTP::CACHE_AS_LATEST_NO_STORE 
                                                       : self::DYNAMIC_CACHE_AS_LATEST ;
  }

  /*
   生成PTTP应答数据包
  */
  public static function generatePttpData(  
      $str_resp_uri,
      $str_local_uri,
      $status_code,
      $status_detail,
      $str_content_type,
      $str_resp_content,
      $str_cache_as_latest ,
      $array_local_key_set=null,
	  $need_simple=false) 
  {
	$array_metainfo=array(
	      "iat"=> time(),
		  "status_code" => $status_code,
		);
    
    if($str_resp_content==null){
        $str_resp_content = '';
    }
    
    if( strlen($str_cache_as_latest)>0 )
        $array_metainfo[PTTP::PTTP_KEY_CACHE_AS_LATEST] = $str_cache_as_latest;

    //对于非text类型的正文内容都缺省采用base64编码
    if( strcasecmp(substr($str_content_type,0,4),'text') !==0 && strlen($str_resp_content)>0 )   {
      $str_resp_content=base64_encode($str_resp_content);
      $array_metainfo['content_encoding']='base64';
      //$str_resp_content=base64_encode(gzcompress($str_resp_content));
      //$array_metainfo['content_encoding']='gzip';
    }
    
	if(!$need_simple){
      $array_metainfo['status_detail']=$status_detail;
      $array_metainfo['ap_node'] = PPK_AP_NODE_NAME;
	  $array_metainfo['content_type']=$str_content_type;
	  $array_metainfo['content_length']=strlen($str_resp_content);
    }
	
    $obj_data = static::signLocalData($str_resp_uri,$array_metainfo,$str_resp_content,$str_local_uri, $array_local_key_set);
    
    //$requester_ip = Util::getclientip(); 
    //$peers=array("udp:".$requester_ip.":10775");
    //$obj_data[PTTP::PTTP_KEY_PEERS]=$peers;

	return json_encode($obj_data);
  }
  
  //生成PTTP签名
  private static function signLocalData($str_resp_uri,$array_metainfo,$str_resp_content,$str_local_uri, $array_local_key_set=null){
    $parent_key_set=null;
    if(isset($array_local_key_set)){
      $parent_key_set=$array_local_key_set;
    }else{
      //Read the corresponding parent ODIN key
      //读取对应的父级ODIN密钥
      $parent_odin=substr($str_local_uri,strlen(ODIN::PPK_URI_PREFIX));
      $tmp_posn=strrpos($parent_odin,'/');
      if($tmp_posn<1)
      { //无效的资源路径，直接用匿名方式生成数据报文
        $parent_key_set = null;
      }else{
        $parent_odin=substr($parent_odin,0,$tmp_posn);

        $tmp_posn=strrpos($parent_odin,'/');
        if($tmp_posn>0){
            $up_parent_odin=substr($parent_odin,0,$tmp_posn);
            $up_resource_id=substr($parent_odin,$tmp_posn+1);
        }else{
            $up_parent_odin="";
            $up_resource_id=$parent_odin;
        }
        $parent_key_set = static::getOdinKeySet($up_parent_odin,$up_resource_id);
      }
    }
    
    $str_metainfo = json_encode($array_metainfo);

    //echo "signData() str_payload_length = \n" . strlen($str_payload) . "\n";
    
    if($parent_key_set!=null && strlen(@$parent_key_set['prvkey'])>0){
        //密钥有效时
        try{
            //采用PAST规范v1.public生成带签名的数据报文
            $vd_prv_key=$parent_key_set['prvkey'];
            if(strpos($vd_prv_key,"-BEGIN ")===false && strpos($vd_prv_key,"-END ")===false) //检查私钥是否为PEM格式
                $vd_prv_key = "-----BEGIN PRIVATE KEY-----\n".$vd_prv_key."-----END PRIVATE KEY-----";
            //echo $vd_prv_key,"\n";
            
            $privateKey = new AsymmetricSecretKey($vd_prv_key, new Version1);

            $str_header = PTTP::SPEC_PAST_HEADER_V1_PUBLIC;
            $str_payload = PTTP::SIGN_MARK_DATA.$str_resp_uri.$str_metainfo.$str_resp_content;
            $str_signature = Version1::sign($str_payload, $privateKey , '', true);
           
            $obj_data=array(
              "ver"  => PTTP::PROTOCOL_VER, 
              "spec" =>  PTTP::SPEC_PAST.PTTP::SPEC_PAST_HEADER_V1_PUBLIC,
              "uri" => $str_resp_uri,
              "metainfo" => $str_metainfo,
              "content" => $str_resp_content,
              "signature" => $str_signature,
            );
            
            /*
            //debug verify1
            try{
                $vd_pub_key=$parent_key_set['pubkey'];
                $publicKey = new AsymmetricPublicKey($vd_pub_key, new Version1);
                
                $str_full = $str_header.Base64UrlSafe::encodeUnpadded($str_payload.Base64UrlSafe::decode($str_signature));
                //echo "str_full1 = \n" . $str_full . "\nlength1=",strlen($str_full),"\n";
                
                Version1::verify($str_full, $publicKey );
            } catch (\Exception $e) {
                //签名验证出现异常
                //print_r($e) ;
                $obj_data['debug']="PPkPub::signLocalData() verify failed: ".$e->getMessage();
            }
            
            
            /*
            //debug verify2
            $rsa = Version1::getRsa();
            $keypair = $rsa->createKey(2048);
            print_r($keypair);
            echo "json_encoded_key = \n" . json_encode($keypair) . "\n";
            $privateKey = new AsymmetricSecretKey($keypair['privatekey'], new Version1);
            $str_signature = Version1::sign($str_payload, $privateKey , '', true);
            
            $publicKey = new AsymmetricPublicKey($keypair['publickey'], new Version1);
            $str_full = Version1::sign($str_payload, $privateKey );
            echo "str_full2 = \n" . $str_full . "\nlength2=",strlen($str_full),"\n";
            
            $str_verified = Version1::verify($str_full, $publicKey );
            echo "str_verified = \n" . $str_verified . "\nlength=",strlen($str_verified),"\n";
            exit(-1);
            */

            
            return $obj_data;
        } catch (\Exception $e) {
            //签名出现异常
            //print_r($e) ;
            //echo "PPkPub::signLocalData() excception: ".$e->getMessage();
        }
    }

    //默认返回不带签名的报文
    return array(
              "ver"  => PTTP::PROTOCOL_VER, 
              "spec" =>  PTTP::SPEC_NONE,
              "uri" => $str_resp_uri,
              "metainfo" => $str_metainfo,
              "content" => $str_resp_content,
            );
    
  }

  //Get the signature verification settings corresponding to the requested resource
  //获取请求资源对应的签名验证设置
  private static function getOdinKeySet($parent_odin_path,$resource_id,$resp_resource_versoin=''){
    //echo "getOdinKeySet(): parent_odin_path=$parent_odin_path,resource_id=$resource_id,resp_resource_versoin=$resp_resource_versoin\n";
    
    $key_filename=PPK_AP_KEY_DIR_PREFIX.$parent_odin_path.'/'.$resource_id.'.key.json';
    
    //If the child extension ODIN does not have valid signature setting, try to use the signature setting of the parent ODIN.
    //如果子级扩展ODIN标识没有设定签名参数，则递归采用上一级ODIN标识的签名参数
    if(!file_exists($key_filename)){
      if(strlen($parent_odin_path)==0)
        return null;
      
      $tmp_posn=strrpos($parent_odin_path,'/');
      if($tmp_posn>0){
        $up_parent_odin_path=substr($parent_odin_path,0,$tmp_posn);
        $up_resource_id=substr($parent_odin_path,$tmp_posn+1);
      }else{
        $up_parent_odin_path="";
        $up_resource_id=$parent_odin_path;
      }
      
      return static::getOdinKeySet($up_parent_odin_path,$up_resource_id,'');
    }
    
    return static::getOdinKeySetFromFile($key_filename);

  }

  public static function getOdinKeySetFromFile($key_filename){
    $str_key_set = @file_get_contents($key_filename);
    if(!empty($str_key_set)){
      return json_decode($str_key_set,true);
    }else{
      return null;
    } 
  }

  //Compare resource version number
  private static function cmpResourceVersion($rv1,$rv2){
    //echo $rv1,'  vs ',$rv2,"<br>";
    $tmp_chunks=explode(".",$rv1);
    //print_r($tmp_chunks);
    $major1 =  intval($tmp_chunks[0]);
    $minor1 =  count($tmp_chunks)>1 ? intval($tmp_chunks[1]):0;
    
    $tmp_chunks=explode(".",$rv2);
    //print_r($tmp_chunks);
    $major2 =  intval($tmp_chunks[0]);
    $minor2 =  count($tmp_chunks)>1 ? intval($tmp_chunks[1]):0;
    
    if($major1==$major2 && $minor1==$minor2){
      return 0;
    }if($major1>$major2 || ( $major1==$major2 && $minor1>$minor2 )){
      return 1;
    } else {
      return -1;
    }
    
  }
  //更快速获取静态文件内容的处理方法
  public static function locateStaticResource(
        $parent_odin_path,
        $resource_id,
        $req_resource_versoin
  ){
    if(!file_exists( PPK_AP_RESOURCE_DIR_PREFIX.$parent_odin_path )){
      return array(
              'code'=>404,
              'msg'=>"Not Found : resource_dir($parent_odin_path)  not exist. ",
          );
    }
    
    $matched_resource_filename = null;
    $resp_resource_versoin = '';
    
    //按目录或者文件区分处理资源
    $default_resource_path_and_filename = PPK_AP_RESOURCE_DIR_PREFIX.$parent_odin_path.'/'.$resource_id;
    if( strlen($resource_id)>0 && is_dir($default_resource_path_and_filename) ){ 
        return static::locateDirResource($parent_odin_path, $resource_id,  $req_resource_versoin);
    }else{
        return static::locateFileResource($parent_odin_path, $resource_id,  $req_resource_versoin);
    }

  }
  
  //Locate the specified directory define from the static resource directory
  public static function locateDirResource(
        $parent_odin_path,
        $resource_id,
        $req_resource_versoin
  ){
    $parent_odin_prefix = ( strlen($parent_odin_path)==0  ) ? "":$parent_odin_path.'/';
    
    $str_local_uri = ODIN::PPK_URI_PREFIX.$parent_odin_prefix.$resource_id.ODIN::PPK_URI_RESOURCE_MARK.$req_resource_versoin;
    $default_dir_resource_filename = PPK_AP_RESOURCE_DIR_PREFIX.$parent_odin_prefix.$resource_id;
    
    if(strlen($req_resource_versoin)==0){
        $default_dir_resource_filename .= ".ppk";
        $str_cache_as_latest = self::DIR_CACHE_AS_LATEST;
    }else{
        $default_dir_resource_filename .= "#".$req_resource_versoin."#.ppk";
        $str_cache_as_latest = PTTP::CACHE_AS_LATEST_NO_STORE;
    }
    
    if( file_exists( $default_dir_resource_filename ) ){
        //存在特定的目录资源描述文件
        $dir_resp = static::genStaticResourceResp(
                        $str_local_uri,
                        $default_dir_resource_filename,
                        $str_cache_as_latest
                   );
    }else if( strlen($req_resource_versoin)==0 ){
        //返回缺省的ODIN标识解析数据作为目录资源描述文件，同时其ap_set和vd_set都为空，表示被请求子路径的AP访问参数与父资源标识相同
        $default_odin_set=array(
            "ver"=> PTTP::PROTOCOL_VER,
            "title"=>$resource_id,
        );

        $dir_resp = array(
            'code'=>0,
            'result_data'=>array(
              'local_uri'=>$str_local_uri,
              'content_type'=>'text/json',
              'content'=>json_encode($default_odin_set),
              PTTP::PTTP_KEY_CACHE_AS_LATEST => $str_cache_as_latest,
            )
        );
    }else{
        $dir_resp = array(
              'code'=>404,
              'msg'=>'Direcory resource not exists : '.$parent_odin_prefix.$resource_id.ODIN::PPK_URI_RESOURCE_MARK.$req_resource_versoin ,
          );
    }   

    return $dir_resp;

  }

  //Locate the latest file content from the static resource directory
  public static function locateFileResource(
        $parent_odin_path,
        $resource_id,
        $req_resource_versoin
  ){
    $resource_filename=null;
    $resp_resource_versoin='';
    
    $parent_odin_prefix = ( strlen($parent_odin_path)==0  ) ? "":$parent_odin_path.'/';
    
    //缺省的不带版本控制信息的内容文件名
    $default_resource_filename = $resource_id;
    $default_resource_path_and_filename = PPK_AP_RESOURCE_DIR_PREFIX.$parent_odin_prefix.$default_resource_filename;
    //如果指定了版本，则确定对应的内容文件名
    if(strlen($req_resource_versoin)>0){
        if( filemtime($default_resource_path_and_filename) === 0 + $req_resource_versoin
             && !is_dir($default_resource_path_and_filename) 
             ){
             //所请求默认资源文件存在，且时间戳与请求版本号一致    
             //忽略版本号直接使用默认文件名   
        }else{
            $ext = pathinfo($default_resource_filename, PATHINFO_EXTENSION);
            
            $default_resource_filename .= '#'.$req_resource_versoin.'#';
            if(strlen($ext)>0)
                $default_resource_filename .= '.'.$ext;
            
            $default_resource_path_and_filename = PPK_AP_RESOURCE_DIR_PREFIX.$parent_odin_prefix.$default_resource_filename;
        }
    }
    
    
    //echo "default_resource_path_and_filename=$default_resource_path_and_filename , resource_id=$resource_id \n";

    if( file_exists($default_resource_path_and_filename ) 
        && !is_dir($default_resource_path_and_filename) ){
        //所请求默认资源文件存在
        $resource_filename=$default_resource_filename;
        $resp_resource_versoin=$req_resource_versoin;
        
        if(strlen($resp_resource_versoin)==0)
            $resp_resource_versoin = filemtime($default_resource_path_and_filename) ; //默认使用文件时间戳作为版本
    }else{
        //遍历检索是否存在带有版本控制特征的匹配资源内容
        $d = dir(PPK_AP_RESOURCE_DIR_PREFIX.$parent_odin_path);
        
        while (($filename = $d->read()) !== false){
          //echo "filename: " . $filename . "<br>";
          $tmp_chunks=explode("#",$filename);
          
          if( strcmp($tmp_chunks[0],$resource_id)==0 ){
            if(count($tmp_chunks)>=3){ //文件名里带有类似"#1.0#"有效版本特征信息
                //print_r($tmp_chunks);
                if(strlen($req_resource_versoin)==0){
                  if(strlen(@$resp_resource_versoin)==0){
                    $resp_resource_versoin = $tmp_chunks[1];
                    $resource_filename = $filename;
                  }else if( static::cmpResourceVersion($tmp_chunks[1],$resp_resource_versoin)>0  ){ 
                    $resp_resource_versoin = $tmp_chunks[1];
                    $resource_filename = $filename;
                  }
                }else if( strcmp($tmp_chunks[1],$req_resource_versoin)==0 ){
                  $resp_resource_versoin=$req_resource_versoin;
                  $resource_filename = $filename;
                }
            }
          }
        }
        $d->close();
    
    }

    if(!isset( $resource_filename ) ){ 
        return array(
          'code'=>404,
          'msg'=>"Not existed resource : $parent_odin_prefix$resource_id*$req_resource_versoin ",
        );
    }

    //if(strlen($resp_resource_versoin)==0)
    //  $resp_resource_versoin = '1.0'; //Default static resource version

    $str_local_uri=ODIN::PPK_URI_PREFIX.$parent_odin_prefix.$resource_id."#".$resp_resource_versoin;
    $resource_path_and_filename = PPK_AP_RESOURCE_DIR_PREFIX.$parent_odin_prefix.$resource_filename;
    
    //echo "str_local_uri=$str_local_uri \n";
    //echo "resource_path_and_filename=$resource_path_and_filename , resource_id=$resource_id , resp_resource_versoin=$resp_resource_versoin \n";
    //exit(-1);
    
    $str_cache_as_latest = self::FILE_CACHE_AS_LATEST;
    if(strlen($req_resource_versoin)>0){
        //在指定版本时，目前简单处理，告知请求方不作为最新版本缓存
        //待完善，附加查询是否存在更新版本的属性，再判断是否告知请求方按最新版本缓存
        $str_cache_as_latest =  PTTP::CACHE_AS_LATEST_NO_STORE;
    }

    return static::genStaticResourceResp(
                $str_local_uri,
                $resource_path_and_filename,
                $str_cache_as_latest
           );
  }
  
  
  
  public static function genStaticResourceResp(
        $str_local_uri,
        $resource_path_and_filename,
        $str_cache_as_latest
  ){
    $max_resp_file_kb = defined('MAX_FILE_KB') ? 
                    MAX_FILE_KB : self::DEFAULT_MAX_FILE_KB;
      
    $file_size = @filesize($resource_path_and_filename );
    if( $file_size===false  ){
        return array(
              'code'=>404,
              'msg'=>"Bad Request : static resource not exists. " ,
          );
          
    }else if( $file_size > $max_resp_file_kb*1024){
        return array(
              'code'=>413,
              'msg'=>'Filesize( '.ceil($file_size/1024).' KB) exceed the limit( '.($max_resp_file_kb).' KB)' ,
          );
    }
    


    $str_resp_content=@file_get_contents($resource_path_and_filename);
    
    $ext = pathinfo($resource_path_and_filename, PATHINFO_EXTENSION);

    //Simple process of content type
    if(strlen($ext)==0)
      $str_content_type='application/octet-stream';
    else if( strcasecmp($ext,'jpeg')==0 || strcasecmp($ext,'jpg')==0 || strcasecmp($ext,'gif')==0 || strcasecmp($ext,'png')==0)
      $str_content_type='image/'.$ext;
    else  
      $str_content_type='text/html';
  
    return array(
              'code'=>0,
              'result_data'=>array(
                  'local_uri'=>$str_local_uri,
                  'content_type'=>$str_content_type,
                  'content'=>$str_resp_content,
                  PTTP::PTTP_KEY_CACHE_AS_LATEST =>  $str_cache_as_latest,
              )
          );
  }

}
