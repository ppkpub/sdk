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

/*
$obj_resp=json_decode($result,true);
if(strcmp($obj_resp['status'],'success')===0){
  echo 'OK';
}else{
  echo 'ERROR';
}
*/

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


/*
url = 'https://blockchain.info/pushtx?cors=true';
        
        // alternatives are:
        // http://eligius.st/~wizkid057/newstats/pushtxn.php (supports non-standard transactions)
        // http://bitsend.rowit.co.uk (defunct)
        // https://btc.com/tools/tx/publish
        // https://insight.bitpay.com/tx/send

        url = prompt(r + 'Press OK to send transaction to:', url);

        if (url != null && url != "") {

            $.post(url, { "hex": tx }, function(data) {
              txSent(data.responseText);
            }).fail(function(jqxhr, textStatus, error) {
              alert( typeof(jqxhr.responseText)=='undefined' ? jqxhr.statusText
                : ( jqxhr.responseText!='' ? jqxhr.responseText : 'No data, probably Access-Control-Allow-Origin error.') );
            });

        }
*/

//http://btmdemo.ppkpub.org/odin/broadcast.php?hex=0100000004fd2180f82404eb1eb95e3306abf9de58832ee821bd597566bae0eacc1133d2f0020000006b483045022100b6c745e6540785c3fe68e19e948a2523c19a83102445faa74e9962dd76d947cf02204b8f0bc3750810baa9a8ad9e5ac8bd283f11beedb12f82c0e35fbbba12960caf0121024e3b96ca0187d3b5f7427fd6cba370735d43070d9b1f45ba7e8e50c69aea83e8fffffffffd2180f82404eb1eb95e3306abf9de58832ee821bd597566bae0eacc1133d2f0000000004a00483045022100a11ba52474110e9beca30167167bb3a9517161f48bb7eeaf8b336bdb34bfda6202203de5bde7f8197e733c285e0618ababdcc54fd5f95bd8aab3c25e41ede4edaf1401ffffffff1b93a91401cc67eacbecdba413bc5ec7bb10bdf6f6f6e4a9e7117bee29f9cb02000000004900473044022053d3d072ddb03f36fcc8ec40399b77a49d2614e79d5bd93a2cf980623d39838302204f188b810e052950574a0b8d9219575e9a954fcfe8907d1a2763377356620c4e01ffffffff6cb4057ba36a55cecf76621ca3391392d3a196fbd4114255e205bafdf571d87a000000004a00483045022100c88377cc71cd3115e381d30927589d8226e4e7e13938af6e681bd8ee9c533f3502201c57caf04860ccb094093ae5fb1138799ccc192c9ec1a4884fa1d6e78d52de9601ffffffff03e803000000000000695121024e3b96ca0187d3b5f7427fd6cba370735d43070d9b1f45ba7e8e50c69aea83e82102d173743cd0d94f64d241d82a42c6ca92327c443e489f3842464a4df118d4920a21031f52543c7b22766572223a312c2261757468223a2230222c22782d746f6f6c2253ae0000000000000000226a203a2250506b54657374302e3830373031222c227469746c65223a22313131227d28230000000000001976a914b4e2b540b202be227031921163c419f9fa2224ce88ac00000000