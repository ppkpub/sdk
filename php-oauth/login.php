<?php
require 'common.php';

$action=$_GET['action'];
if($action=='relogin')
    unsetLogonUser();

$qruuid=generateSessionSafeUUID();
$redirect_url = SERVER_URL.'authorize.php?qruuid='.$qruuid.'&'.$_SESSION['authorize_querystring'];

if(isset($g_logonUserInfo)) {
    header("Location: ".$redirect_url);
    exit;
}

$confirm_url=SERVER_URL.'login_verify.php?qruuid='.urlencode($qruuid);
$poll_url=SERVER_URL.'login_poll.php?qruuid='.urlencode($qruuid);

require_once "page_header.inc.php";
?>
<h2 align=center>开放认证服务-以奥丁号登录</h2>

<div id="loginform_area" style="display:none;">
<form class="form-horizontal">
<div class="form-group">
    <label for="exist_odin_uri" class="col-sm-2 control-label"><?php echo getLang('你的奥丁号');?></label>
    <div class="col-sm-10">
      <input type="text" class="form-control"  id="exist_odin_uri" value="ppk:YourODIN#"  onchange="getUserOdinInfo();" readonly >
    </div>
</div>
<div class="form-group">
    <label for="use_exist_odin" class="col-sm-2 control-label"></label>
    <div class="col-sm-10">
      <input type='button' class="btn btn-success"  id="use_exist_odin" value=' <?php echo getLang('使用支持奥丁号的APP自主验证身份');?> ' onclick='authAsOdinOwner();' disabled="true"><br><br>
    </div>
</div>
</form>
</div>

<div id="qrcode_area" align="center" style="display:none;">
<p><strong><?php echo getLang('请使用支持奥丁号的APP扫码登录（如PPk浏览器、微信等）');?></strong></p>
    <div id="qrcode_img" ></div><br>
<p>或者<a href="<?php echo $confirm_url;?>" target="LocalPPkTool" ><?php echo getLang('网页版钱包工具登录');?></a></p>
</div>
<p align="center">
<font size="-2">(<?php echo getLang('注：需升级到PPkBrowser安卓版0.305以上版本，');?><a href="https://ppkpub.github.io/docs/help_ppkbrowser/#s05"><?php echo getLang('请点击阅读这里的操作说明安装和使用。');?></a><?php echo getLang('更多信息，');?><a href="https://ppkpub.github.io/docs/" target="_blank"><?php echo getLang('可以参考奥丁号和PPk开放协议的资料进一步了解。');?></a>)</font>
</p>

<script src="js/common_func.js"></script>
<script type="text/javascript" src="js/qrcode.js"></script>
<script type="text/javascript">
var mTempDataHex;

window.onload=function(){
    init();
}

function init(){
    console.log("init...");
    if(typeof(PeerWeb) == 'undefined'){ //检查PPk开放协议相关PeerWeb JS接口可用性
        console.log("PeerWeb not valid");
        //alert("PeerWeb not valid. Please visit by PPk Browser For Android v0.2.6 above.");

        //显示扫码登录
        document.getElementById('qrcode_area').style.display="";
        makeQrCode();
    }else{
        console.log("PeerWeb enabled");
        //document.getElementById("use_exist_odin").disabled=false;
        
        //显示登录表单
        document.getElementById('loginform_area').style.display="";
        
        //读取PPk浏览器内置钱包中缺省用户身份标识
        PeerWeb.getDefaultODIN(
            'callback_getDefaultODIN'  //回调方法名称
        );
    }
}

//打开扫码登录
function makeQrCode() {		
    var poll_url='<?php echo $poll_url; ?>';
    var confirm_url='<?php echo $confirm_url; ?>';
    generateQrCodeImg(confirm_url);

    //轮询 查询该qruuid的状态 直到登录成功或者过期(过期这里暂没判断，待完善)
    var interval1= setInterval(function () {
        console.log("Polling "+poll_url);
        $.ajax({
            type: "GET",
            url: poll_url,
            data: {},
            success: function (result) {
                var obj_resp = JSON.parse(result);
                if (obj_resp.code == 0) {
                    //alert('扫码成功（即登录成功），进行跳转.....');
                    //停止轮询
                    clearInterval(interval1);
                    //然后跳转
                    self.location="<?php echo $redirect_url;?>";
                }
            }
        });
    }, 2000);//2秒钟  频率按需求

}

function generateQrCodeImg(str_data){
    var typeNumber = 0;
    var errorCorrectionLevel = 'L';
    var qr = qrcode(typeNumber, errorCorrectionLevel);
    qr.addData(str_data);
    qr.make();
    document.getElementById('qrcode_img').innerHTML = qr.createImgTag();
}

function callback_getDefaultODIN(status,obj_data){
    if('OK'==status){
        if(obj_data.odin_uri!=null || obj_data.odin_uri.trim().length>0){
            document.getElementById("exist_odin_uri").value=obj_data.odin_uri;
            document.getElementById("use_exist_odin").disabled=false;
        }
    }else{
        alert("<?php echo getLang('请先设置所要使用的奥丁号！');?>");
    }
}

//兼容DID的用户标识处理，得到以ppk:起始的URI
function getUserPPkURI(user_uri){ 
    if(user_uri.substring(0,"did:ppk:".length).toLowerCase()=="did:ppk:" ) { 
        user_uri=user_uri.substring("did:".length);
    }
    return user_uri;
}

function authAsOdinOwner(){
    var qruuid='<?php echo $qruuid; ?>';
    
    var exist_odin_uri=getUserPPkURI(document.getElementById("exist_odin_uri").value);
    var requester_uri='<?php echo SERVER_URL;?>';
    var auth_txt=requester_uri+','+exist_odin_uri+','+qruuid;  //需要签名的原文
    //alert('auth_txt:'+auth_txt);
    mTempDataHex = stringToHex(auth_txt);
    
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
            //提交服务器验证签名登录
            var exist_odin_uri=getUserPPkURI(document.getElementById("exist_odin_uri").value);
            confirmExistODIN(
                    exist_odin_uri,
                    mTempDataHex,
                    obj_data.algo+':'+obj_data.sign,
                    "<?php echo getLang('验证用户身份成功');?>\n<?php echo getLang('奥丁号');?>:"+exist_odin_uri
                );
        }else{
            alert("<?php echo getLang('无法签名指定资源！');?>\n<?php echo getLang('请检查确认该资源已配置有效的验证密钥。');?>");
        }
    }catch(e){
        alert("<?php echo getLang('获得的签名信息有误!');?>\n"+e);
    }
}

function confirmExistODIN(user_odin_uri,auth_txt_hex,user_sign,success_info){
    var confirm_url='login_verify.php?user_odin_uri='+encodeURIComponent(user_odin_uri)+'&auth_txt_hex='+auth_txt_hex+'&user_sign='+encodeURIComponent(user_sign);
    //document.getElementById("exist_odin_uri").value=confirm_url;
    $.ajax({
        type: "GET",
        url: confirm_url,
        data: {},
        success: function (result) {
            var obj_resp = JSON.parse(result);
            if (obj_resp.code == 0) {
                if(success_info.length>0)
                    alert(success_info);
                self.location="<?php echo $redirect_url;?>";
            }else{
                alert("<?php echo getLang('用户身份标识签名验证未通过！');?>\n"+result);
            }
        }
    });
}


</script>
</body>
</html>