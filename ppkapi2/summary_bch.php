<?php
/* 
按BCH地址查询相关数据的接口
A php json-api service for query data by BCH address  
-- PPkPub.org
-- 2019-06-14 Ver0.1
*/

$str_address=trim($_GET['address']);
if(strlen($str_address)>0){
  $arrayData = queryDataByAddress($str_address);
  echo json_encode($arrayData);
}

function queryDataByAddress($address){
    
    $arrayStat=array();
    $arrayStat['status']='OK';
    
    
    $arrayStat['balance_satoshi']=0;
    
    $tmp_url='https://bch-chain.api.btc.com/v3/address/'.$address;
    $tmp_content=@file_get_contents($tmp_url);
    if(strlen($tmp_content)>0){
        $tmp_array=@json_decode($tmp_content,true);
        if(isset($tmp_array['data'])){
             $arrayStat['balance_satoshi']=$tmp_array['data']['balance'];
             $arrayStat['unconfirmed_tx_count']=$tmp_array['data']['unconfirmed_tx_count'];
             $arrayStat['unspent_tx_count']=$tmp_array['data']['unspent_tx_count'];
        }
    }
    
    /*
    $tmp_url='https://blockchain.info/zh-cn/address/'.$address.'?format=json&limit=0';
    $tmp_content=@file_get_contents($tmp_url);
    if(strlen($tmp_content)>0){
        $tmp_array=@json_decode($tmp_content,true);
        if(isset($tmp_array['final_balance'])){
             $arrayStat['balance_satoshi']=$tmp_array['final_balance'];
        }
    }
    */
    return $arrayStat;
}
