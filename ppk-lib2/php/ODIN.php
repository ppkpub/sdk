<?php
namespace PPkPub;

/* 
ODIN标识常用方法（如处理转义英文名称等)
A php sdk for processing ODIN URI   
-- PPkPub.org
-- 2020-04-16
*/

require_once('Util.php');

class ODIN
{
  const PPK_URI_PREFIX  =  'ppk:';
  const PPK_URI_RESOURCE_MARK = '*';

  //递归获得指定数字短标识的对应字母转义名称组合
  private static $LetterEscapeNumSet = 
            array(0=>"O",1=>"AIL",2=>"BCZ",3=>"DEF",4=>"GH",5=>"JKS",6=>"MN",7=>"PQR",8=>"TUV",9=>"WXY");
  
  //将根标识中的英文字母按ODIN标识规范转换成对应数字
  public static function convertLetterToNumberInRootODIN($original_odin){  
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


  

  public static function getEscapedListOfShortODIN($short_odin){ 
    if(strlen($short_odin)>6){
        return array($short_odin);
    }
    $listEscaped=array();
    return  static::getEscapedLettersOfShortODIN($listEscaped,''.$short_odin,0,"");
  }

  public static function getEscapedLettersOfShortODIN($listEscaped,$original,$posn,$pref){ 
    $tmpNum = 0 + substr($original,$posn,1);

    $tmpLetters=static::$LetterEscapeNumSet[$tmpNum];
    for($tt=0;$tt<strlen($tmpLetters);$tt++){
      $new_str=$pref.substr($tmpLetters,$tt,1);
      
      if($posn<strlen($original)-1){
        $listEscaped=static::getEscapedLettersOfShortODIN($listEscaped,$original,$posn+1,$new_str);
      }else{
        $listEscaped[]=$new_str;
      }
    }

    return $listEscaped;
  }


  //解构PPK资源地址
  public static function splitPPkURI($ppk_uri){
    if( 0!=strncasecmp($ppk_uri,self::PPK_URI_PREFIX,strlen(self::PPK_URI_PREFIX)) ){
      return null;
    }
    
    $uri_main_part = substr($ppk_uri,strlen(self::PPK_URI_PREFIX));
    

    $odin_chunks=array();
    $parent_odin_path="";
    $resource_id="";
    $req_resource_versoin="";
    
    $resoure_mark_posn = strrpos($uri_main_part,self::PPK_URI_RESOURCE_MARK); //以最右边的资源标识符为准
    if($resoure_mark_posn===false){
        $req_resource_versoin="";
        $odin_chunks=explode("/",$uri_main_part);
    }else{
        $req_resource_versoin=substr($uri_main_part,$resoure_mark_posn+1) ;
        $odin_chunks=explode("/",substr($uri_main_part,0,$resoure_mark_posn));
    }  
    
    if(count($odin_chunks)==1){
      $parent_odin_path="";
      $resource_id=$odin_chunks[0];
      $odin_chunks=array();
      $req_resource_versoin="";   //根标识本身不能带版本
      
      $format_uri = self::PPK_URI_PREFIX.$resource_id.self::PPK_URI_RESOURCE_MARK;
    }else{
      $resource_id=$odin_chunks[count($odin_chunks)-1];
      unset($odin_chunks[count($odin_chunks)-1]);
      $parent_odin_path=implode('/',$odin_chunks);
      $format_uri = self::PPK_URI_PREFIX.$parent_odin_path.'/'.$resource_id.self::PPK_URI_RESOURCE_MARK.$req_resource_versoin;
    }
    
    return array(
            'format_uri' => $format_uri,
            'parent_odin_path'=>$parent_odin_path,
            'resource_id'=>$resource_id,
            'resource_versoin'=>$req_resource_versoin,
            'odin_chunks'=>$odin_chunks,
        );
  }
  
  //获得PPk URI对应资源版本号，即结尾类似“*1.0”这样的描述，如果没有则返回空字符串
  public static function getPPkResourceVer($ppk_uri){
      $resoure_mark_posn = strrpos($ppk_uri,self::PPK_URI_RESOURCE_MARK); //以最右边的资源标识符为准

      return $resoure_mark_posn===false ? "" : substr($ppk_uri,$resoure_mark_posn+1) ;
  }
  
  //格式化输入URI参数，使之符合ODIN标识定义规范，无效返回null
  //参数prior_add_resource_mark取值true时 优先追加资源标识符（主要用于ID使用时）， 否则根据常用网址规则自动判断添加缺少的"/"字符和资源标志
  public static function formatPPkURI($ppk_uri,$prior_add_resource_mark_for_id=false){
      if($ppk_uri==null || strlen($ppk_uri) == 0 )
        return null;
      
      if( strpos($ppk_uri,'//') !==false ){
        //存在连续的/字符
        return null;
      }
    
      //更新旧版本标识URI
      $old_resoure_mark_posn = strrpos($ppk_uri,'#');
      if($old_resoure_mark_posn !== false){
        $ppk_uri = str_replace('#',static::PPK_URI_RESOURCE_MARK,$ppk_uri);
      }

      //参考ODIN.java 可进一步完善前缀填错不是ppk:的情况
      
      $resoure_mark_posn = strrpos($ppk_uri,static::PPK_URI_RESOURCE_MARK);
      if($resoure_mark_posn===false){
          if(!$prior_add_resource_mark_for_id) {
    		//自动判断先添加缺少的"/"字符
	        $fisrt_slash_posn=strpos($ppk_uri,"/");
	        if($fisrt_slash_posn===false){ //是根标识
	            $ppk_uri .= "/";
	        }else{ //是扩展标识
	            //判断尾部的内容资源名是否有文件扩展名 或者方法标志符
	            $last_slash_posn=strrpos($ppk_uri,"/");
	            
	            if( $last_slash_posn!=strlen($ppk_uri)-1){ //不是以"/"字符结尾
	                $last_point_posn=strrpos($ppk_uri,".");
                    $function_mark_posn = strrpos($ppk_uri,")");
                    
	                if($last_point_posn<$last_slash_posn && $function_mark_posn==false){
	                    //没有文件扩展名或者是方法标志，默认为目录，需要补上"/"
	                    $ppk_uri .= "/";
	                }
	            }
	        }
    	}
    	
    	$ppk_uri .= static::PPK_URI_RESOURCE_MARK;
      }
      
      return $ppk_uri;
  }
}
