<?php
//代理广播已签名的BTC交易
$signed_tx_hex=trim($_GET['hex']);

$tmp_url='https://blockchain.info/pushtx?cors=true';
$tmp_post_data='tx='.$signed_tx_hex;

$result=commonCallBtcApi($tmp_url,$tmp_post_data);

if(strpos($result,'Transaction Submitted')===false){
  echo 'ERROR:'.$result;
}else{
  echo 'OK';
}

function commonCallBtcApi(
         $api_url,    
         $post_data
    )
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $api_url);
    //设置头文件的信息不作为数据流输出
    curl_setopt($curl, CURLOPT_HEADER, 0);
    //设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //设置post方式提交
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    
    //执行命令
    $data = curl_exec($curl);
    //关闭URL请求
    curl_close($curl);

    return $data;
}
