<?php
/* 
A php lib for parsing ODIN URI by blockchain.info API
-- PPkPub.org
-- 2017-09-27 Ver0.1
*/
define(PPK_ODIN_MARK_PUBKEY_HEX,'02d173743cd0d94f64d241d82a42c6ca92327c443e489f3842464a4df118d4920a');  //ODINЭ���������������(Testnet): 1PPkT1hoRbnvSRExCeNoP4s1zr61H12bbg 

define(PPK_PUBKEY_TYPE_FLAG_HEX,'03');  //ODINЭ�������Ϣ����ʹ�õĹ�Կ����ǰ׺�ַ���16���ƣ�
define(PPK_PUBKEY_LENGTH,33);  //ODINЭ�������Ϣ����ʹ�õĵ�����Կ����
define(ADDRESSVERSION,"00"); //this  is a hex byte
define(MAX_N , 10);   //����ǩ��1-OF-N�еĲ���N���������������10

define(FUNC_ID_ODIN_REGIST,'R'); 
define(FUNC_ID_ODIN_UPDATE,'U'); 

define(DATA_TEXT_UTF8, 'T'); //normal text in UTF-8
define(DATA_BIN_GZIP , 'G'); //Compressed by gzip
  
define(ODIN_CMD_UPDATE_BASE_INFO , 'BI');
define(ODIN_CMD_UPDATE_AP_SET    , 'AP');
define(ODIN_CMD_UPDATE_VD_SET    , 'VD');
define(ODIN_CMD_CONFIRM_UPDATE   , 'CU');
define(ODIN_CMD_TRANS_REGISTER   , 'TR');  

define(AUTH_MODE_EACH_UPDATE,0); 
define(AUTH_MODE_ONLY_ADMIN_UPDATE,1); 
define(AUTH_MODE_BOTH_UPDATE,2); 

define(IPFS_PROXY_URL,'https://ipfs.io/ipfs/');

class LibPPkODIN{

  public function getRootOdinSet($rootOdin){
    $arrayTemp=explode('.',$rootOdin);

    $blockIndex=intval($arrayTemp[0]);
    $txIndexInBlock=intval($arrayTemp[1]);

    //����blockchain.info�ṩ��HTTP API����ȡָ����������ݡ����API˵�� https://blockchain.info/zh-cn/api/blockchain_api
    $strBlockJson=file_get_contents('https://blockchain.info/zh-cn/block-height/'.$blockIndex.'?format=json');
    $arrayBlock=json_decode($strBlockJson,true);
    //print_r($arrayBlock);

    if(!isset($arrayBlock['blocks'][0]['tx'][$txIndexInBlock])){
        echo 'Invalid ODIN!';
        exit;
    }

    $txRecord=$arrayBlock['blocks'][0]['tx'][$txIndexInBlock];
    $txHash=$txRecord['hash'];
    echo "ODIN[$rootOdin]'s registe transaction hash=$txHash\n";

    //����blockchain.info�ṩ��HTTP API����ȡָ��ע��ODIN��ʶ��Ӧ��������
    $strTxJson=file_get_contents('https://blockchain.info/zh-cn/rawtx/'.$txHash);
    $arrayTx=json_decode($strTxJson,true);
    //print_r($arrayTx);

    $odinRecord=$this->parseOdinTx($arrayTx,$rootOdin);
    
    //��ȡ��ʶ��ظ��²�����¼
    //��ODINЭ��淶����ָ����ʶ��Ӧ�ĸ��¼�¼������Կ
    $update_mark_pubkey_hex=PPK_PUBKEY_TYPE_FLAG_HEX.'1F'.bin2hex(FUNC_ID_ODIN_UPDATE);
    for($kk=0;$kk<strlen($rootOdin);$kk++){
      $update_mark_pubkey_hex.=bin2hex($rootOdin[$kk]);
    }
    for($kk=strlen($update_mark_pubkey_hex);$kk<PPK_PUBKEY_LENGTH*2;$kk+=2){
      $update_mark_pubkey_hex.=bin2hex(' ');
    }
    echo 'update_mark_pubkey_hex=',$update_mark_pubkey_hex,"\n";

    //���ݹ�Կ���ɶ�Ӧ�ı��رҵ�ַ
    $update_mark_address=$this->pubKeyToAddress($update_mark_pubkey_hex);
    echo 'update_mark_address=',$update_mark_address,"\n";
    
    //����blockchain.info�ṩ��HTTP API����ȡ��ظ��½��׵�����
    $strTxJson=file_get_contents('https://blockchain.info/address/'.$update_mark_address.'?format=json&limit=50&offset=0');
    
    $arrayTxs=json_decode($strTxJson,true);
    //print_r($arrayTxs);
    
    for($kk=count($arrayTxs['txs'])-1;$kk>=0;$kk--){
      echo "ODIN[$rootOdin]'s updte transaction hash[$kk]=",$arrayTxs['txs'][$kk]['hash'],",time=",$arrayTxs['txs'][$kk]['time'],"\n";
      
      $odinUpdateRequest=$this->parseOdinTx($arrayTxs['txs'][$kk],$rootOdin);

      $odinRecord=$this->checkUpdateRequest($odinRecord,$odinUpdateRequest);
      
      echo "=============Updated ODIN record ========\n";
      print_r($odinRecord);
      echo "=========================================\n";
    }
  }

  //���ODIN���������Ƿ�Ϸ�
  function checkUpdateRequest($oldOdinRecord,$odinUpdateRequest){
    $newOdinRecord=$oldOdinRecord;

    if(strcmp($odinUpdateRequest['msg_type'],FUNC_ID_ODIN_UPDATE)==0){
      $obj_update_set=json_decode($odinUpdateRequest['msg_content'],true);  
      $update_cmd=$obj_update_set['cmd'];
      $auth_mode=0+$oldOdinRecord['auth'];
      echo 'auth_mode=',$auth_mode,"\n";
      if(strcmp($update_cmd,ODIN_CMD_CONFIRM_UPDATE)==0){ //ȷ�ϸ���
        foreach ( $obj_update_set['tx_list'] as $tx_id){
          $confirm_odin_update_request = $this->getOdinTx($tx_id,$oldOdinRecord['full_odin']);

          echo "confirm_odin_update_request:\n";
          print_r($confirm_odin_update_request);
          
          //���Ȩ��
          if($confirm_odin_update_request!=null){
            $obj_confirm_update_set=json_decode($confirm_odin_update_request['msg_content'],true);  
            
            if( $auth_mode==AUTH_MODE_BOTH_UPDATE
              &&( 
              (strcmp($odinUpdateRequest['source'],$oldOdinRecord['admin'])==0 && strcmp($confirm_odin_update_request['source'],$oldOdinRecord['register'])==0 ) 
              || (strcmp($odinUpdateRequest['source'],$oldOdinRecord['register'])==0 && strcmp($confirm_odin_update_request['source'],$oldOdinRecord['admin'])==0 ) 
              ) ){
              if(strcmp($obj_confirm_update_set['cmd'],ODIN_CMD_TRANS_REGISTER)==0 ){
                //��Ҫת��Ŀ���ȷ��
                $newOdinRecord['register_transing']=true;
              }else if(strcmp($obj_confirm_update_set['cmd'],ODIN_CMD_CONFIRM_UPDATE)!=0 ){
                return $this->updateOdinRecord($newOdinRecord,$confirm_odin_update_request);
              }
            }

            if(strcmp($obj_confirm_update_set['cmd'],ODIN_CMD_TRANS_REGISTER)==0 
              && strcmp($confirm_odin_update_request['dest'], $odinUpdateRequest['source'] )==0 
              && $newOdinRecord['register_transing']===true ){
              //ת��Ŀ��ȷ�Ͻ���
              $newOdinRecord=updateOdinRecord($newOdinRecord,$confirm_odin_update_request);
              unset($newOdinRecord['register_transing']);
            }
          }
        }
      }else if( 
         ($auth_mode==AUTH_MODE_EACH_UPDATE 
          && (strcmp($odinUpdateRequest['source'],$oldOdinRecord['admin'])==0 || strcmp($odinUpdateRequest['source'],$oldOdinRecord['register'])==0 ) )
         ||($auth_mode==AUTH_MODE_ONLY_ADMIN_UPDATE && strcmp($odinUpdateRequest['source'],$oldOdinRecord['admin'])==0)
        ){ //��Ȩ����
        if(strcmp($update_cmd,ODIN_CMD_TRANS_REGISTER)==0){ //ת��ע����
          //��Ҫת��Ŀ���ȷ��
          $newOdinRecord['register_transing']=true;
        }else{
          $newOdinRecord=updateOdinRecord($newOdinRecord,$odinUpdateRequest);
        }
      }
    }

    return $newOdinRecord;
    
  }

  //����ODIN��ʶ��Ϣ
  function updateOdinRecord($oldOdinRecord,$odinUpdateRequest){
    $newOdinRecord=$oldOdinRecord;

    if(strcmp($odinUpdateRequest['msg_type'],FUNC_ID_ODIN_UPDATE)==0){
      $obj_update_set=json_decode($odinUpdateRequest['msg_content'],true);  
      $update_cmd=$obj_update_set['cmd'];
      if(strcmp($update_cmd,ODIN_CMD_UPDATE_BASE_INFO)==0){ //���»�����Ϣ
        if(isset($obj_update_set['title']))
          $newOdinRecord['title']=$obj_update_set['title'];
        if(isset($obj_update_set['email']))
          $newOdinRecord['email']=$obj_update_set['email'];
        if(isset($obj_update_set['auth']))
          $newOdinRecord['auth']=$obj_update_set['auth'];
        
        if(!empty($odinUpdateRequest['dest']))
          $newOdinRecord['admin']=$odinUpdateRequest['dest'];
      }else if(strcmp($update_cmd,ODIN_CMD_UPDATE_AP_SET)==0){ //����AP���ʵ�����
        if(!isset($newOdinRecord['ap_set']))
          $newOdinRecord['ap_set']=array();
        
        foreach ( $obj_update_set['ap_set'] as $ap_id => $ap_record){
          $newOdinRecord['ap_set'][$ap_id]=$ap_record;
        }
      }else if(strcmp($update_cmd,ODIN_CMD_UPDATE_VD_SET)==0){ //��������ǩ����֤����
        $newOdinRecord['vd_set']=$obj_update_set['vd_set'];
        
        $cert_uri=$newOdinRecord['vd_set']['cert_uri'];
        $tmp_uri_segments=explode(':',$cert_uri);
        if( strcasecmp($tmp_uri_segments[0],'ipfs')==0){
          $newOdinRecord['vd_set']['pubkey']=file_get_contents( IPFS_PROXY_URL . $tmp_uri_segments[1] );
        }else{
          $newOdinRecord['vd_set']['pubkey']=file_get_contents($cert_uri);
        }
      }else if(strcmp($update_cmd,ODIN_CMD_TRANS_REGISTER)==0 ){ //ת��ע����
        $newOdinRecord['register']=$odinUpdateRequest['dest'];
      }
    }
    
    return $newOdinRecord;
    
  }


  //��ȡָ�������¼λ�õĽ��׼�¼����ǶODIN��Ϣ
  function getOdinTx($tx_id,$full_odin){
    $arrayTemp=explode('.',$tx_id);

    $blockIndex=intval($arrayTemp[0]);
    $txIndexInBlock=intval($arrayTemp[1]);

    //����blockchain.info�ṩ��HTTP API����ȡָ����������ݡ����API˵�� https://blockchain.info/zh-cn/api/blockchain_api
    $strBlockJson=file_get_contents('https://blockchain.info/zh-cn/block-height/'.$blockIndex.'?format=json');
    $arrayBlock=json_decode($strBlockJson,true);
    //print_r($arrayBlock);

    if(!isset($arrayBlock['blocks'][0]['tx'][$txIndexInBlock])){
        echo 'Invalid ODIN!';
        exit;
    }

    $txRecord=$arrayBlock['blocks'][0]['tx'][$txIndexInBlock];
    $txHash=$txRecord['hash'];

    //����blockchain.info�ṩ��HTTP API����ȡָ��ע��ODIN��ʶ��Ӧ��������
    $strTxJson=file_get_contents('https://blockchain.info/zh-cn/rawtx/'.$txHash);
    $arrayTx=json_decode($strTxJson,true);

    $odinRecord=$this->parseOdinTx($arrayTx,$full_odin);

    return $odinRecord;
  }

  //������ǶODIN��ʶ��Ϣ�ı��رҽ���
  function parseOdinTx($arrayTx,$relate_full_odin){
    $sourceAddress = null;
    $destAddress = null;
    $arrayOdinSet=null;

    $strValidDataInScript='';

    $foundPPkFlag=false;
            
    $outNum=count($arrayTx['out']);
    for ($kk=0;$kk<$outNum;$kk++) {
      $outRecord=$arrayTx['out'][$kk];
      $script = $outRecord['script'];
      
      $tempData=$this->getValidDataFromScript($script,$foundPPkFlag);
      
      if(is_null($tempData)){
        if ($destAddress==null) {
            $destAddress = $outRecord['addr'];
        }
      }else{
          if ($sourceAddress==null) {
            for($nn=MAX_N;$nn>0;$nn--){
              if(isset($outRecord['addr'.$nn])){
                $sourceAddress = $outRecord['addr'.$nn];
                break;
              }
            }
          }
          
          $foundPPkFlag=true;
          $strValidDataInScript=$strValidDataInScript.$tempData;
      }
    }

    echo 'strValidDataInScript=',$strValidDataInScript,"\n";
    if(strlen($strValidDataInScript)==0){
      return null;
    }

    $from=0;
    $msgType=$strValidDataInScript[$from];
    $msgContent="";
    $from ++ ;
    
    if(strcmp($msgType,FUNC_ID_ODIN_UPDATE)==0){
      $tmp_odin=trim(substr($strValidDataInScript,$from,PPK_PUBKEY_LENGTH-3));
      echo "tmp_odin=$tmp_odin\n";
      if(strcmp($tmp_odin,$relate_full_odin)!=0){
        return null;
      }
      
      $from += PPK_PUBKEY_LENGTH-3;
    }
    $msgFormat=$strValidDataInScript[$from];
    $from++;
    $msgLen=ord($strValidDataInScript[$from]);
    $from++;
    
    if($msgLen==0xFD){ //�ɱ䳤������(Varint)
      $msgLen=ord($strValidDataInScript[$from])+ord($strValidDataInScript[$from+1])*256;
      $from+=2;
    }

    echo "msgType=$msgType,msgFormat=$msgFormat,msgLen=$msgLen\n";

    if(strcmp($msgFormat,DATA_TEXT_UTF8)==0) //Normal text
        $msgContent=substr($strValidDataInScript,$from);
    else if(strcmp($msgFormat,DATA_BIN_GZIP)==0) //Gzip compressed
        $msgContent=gzuncompress(substr($strValidDataInScript,$from));
    
    echo "msgContent=$msgContent\n";
    
    //$arrayOdinSet=json_decode($msgContent,true);
    
    if(strcmp($msgType,FUNC_ID_ODIN_REGIST)==0){
      $odinRecord=json_decode($msgContent,true);
      $odinRecord['full_odin']=$relate_full_odin;
      $odinRecord['register']=$sourceAddress;
      $odinRecord['admin']= strlen($destAddress)>0 ? $destAddress : $sourceAddress ;
    }else{
      $odinRecord=array(
          'source'=>$sourceAddress,
          'dest'=>$destAddress,
          'msg_type'=>$msgType,
          'msg_content'=>$msgContent,
      );
    }
    //print_r($odinRecord);
    echo "parseOdinTx() result:\n";
    print_r($odinRecord);
    
    return $odinRecord;
   
  }
  /*
  �ӽ��׵�script�ֶ�����ȡ����Ч����Ƕ����
  */
  function getValidDataFromScript($scriptStr,$foundPPkFlag){
      echo 'getValidDataFromScript():  scriptStr=',$scriptStr,"  foundPPkFlag=$foundPPkFlag\n";
    
      $strValidData="";
          
      $opcode1=hexdec(substr($scriptStr,0,2));
      $opcode2=hexdec(substr($scriptStr,-4,2));
      $opcode3=hexdec(substr($scriptStr,-2));
      
      //echo "opcodes:",$opcode1,',',$opcode2,',',$opcode3,"\n";
      if($opcode1==0x51 && $opcode2>=0x52 && $opcode3==0xAE){  //���ϱ��ر�Э��Ķ���ǩ��(MULTISIG)����
          $multisig_n=$opcode2-0x50;
          
          if(!$foundPPkFlag){
            $from=2;
            $pubkeyLen=hexdec(substr($scriptStr,$from,2))*2;
            $from+=2;
            $pubkeyStr=substr($scriptStr,$from,$pubkeyLen);
            //echo "pubkeyLen1=$pubkeyLen,pubkeyStr=$pubkeyStr\n";
            $from+=$pubkeyLen;

            $pubkeyLen=hexdec(substr($scriptStr,$from,2))*2;
            $from+=2;
            $pubkeyStr=substr($scriptStr,$from,$pubkeyLen);
            $from+=$pubkeyLen;
            //echo "pubkeyLen2=$pubkeyLen,pubkeyStr=$pubkeyStr\n";
            
            if(strcasecmp($pubkeyStr,PPK_ODIN_MARK_PUBKEY_HEX)==0){ //��һ������ǩ���ű���ĵڶ�����Կƥ��ODINЭ��������Կ
              echo "Found ppk mark\n";
              $foundPPkFlag=true;
            }
            
            if($foundPPkFlag){
              for($nn=2;$nn<$multisig_n;$nn++){
                $pubkeyLen=hexdec(substr($scriptStr,$from,2))*2;
                $from+=2;
                $pubkeyStr=substr($scriptStr,$from,$pubkeyLen);
                $from+=$pubkeyLen;
                //echo "pubkeyLen",$nn,"=$pubkeyLen,pubkeyStr=$pubkeyStr\n";
                
                $dataLen=hexdec(substr($pubkeyStr,2,2))*2;
                //echo "dataLen=$dataLen\n";
                
                for($kk=0;$kk<$dataLen;$kk+=2)
                    $strValidData.=chr(hexdec(substr($pubkeyStr,4+$kk,2))); 
              }
            }
          }else{
            $from=2;
            for($nn=0;$nn<$multisig_n;$nn++){
              $pubkeyLen=hexdec(substr($scriptStr,$from,2))*2;
              $from+=2;
              $pubkeyStr=substr($scriptStr,$from,$pubkeyLen);
              $from+=$pubkeyLen;
              //echo "pubkeyLen",$nn,"=$pubkeyLen,pubkeyStr=$pubkeyStr\n";
              
              if($nn>0){//�ӵڶ�������ǩ����ʼ���ű���ĵ�2����Կ��ʼΪ��ЧǶ������
                $dataLen=hexdec(substr($pubkeyStr,2,2))*2;
                for($kk=0;$kk<$dataLen;$kk+=2)
                  $strValidData.=chr(hexdec(substr($pubkeyStr,4+$kk,2))); 
              }
            }
          }
      }elseif( $opcode1==0x6a && $foundPPkFlag ){
          $from=2;
          $pubkeyLen=hexdec(substr($scriptStr,$from,2))*2;
          $from+=2;
          $pubkeyStr=substr($scriptStr,$from,$pubkeyLen);
          //echo "op_return_len=$pubkeyLen,str=$pubkeyStr\n";
          
          for($kk=0;$kk<$pubkeyLen;$kk+=2)
              $strValidData.=chr(hexdec(substr($pubkeyStr,$kk,2))); 
      }
      //echo 'strValidData=',$strValidData,",  foundPPkFlag=$foundPPkFlag\n";
      
      if(!$foundPPkFlag)
        return null;
      
      return $strValidData;

  } 


  function decodeHex($hex)
  {
    $hex=strtoupper($hex);
    $chars="0123456789ABCDEF";
    $return="0";
    for($i=0;$i<strlen($hex);$i++)
    {
      $current=(string)strpos($chars,$hex[$i]);
      $return=(string)bcmul($return,"16",0);
      $return=(string)bcadd($return,$current,0);
    }
    return $return;
  }

  function encodeHex($dec)
  {
    $chars="0123456789ABCDEF";
    $return="";
    while (bccomp($dec,0)==1)
    {
      $dv=(string)bcdiv($dec,"16",0);
      $rem=(integer)bcmod($dec,"16");
      $dec=$dv;
      $return=$return.$chars[$rem];
    }
    return strrev($return);
  }

  function decodeBase58($base58)
  {
    $origbase58=$base58;
    
    $chars="123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
    $return="0";
    for($i=0;$i<strlen($base58);$i++)
    {
      $current=(string)strpos($chars,$base58[$i]);
      $return=(string)bcmul($return,"58",0);
      $return=(string)bcadd($return,$current,0);
    }
    
    $return=$this->encodeHex($return);
    
    //leading zeros
    for($i=0;$i<strlen($origbase58)&&$origbase58[$i]=="1";$i++)
    {
      $return="00".$return;
    }
    
    if(strlen($return)%2!=0)
    {
      $return="0".$return;
    }
    
    return $return;
  }

  function encodeBase58($hex)
  {
    if(strlen($hex)%2!=0)
    {
      die("encodeBase58: uneven number of hex characters");
    }
    $orighex=$hex;
    
    $chars="123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
    $hex=$this->decodeHex($hex);
    $return="";
    while (bccomp($hex,0)==1)
    {
      $dv=(string)bcdiv($hex,"58",0);
      $rem=(integer)bcmod($hex,"58");
      $hex=$dv;
      $return=$return.$chars[$rem];
    }
    $return=strrev($return);
    
    //leading zeros
    for($i=0;$i<strlen($orighex)&&substr($orighex,$i,2)=="00";$i+=2)
    {
      $return="1".$return;
    }
    
    return $return;
  }

  function hash160ToAddress($hash160,$addressversion=ADDRESSVERSION)
  {
    $hash160=$addressversion.$hash160;
    $check=pack("H*" , $hash160);
    $check=hash("sha256",hash("sha256",$check,true));
    $check=substr($check,0,8);
    $hash160=strtoupper($hash160.$check);
    return $this->encodeBase58($hash160);
  }

  function addressToHash160($addr)
  {
    $addr=$this->decodeBase58($addr);
    $addr=substr($addr,2,strlen($addr)-10);
    return $addr;
  }

  function checkAddress($addr,$addressversion=ADDRESSVERSION)
  {
    $addr=$this->decodeBase58($addr);
    if(strlen($addr)!=50)
    {
      return false;
    }
    $version=substr($addr,0,2);
    if(hexdec($version)>hexdec($addressversion))
    {
      return false;
    }
    $check=substr($addr,0,strlen($addr)-8);
    $check=pack("H*" , $check);
    $check=strtoupper(hash("sha256",hash("sha256",$check,true)));
    $check=substr($check,0,8);
    return $check==substr($addr,strlen($addr)-8);
  }

  function hash160($data)
  {
    $data=pack("H*" , $data);
    return strtoupper(hash("ripemd160",hash("sha256",$data,true)));
  }

  //���ָ����Կ��Ӧbas58�����ʽ��ַ
  function pubKeyToAddress($pubkey)
  {
    return $this->hash160ToAddress($this->hash160($pubkey));
  }

  function remove0x($string)
  {
    if(substr($string,0,2)=="0x"||substr($string,0,2)=="0X")
    {
      $string=substr($string,2);
    }
    return $string;
  }
}