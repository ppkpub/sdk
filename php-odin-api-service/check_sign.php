<?php
//验证签名
$str_pubkey=trim($_GET['pubkey']);
$str_sign=trim($_GET['sign']);
$str_algo=trim($_GET['algo']);
$str_original=trim($_GET['original']);

$tmp_check_url='http://localhost:9876/checksign?pubkey='.urlencode($str_pubkey).'&sign='.urlencode($str_sign).'&algo='.urlencode($str_algo).'&original='.urlencode($str_original);
//echo $tmp_check_url;
$result=trim(file_get_contents($tmp_check_url));
echo $result;