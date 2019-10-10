<?php
require_once __DIR__."/common.php";

if(!$server->verifyResourceRequest(OAuth2\Request::createFromGlobals())) {
    $server->getResponse()->send();
    die;
}


$token = $server->getAccessTokenData(OAuth2\Request::createFromGlobals());

//echo "User ID associated with this token is {$token['user_id']}";

echo json_encode(array('success'=>true, 'userInfo'=>[
    'user_odin_uri'=> $token['user_id'],
    'username'=> $token['user_id'],
    'avatar'=> 'http://ppkpub.org/images/user.png'
]));