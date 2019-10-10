<?php
/**
 * 用于前端登记或获取登录事务号qruuid使用
 */
require_once "ppk_swap.inc.php";

$qruuid=session_id();

/*
$qruuid=safeReqChrStr('qruuid');

if(strlen($qruuid)==0){
    //前端不提供uuid时，由后端生成随机的UUID 用于二维码显示的内容 和 绑定用
    $qruuid = substr(md5(uniqid(mt_rand(), true)),0,15);//生成uuid
}
*/

$sql_str = "REPLACE INTO qrcodelogin (qruuid) VALUES ('". $qruuid ."');";
$result=mysqli_query($g_dbLink,$sql_str);
if($result===false)
{
    echo '无效参数. Invalid argus';
    exit(-1);
}

$post_confirm_url=getCurrentPagePath(true).'login_verify.php?qruuid='.urlencode($qruuid);
$poll_url='login_poll.php?qruuid='.urlencode($qruuid);

$arr = array('code'=> 0, 
             'msg' => 'qruuid registered ok',
             'data'=> array(
                'qruuid'=>$qruuid,
                'poll_url'=>$poll_url,
                'confirm_url'=>$post_confirm_url
             )
            );
echo json_encode($arr);
