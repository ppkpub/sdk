<?php
/* 
按BTC地址查询相关ODIN标识数据的接口,通过同步访问ODIN JAVA管理工具的SQLITE数据库来实现
A php json-api service for query ODIN data by BTC address using the sqlite database of ODIN-javatool daemon  
-- PPkPub.org
-- 2019-01-21 Ver0.1
*/
//设置你的ODIN JAVA管理工具的所在路径
//Set the sqlite database of ODIN-javatool daemon. 
define('YOUR_JAVATOOL_PATH','/home/ppk/ppktool'); 

header("Access-Control-Allow-Origin:*"); //允许跨域访问

$str_address=trim($_GET['address']);
if(strlen($str_address)>0){
  $arrayData = queryOdinStatByAddress($str_address);
  echo json_encode($arrayData);
}

function queryOdinStatByAddress($address){
    try{
        $conn = new PDO('sqlite:'.YOUR_JAVATOOL_PATH.'/resources/db/ppk-1.db');
        if (!$conn){
          echo '{"status":"ERROR","desc":"sqlite connect failed"}';
          exit;
        }
    }catch(PDOException $e){
        echo '{"status":"ERROR","desc":"PDOException:'.$e->getMessage().'"}';
        exit;
    }
    
    $arrayStat=array();
    $arrayStat['status']='OK';
    
    $sqlstr = "SELECT count(*) as counter FROM odins where register='$address';";
    
    $sth = $conn->prepare($sqlstr); 
    $sth->execute(); 
    if($row = $sth->fetch(PDO::FETCH_ASSOC)) { 
      $counter=$row['counter'];
      
      $arrayStat['register_num']=$counter;
      
      if($counter>0){
          $sqlstr = "SELECT odins.full_odin,odins.short_odin,odins.register,odins.admin,odins.block_index,blocks.block_hash,blocks.block_time FROM odins,blocks where odins.register='$address' and odins.block_index=blocks.block_index order by short_odin  limit 1;";
          $sth2 = $conn->prepare($sqlstr); 
          $sth2->execute(); 
          if($row_odin = $sth2->fetch(PDO::FETCH_ASSOC)) {
              $arrayStat['first_register_odin']=$row_odin;
          }
          
          $sqlstr = "SELECT odins.full_odin,odins.short_odin,odins.register,odins.admin,odins.block_index,blocks.block_hash,blocks.block_time FROM odins,blocks where odins.register='$address' and odins.block_index=blocks.block_index order by short_odin desc limit 1;";
          $sth2 = $conn->prepare($sqlstr); 
          $sth2->execute(); 
          if($row_odin = $sth2->fetch(PDO::FETCH_ASSOC)) {
              $arrayStat['last_register_odin']=$row_odin;
          }
      }
    }
    
    $tmp_url='https://chain.api.btc.com/v3/address/'.$address;
    $tmp_content=@file_get_contents($tmp_url);
    if(strlen($tmp_content)>0){
        $tmp_array=@json_decode($tmp_content,true);
        if(isset($tmp_array['data'])){
             $arrayStat['balance_satoshi']=$tmp_array['data']['balance'];
             $arrayStat['unconfirmed_tx_count']=$tmp_array['data']['unconfirmed_tx_count'];
             $arrayStat['unspent_tx_count']=$tmp_array['data']['unspent_tx_count'];
             $arrayStat['api_serviver']="btc.com";
        }
    }
    
    if(!isset($arrayStat['balance_satoshi'])){
        $tmp_url='https://blockchain.info/zh-cn/address/'.$address.'?format=json&limit=0';
        $tmp_content=@file_get_contents($tmp_url);
        if(strlen($tmp_content)>0){
            $tmp_array=@json_decode($tmp_content,true);
            if(isset($tmp_array['final_balance'])){
                 $arrayStat['balance_satoshi']=$tmp_array['final_balance'];
                 $arrayStat['api_serviver']="blockchain.info";
            }
        }
    }
    
    if(!isset($arrayStat['balance_satoshi']))
        $arrayStat['balance_satoshi']=0;
    
    /*
    $cache_filename='./cache/'.urlencode($address);
    $tmp_content=@file_get_contents($cache_filename);
    if(strlen($tmp_content)==0){  
        $tmp_url='https://blockchain.info/q/pubkeyaddr/'.$address;
        $tmp_content=@file_get_contents($tmp_url);
        
        if(strlen($tmp_content)>0){
            @file_put_contents($cache_filename,$tmp_content);
        }
    }
    if(strlen($tmp_content)>0){
        $arrayStat['authentication']=array(
            array(
                "type"=>"secp256k1",
                "publicKeyHex"=>$tmp_content,
            )
        );
    }
    */
    return $arrayStat;
}
