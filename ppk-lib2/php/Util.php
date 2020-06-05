<?php
namespace PPkPub;

/*  Common Funtions */
/* 
常用公共方法
Common Funtions
-- PPkPub.org
-- 2020-04-15
*/

class Util
{
    //判断来访者浏览器类型是否支持以太坊WEB3插件，是返回true，否则返回false
    public static function isBrowserSupportEthWeb3Plugin()
    {
        if( strpos($_SERVER["HTTP_USER_AGENT"],"Mobile") === false && strpos($_SERVER["HTTP_USER_AGENT"],"MicroMessenger") === false && 
           ( strpos($_SERVER["HTTP_USER_AGENT"],"Chrome") || strpos($_SERVER["HTTP_USER_AGENT"],"Firefox") )
            )
            return true;
        else 
            return false;

    }

    public static function getCurrentUrl($includeHost=true) 
    {
       $url='';
       if($includeHost)
       {
           $arrayTmp=explode('/',$_SERVER['SERVER_PROTOCOL']);
           $url.= (STATIC::isHttps()?'https':'http') .'://'.$_SERVER['HTTP_HOST'];
       }
       if (isset($_SERVER['REQUEST_URI'])) {
           $url .= $_SERVER['REQUEST_URI'];
       }
       else {
           $url .= $_SERVER['PHP_SELF'];
           $url .= empty($_SERVER['QUERY_STRING'])?'':'?'.$_SERVER['QUERY_STRING'];
       }
       return $url;
    }

    public static function isHttps(){
        if ( ! empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        {
            return TRUE;
        }
        elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        {
            return TRUE;
        }
        elseif ( ! empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off')
        {
            return TRUE;
        }

        return FALSE;
    }

    //获取当前页面的网址路径
    public static function getCurrentPagePath($includeHost=true) 
    {
       $url=STATIC::getCurrentUrl($includeHost);
       $last_pson = strrpos($url,'/',0);
       if($last_pson===false){
           return $url;
       }else{
           return substr( $url , 0, $last_pson+1);
       }
    }

    public static function startsWith($haystack, $needle)
    {
         $length = strlen($needle);
         return (substr($haystack, 0, $length) === $needle);
    }

    public static function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

    public static function strToHex($string){
        $hex='';
        for ($i=0; $i < strlen($string); $i++){
            $hex .= dechex(ord($string[$i]));
        }
        return $hex;
    }

    public static function hexToStr($hex){
        $string='';
        for ($i=0; $i < strlen($hex)-1; $i+=2){
             $string .= chr(hexdec($hex[$i].$hex[$i+1]));
        }
        return $string;
    }

    /**
     * 将内容进行UNICODE编码得到原始二进制字符串
     * @param string $name 要转换的中文字符串
     * @param string $in_charset 输入中文编码，默认为uft8
     * @param string $out_charset 输出unicode编码，'UCS-2BE'或'UCS-2LE'
     * Linux 服务器上 UCS-2 编码方式与 Winodws 不一致，linux编码为UCS-2BE，windows为UCS-2LE，即big-endian和little-endian
     * @return string
     */
    public static function unicode_encode($name,$in_charset='UTF-8',$out_charset='UCS-2BE')
    {
        $name = iconv($in_charset, $out_charset, $name);
        $len = strlen($name);
        $str = '';
        for ($i = 0; $i < $len - 1; $i = $i + 2){
            $c = $name[$i];
            $c2 = $name[$i + 1];
            if (ord($c) > 0){    // 两个字节的文字
                $str .= $c.$c2;
            }
            else{
                $str .= $c2;
            }
        }
        return $str;
    }
     

    //格式化单位到秒的时间值的显示
    //$timestamp: 自1970年1月1日0时起的秒数
    //$onlydate: 是否只显示日期，缺省为false
    //$sepc_time_zone:指定时区，不指定时，将按照当前已登录用户设定时区->服务器设定时区->北京时区为优先级来依次判断取值
    public static function formatTimestampForView($timestamp,$onlydate=false,$sepc_time_zone=NULL)
    {
       global $g_fUserLogonTimeZone;
       
       if(isset($sepc_time_zone))
          $time_zone=$sepc_time_zone;
       else if(isset($g_fUserLogonTimeZone))
          $time_zone=$g_fUserLogonTimeZone;
       else if(defined('SERVER_TIME_ZONE'))
          $time_zone=SERVER_TIME_ZONE;
       else
          $time_zone=8;
       
       if($onlydate)
           return $timestamp==0? '--------' :gmdate("Y-m-d", $timestamp+$time_zone*3600);
       else
           return $timestamp==0? '--------' :gmdate("Y-m-d H:i", $timestamp+$time_zone*3600);
    }
    /**
     * 友好显示长标识
     * @param $longid 字符串
     * @return string
     */
    public static function friendlyLongID($longid)
    {
        if(strlen($longid)>16){
            return substr($longid,0,9).'...'.substr($longid,strlen($longid)-4);
        }else{
            return $longid;
        }
    }

    /**
     * 友好截短显示过长字符串(UTF8)
     * @param $longstr 字符串
     * @return string
     */
    /*
    public static function friendlyLongStrUTF8($longstr,$max_show_words,$append = false)
    {
        //需要enable mbstring模块
        if(mb_strlen($longstr)>$max_show_words){
            return mb_substr($longstr,0,$max_show_words,'utf-8').($append?'...':'');
        }else{
            return $longstr;
        }
    }
    */

    /**
    * 参考自BugFree 的字符截取函数sysSubStr
    *
    * Return part of a string(Enhance the public static function substr())
    *
    * @author Chunsheng Wang
    * @param string $String the string to cut.
    * @param int $Length the length of returned string.
    * @param booble $Append whether append "...": false|true
    * @return string the cutted string.
    */
    public static function friendlyLongStrUTF8($String,$Length,$Append = false)
    {
        if (strlen($String) <= $Length )
        {
            return $String;
        }
        else
        {
            $I = 0;
            while ($I < $Length)
            {
                $StringTMP = substr($String,$I,1);
                if ( ord($StringTMP) >=224 )
                {
                    $StringTMP = substr($String,$I,3);
                    $I = $I + 3;
                }
                elseif( ord($StringTMP) >=192 )
                {
                    $StringTMP = substr($String,$I,2);
                    $I = $I + 2;
                }
                else
                {
                    $I = $I + 1;
                }
                $StringLast[] = $StringTMP;
            }
            $StringLast = implode("",$StringLast);
            if($Append)
            {
                $StringLast .= "...";
            }
            return $StringLast;
        }
    }


    /**
     * 友好时间显示，支持过去时和未来时
     * @param $timestamp int 时间戳
     * @return null|string
     */
    public static function friendlyTime($timestamp)
    {
        if ($timestamp > time()) {
            $formats = array(
                'DAY' => getLang('还有').' %s'.getLang('天多'),
                'DAY_HOUR' => getLang('还有').' %s'.getLang('天').'%s'.getLang('小时'),
                'HOUR' => getLang('还有').' %s'.getLang('小时'),
                'HOUR_MINUTE' => getLang('还有').' %s'.getLang('小时').'%s'.getLang('分钟'),
                'MINUTE' => getLang('还有').' %s'.getLang('分钟'),
                'MINUTE_SECOND' => getLang('还有').' %s'.getLang('分钟').'%s'.getLang('秒'),
                'SECOND' => getLang('还有').' %s'.getLang('秒'),
            );
            $seconds = $timestamp - time();
        } else {
            $formats = array(
                'DAY' => '%s'.getLang('天前'),
                'DAY_HOUR' => '%s'.getLang('天').'%s'.getLang('小时前'),
                'HOUR' => '%s'.getLang('小时前'),
                'HOUR_MINUTE' => '%s'.getLang('小时').'%s'.getLang('分钟前'),
                'MINUTE' => '%s'.getLang('分钟前'),
                'MINUTE_SECOND' => '%s'.getLang('分钟').'%s'.getLang('秒前'),
                'SECOND' => '%s'.getLang('秒前'),
            );
            $seconds = time() - $timestamp;
        }

        /* 计算出时间差 */

        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);
        $days = floor($hours / 24);

        if ($days > 0) {
            $diffFormat = 'DAY';
        } else {
            $diffFormat = ($hours > 0) ? 'HOUR' : 'MINUTE';
            if ($diffFormat == 'HOUR') {
                $diffFormat .= ($minutes > 0 && ($minutes - $hours * 60) > 0) ? '_MINUTE' : '';
            } else {
                $diffFormat = (($seconds - $minutes * 60) > 0 && $minutes > 0)
                    ? $diffFormat . '_SECOND' : 'SECOND';
            }
        }

        $dateDiff = null;
        switch ($diffFormat) {
            case 'DAY':
                $dateDiff = sprintf($formats[$diffFormat], $days);
                break;
            case 'DAY_HOUR':
                $dateDiff = sprintf($formats[$diffFormat], $days, $hours - $days * 60);
                break;
            case 'HOUR':
                $dateDiff = sprintf($formats[$diffFormat], $hours);
                break;
            case 'HOUR_MINUTE':
                $dateDiff = sprintf($formats[$diffFormat], $hours, $minutes - $hours * 60);
                break;
            case 'MINUTE':
                $dateDiff = sprintf($formats[$diffFormat], $minutes);
                break;
            case 'MINUTE_SECOND':
                $dateDiff = sprintf($formats[$diffFormat], $minutes, $seconds - $minutes * 60);
                break;
            case 'SECOND':
                $dateDiff = sprintf($formats[$diffFormat], $seconds);
                break;
        }
        return $dateDiff;
    }
    //安全获取HTTP传入参数，值类型为字符串
    //可以指定GET和POST数组，缺省为系统默认全局变量
    public static function safeReqChrStr($argvName,$getArgus=null,$postArgus=null){
        if(null==$getArgus) $getArgus=$_GET;
        if(null==$postArgus) $postArgus=$_POST;
        
        $argValue=trim(@$getArgus[$argvName]);
        
        if($argValue=='')
        {
            $argValue=@$postArgus[$argvName];
        }
        
        if (false==get_magic_quotes_gpc()) 
        {
            $newArgValue = addslashes($argValue);
            if(strlen($newArgValue)>0)
                $argValue=$newArgValue;
        }
        return trim($argValue); 
    }

    //安全获取HTTP传入参数，值类型为数字
    //可以指定GET和POST数组，缺省为系统默认全局变量
    public static function safeReqNumStr($argvName,$getArgus=null,$postArgus=null){
        if(null==$getArgus) $getArgus=$_GET;
        if(null==$postArgus) $postArgus=$_POST;
        
        $argValue=trim(@$getArgus[$argvName]);
        if($argValue=="")
        {
            $argValue=@$postArgus[$argvName];
        }

        if(!is_numeric($argValue))
        {
            return "";
        }
        
        return trim($argValue); 
    }

    //获取原始的HTTP传入参数，值类型为字符串。
    //注意出于安全，调用该方法获得结果不能直接用于SQL语句
    //可以指定GET和POST数组，缺省为系统默认全局变量
    public static function originalReqChrStr($argvName,$getArgus=null,$postArgus=null)
    {
        if(null==$getArgus) $getArgus=$_GET;
        if(null==$postArgus) $postArgus=$_POST;
        
        $argValue=trim(@$getArgus[$argvName]);
        
        if($argValue=='')
        {
            $argValue=@$postArgus[$argvName];
        }
        if (true==get_magic_quotes_gpc()) 
        {    //恢复原样的字符串
            $newArgValue = stripslashes($argValue);
            if(strlen($newArgValue)>0)
                $argValue=$newArgValue;
        }
        return trim($argValue); 
    }

    //消除小数点后多余的0
    public static function trimz($s) {  
        $s=explode('.',$s);  
        if (count($s)==2 && ($s[1]=rtrim($s[1],'0'))) return implode('.',$s);  
        return $s[0];  
    }  

    //显示重定向页面内容
    public static function redirect($url,$message)
    {
        echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1" />';
        echo "<p>$message</p>\n";
        echo "<meta http-equiv=\"refresh\" content=\"1;url=$url\">\n";
    }

    //打印错误信息页面并终止处理
    public static function error_exit($url,$message)
    {
        echo "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>";
        echo "<p align=center>$message</p>\n";
        echo "<p align=center><br><input type=button value=' << 返回，重新输入 '  name=B1 onclick='history.back(-1)'></p>";
        echo "<p align=center><br>或者,<a href=\"$url\">点击这里到相关页面</a></p>\n";
        
        global $g_objPageCache;
        if(isset($g_objPageCache))
            $g_objPageCache->write(); 
        
        exit;
    }

    //打印维护中信息页面并终止处理
    public static function maintenance_exit($message)
    {
        echo "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>";
        echo "<p align=center>$message</p>\n";
        
        global $g_objPageCache;
        if(isset($g_objPageCache))
            $g_objPageCache->write(); 
        
        exit;
    }

    //安全输出显示文本内容到网页上
    public static function safeEchoTextToPage($str_user_input){
        echo htmlspecialchars($str_user_input,ENT_QUOTES,"UTF-8");
    }

    //获取可安全显示到网页上的文本内容
    public static function getSafeEchoTextToPage($str_user_input){
        return htmlspecialchars($str_user_input,ENT_QUOTES,"UTF-8");
    }



    //获取到微秒的时间戳
    public static function getCurrentMicroTime()
    {
        $time = explode ( " ", microtime () );
        $time = $time [1] . ($time [0] * 100000000);
        return $time;
    }

    //简单的获取网页方法（可设定超时时间，重试次数）
    public static function  simpleGetPage($url,$timeout=5,$retry=1){
       $opts = array(   
           'http'=>array(   
               'method'=>"GET",   
               'timeout'=>$timeout,//单位秒  
           ),
           'https'=>array(   
               'method'=>"GET",   
               'timeout'=>$timeout,//单位秒  
           )
        ); 
        
        $cnt=0;   
        while($cnt<$retry && ($resp=@file_get_contents($url, false, stream_context_create($opts)))===FALSE) 
            $cnt++;   
        return $resp;  
    }
    
    /**
    * 获取客户端IP地址
    * @param integer $type
    * @return mixed
    */
   public static function getclientip() {
      static $realip = NULL;
        
      if($realip !== NULL){
          return $realip;
      }
      if(isset($_SERVER)){
          if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){ //但如果客户端是使用代理服务器来访问，那取到的就是代理服务器的 IP 地址，而e69da5e6ba90e79fa5e9819331333361313365不是真正的客户端 IP 地址。要想透过代理服务器取得客户端的真实 IP 地址，就要使用 $_SERVER["HTTP_X_FORWARDED_FOR"] 来读取。
              $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
              /* 取X-Forwarded-For中第一个非unknown的有效IP字符串 */
              foreach ($arr AS $ip){
                  $ip = trim($ip);
                  if ($ip != 'unknown'){
                      $realip = $ip;
                      break;
                  }
              }
          }elseif(isset($_SERVER['HTTP_CLIENT_IP'])){//HTTP_CLIENT_IP 是代理服务器发送的HTTP头。如果是"超级匿名代理"，则返回none值。同样，REMOTE_ADDR也会被替换为这个代理服务器的IP。
              $realip = $_SERVER['HTTP_CLIENT_IP'];
          }else{
              if (isset($_SERVER['REMOTE_ADDR'])){ //正在浏览当前页面用户的 IP 地址
                  $realip = $_SERVER['REMOTE_ADDR'];
              }else{
                  $realip = '0.0.0.0';
              }
          }
      }else{
          //getenv环境变量的值
          if (getenv('HTTP_X_FORWARDED_FOR')){//但如果客户端是使用代理服务器来访问，那取到的就是代理服务器的 IP 地址，而不是真正的客户端 IP 地址。要想透过代理服务器取得客户端的真实 IP 地址
              $realip = getenv('HTTP_X_FORWARDED_FOR');
          }elseif (getenv('HTTP_CLIENT_IP')){ //获取客户端IP
              $realip = getenv('HTTP_CLIENT_IP');
          }else{
              $realip = getenv('REMOTE_ADDR');  //正在浏览当前页面用户的 IP 地址
          }
      }
      preg_match("/[\d\.]{7,15}/", $realip, $onlineip);
      $realip = !empty($onlineip[0]) ? $onlineip[0] : '0.0.0.0';
      return $realip;
   }
}
