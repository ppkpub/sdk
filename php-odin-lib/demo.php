<?php
/* 
A php demo for calling ppk odin php library
-- PPkPub.org
-- 2017-09-27 Ver0.1
*/
include('LibPPkODIN.php');

$rootOdinTest='479110.1304';   //ʾ����������һ��ODIN��ʶ
//$rootOdinTest='430983.1406';

$objDemo=new LibPPkODIN();
$objDemo->getRootOdinSet($rootOdinTest);

