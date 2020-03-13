<?php
/*          PPK Common Defines            */
/*         PPkPub.org  20200221           */  
/*    Released under the MIT License.     */

define('DID_URI_PREFIX','did:'); //DID标识前缀
define('PPK_URI_PREFIX','ppk:'); //ppk标识前缀
define('PPK_URI_RES_FLAG','#');  //ppk标识资源版本前缀

//常用币种
define('COIN_TYPE_BITCOIN','bitcoin:');   
define('COIN_TYPE_BITCOINCASH','ppk:bch/');   
define('COIN_TYPE_BYTOM','ppk:joy/btm/');   

//DID定义的身份验证公钥类型
define('DID_KEY_TYPE_ECC_SECP256K1','EcdsaSecp256k1VerificationKey2019'); 
define('DID_KEY_TYPE_ED25519','Ed25519VerificationKey2018'); 
define('DID_KEY_TYPE_RSA','RsaVerificationKey2018'); 

//常用的身份验证签名类型
define('PPK_SIGN_TYPE_BITCOIN_SIGNMSG','bitcoin_secp256k1'); 
//define('PPK_SIGN_TYPE_BITCOIN_SIGNMSG','BitcoinSignMsg'); 
define('PPK_SIGN_TYPE_SHA256_RSA','SHA256withRSA');