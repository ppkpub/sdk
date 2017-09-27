<?php
/* 
A php demo for calling ppk odin php library
-- PPkPub.org
-- 2017-09-27 Ver0.1
*/
include('LibPPkODIN.php');

$rootOdinTest='479110.1304';   //示例所解析的一级ODIN标识
//$rootOdinTest='430983.1406';

$objDemo=new LibPPkODIN();
$objDemo->getRootOdinSet($rootOdinTest);

