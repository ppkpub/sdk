<?php
/**
 * 传入用户奥丁号、签名等信息进行登录验证
 */
require_once "common.php";

$qruuid=safeReqChrStr('qruuid');
$user_odin_uri=safeReqChrStr('user_odin_uri');
$auth_txt_hex=safeReqChrStr('auth_txt_hex');
$user_sign = safeReqChrStr('user_sign');
$response_type=safeReqChrStr('response_type');

if(empty($qruuid) )
{
    $qruuid=generateSessionSafeUUID(); 
}

if( !empty($user_odin_uri) ){
    if( !empty($auth_txt_hex)  && !empty($user_sign)){
        $str_original= hexToStr($auth_txt_hex);
        if(strpos($str_original,$qruuid)===false){
            $arr = array('code' => 500, 'msg' => '所签名的内容标识不一致. Invalid auth_txt without same qruuid!');
            echo json_encode($arr);
            exit(-1);
        }
        
        //获取公钥
        $tmp_user_info = getPubUserInfo($user_odin_uri);
        $str_pubkey=$tmp_user_info['pubkey'];

        $user_loginlevel=0;
        if(strlen($str_pubkey)==0 ){
            $arr = array('code' => 501, 'msg' => '没有获得对应公钥. Invalid pubkey!');
            echo json_encode($arr);
            exit(-1);
        }else{
            $user_loginlevel=2;
            
            //验证签名
            $array_sign_chunks=explode(':',$user_sign);
            if($array_sign_chunks[0]=='bitcoin_secp256k1'){ //用比特币签名算法验证签名
                $tmp_check_url=PTTP_NODE_API_URL.'check_sign.php?pubkey='.urlencode($str_pubkey).'&sign='.urlencode($array_sign_chunks[1]).'&algo='.urlencode($array_sign_chunks[0]).'&original='.urlencode($str_original);

                $result=trim(file_get_contents($tmp_check_url));

                if(strcasecmp($result,'OK')!=0){
                    $arr = array('code' => 502, 'msg' => '比特币签名算法验证未通过. Invalid bitcoin signature!');
                    echo json_encode($arr);
                    exit(-1);
                }
            }else if(!rsaVerify($str_original, $str_pubkey, $array_sign_chunks[1],$array_sign_chunks[0])){ //其他默认尝试用RSA算法验证
                $arr = array('code' => 503, 'msg' => 'RSA签名验证未通过. Invalid RSA signature!');
                echo json_encode($arr);
                exit(-1);
            }
        }
    }
    
    //保存登录状态
    $sql = "REPLACE INTO oauth_ppk_login (qruuid,user_odin_uri,user_sign,status_code) values ('$qruuid','$user_odin_uri','$user_sign',$user_loginlevel)";
    $result = @mysqli_query($g_dbLink,$sql);

    if($result===false)
    {
        $arr = array('code' => 504, 'msg' => '无效参数. Invalid argus');
        echo json_encode($arr);
        exit(-1);
    }

    if($response_type=='html'){
        require_once "page_header.inc.php";
        echo '<center><br><br><h3>扫码验证奥丁号通过<br>ODIN verified OK</h3><br><P><font color="#FF7026">',getSafeEchoTextToPage($user_odin_uri),'</font><br><br>请回到所登录设备或网站上继续访问。<br>Please go back the device or page to continue. </p></center>';
    }else{
        $arr = array('code' => 0, 'msg' => '扫码验证通过，请回到所登录设备或网站上继续访问。user_sign verified ok as '.$user_odin_uri);
        echo json_encode($arr);
    }
    exit(0);
}

require_once "page_header.inc.php";

?>

<h3>确认扫码登录</h3>

<form class="form-horizontal"  action="login_verify.php" method="post" id="form_confirm">
<input type="hidden" name="qruuid"  id="qruuid" value="<?php safeEchoTextToPage($qruuid) ;?>">
<input type="hidden" name="auth_txt_hex" id="auth_txt_hex" value="">
<input type="hidden" name="user_sign" id="user_sign" value="">
<input type="hidden" name="response_type" value="html">

<div class="form-group">
    <label for="exist_odin_uri" class="col-sm-2 control-label">用户奥丁号</label>
    <div class="col-sm-10">
      <input type="text" class="form-control"  id="exist_odin_uri" name="user_odin_uri" value="<?php safeEchoTextToPage($user_odin_uri) ;?>"  onchange="getUserOdinInfo();"  >
    </div>
</div>
  
<p align="center"><input type='button' class="btn btn-success"  id="btn_use_exist_odin" value=' 确 认 登 录 ' onclick='authAsOdinOwner();' disabled="true"></p>

<input type=hidden id="user_name" value="" >
<input type=hidden id="user_avtar_url" value="http://ppkpub.org/images/user.png" >
<!--
<p align="center">对应的用户信息设置</p>
<div class="form-group">
    <label for="user_name" class="col-sm-2 control-label">用户昵称</label>
    <div class="col-sm-10">
      <input type=text class="form-control"  id="user_name" value="" >
    </div>
</div>

<div class="form-group">
    <label for="user_avtar_url" class="col-sm-2 control-label">头像URL</label>
    <div class="col-sm-10">
      <input type=text class="form-control"  id="user_avtar_url" value="http://ppkpub.org/images/user.png" >
    </div>
</div>

<div class="form-group">
    <label for="user_avtar_img" class="col-sm-2 control-label">头像预览</label>
    <div class="col-sm-10">
    <img id="user_avtar_img" width="128" height="128" src="http://ppkpub.org/images/user.png" >
    </div>
</div>
</form>
-->
<p align="center">QRUUID: <?php safeEchoTextToPage($qruuid) ;?></p>

<script src="js/common_func.js"></script>
<script type="text/javascript">
var mObjUserInfo;
var mTempDataHex;

window.onload=function(){
    init();
}

function init(){
    console.log("init...");
    if(typeof(PeerWeb) == 'undefined'){ //检查PPk开放协议相关PeerWeb JS接口可用性
        console.log("PeerWeb not valid");
        
        window.location.href = "<?php echo WEIXIN_QR_SERVICE_URL;?>?login_confirm_url=<?php echo urlencode(getCurrentUrl());?>";
    }else{
        console.log("PeerWeb enabled");
        
        var exist_odin_uri=getUserPPkURI(document.getElementById("exist_odin_uri").value);
        if(exist_odin_uri.length==0){
            //读取PPk浏览器内置钱包中缺省用户身份标识
            PeerWeb.getDefaultODIN(
                'callback_getDefaultODIN'  //回调方法名称
            );
        }else{
            getUserOdinInfo();
        }
    }
}

function makeConfirm () {		
    document.getElementById("form_confirm").submit();
}

function callback_getDefaultODIN(status,obj_data){
    if('OK'==status){
        if(obj_data.odin_uri!=null || obj_data.odin_uri.trim().length>0){
            document.getElementById("exist_odin_uri").value=obj_data.odin_uri;
            getUserOdinInfo();
        }
    }else{
        alert("请先在浏览器里配置所要使用的奥丁号！");
    }
}

//兼容DID的用户标识处理，得到以ppk:起始的URI
function getUserPPkURI(user_uri){ 
    if(user_uri.substring(0,"did:ppk:".length).toLowerCase()=="did:ppk:" ) { 
        user_uri=user_uri.substring("did:".length);
    }
    return user_uri;
}

function getUserOdinInfo(){
    //document.getElementById("btn_use_exist_odin").disabled=true;
    var exist_odin_uri=getUserPPkURI(document.getElementById("exist_odin_uri").value);
    //读取用户身份标识URI对应说明
    PeerWeb.getPPkResource(
        exist_odin_uri,
        'content',
        'callback_getUserOdinInfo'  //回调方法名称
    );
}

function callback_getUserOdinInfo(status,obj_data){
    if('OK'==status){
        try{
            var content=window.atob(obj_data.content_base64);
            //var content=obj_data.content_base64;
            //alert("type="+obj_data.type+" \nlength="+obj_data.length+"\nurl="+obj_data.url+"\ncontent="+content);
            mObjUserInfo = JSON.parse(content);
            
            var default_avtar_url='http://ppkpub.org/images/user.png';
            var exist_odin_uri=document.getElementById("exist_odin_uri").value;
            
            if(typeof(mObjUserInfo.attributes) !== 'undefined'){  //DID格式的用户定义
                document.getElementById("user_name").value=mObjUserInfo.attributes.name;
                document.getElementById("user_avtar_url").value=mObjUserInfo.attributes.avtar;
                //document.getElementById('user_avtar_img').src=mObjUserInfo.attributes.avtar;
            }else if(typeof(mObjUserInfo.title) !== 'undefined'){  //直接使用奥丁号的属性
                document.getElementById("user_name").value=mObjUserInfo.title.length>0 ? mObjUserInfo.title : exist_odin_uri ;
                document.getElementById("user_avtar_url").value=default_avtar_url;
                //document.getElementById('user_avtar_img').src=default_avtar_url;
            }else{
                document.getElementById("user_name").value="anonymous";
                document.getElementById("user_avtar_url").value=default_avtar_url;
                //document.getElementById('user_avtar_img').src=default_avtar_url;
            }
            
            document.getElementById("btn_use_exist_odin").disabled=false;
        }catch(e){
            alert("获得的用户信息有误!\n"+e);
        }
    }else{
        alert("无法获取对应用户信息！\n请检查确认下述奥丁号:\n"+document.getElementById("exist_odin_uri").value);
    }
}

function authAsOdinOwner(){
    var exist_odin_uri=getUserPPkURI(document.getElementById("exist_odin_uri").value);
    var requester_uri='<?php echo SERVER_URL;?>';
    var auth_txt=requester_uri+','+exist_odin_uri+','+document.getElementById("qruuid").value;  //需要签名的原文

    //alert('auth_txt:'+auth_txt);
    mTempDataHex = stringToHex(auth_txt);
    document.getElementById("auth_txt_hex").value=mTempDataHex;
    
    //请求用指定资源密钥来生成签名
    PeerWeb.signWithPPkResourcePrvKey(
        exist_odin_uri,
        requester_uri ,
        mTempDataHex,
        'callback_signWithPPkResourcePrvKey'  //回调方法名称
    );
}

function callback_signWithPPkResourcePrvKey(status,obj_data){
    try{
        if('OK'==status){
        
            //alert("res_uri="+obj_data.res_uri+" \nsign="+obj_data.sign+" \algo="+obj_data.algo);
            
            document.getElementById("user_sign").value=obj_data.algo+":"+obj_data.sign;
        
            makeConfirm();
        }else{
            alert("无法签名指定资源！\n请检查确认该资源已配置有效的验证密钥.");
        }
    }catch(e){
        alert("获得的签名信息有误!\n"+e);
    }
}

</script>