//---------------------------------------------------------------------
//
// PPk OAuth SDK for JavaScript
//
// Copyright (c) 2019 PPkPub.org
//
// URL: http://www.ppkpub.org/
//
// Licensed under the MIT license:
//  http://www.opensource.org/licenses/mit-license.php
//
//---------------------------------------------------------------------

var PPkOAuth = function() {

  //---------------------------------------------------------------------
  // PPkOAuth
  //---------------------------------------------------------------------

  /**
   * PPkOAuth
   * @param str_client_id 登记的OAuth应用标识
   * @param str_state_flag 自定义的OAuth特征值
   * @param str_ppk_oauth_service_uri 支持PPk协议的的OAuth服务网址
   * @param str_oauth_redirect_uri 应用的OAuth回调网址
   * @param str_app_api_uri 应用的后端API网址
   */
  var PPkOAuth = function(
        str_client_id, 
        str_state_flag,
        str_ppk_oauth_service_uri,
        str_oauth_redirect_uri,
        str_app_api_uri) {
            
    var _strClientID=str_client_id;
    var _strStateFlag=str_state_flag;
    var _strPPkOAuthServiceURI=str_ppk_oauth_service_uri;
    var _strOAuthRedirectURI=str_oauth_redirect_uri;
    var _strAppApiURI=str_app_api_uri;

    var _this = {};
  
    //打开OAuth方式验证ODIN服务网址
    _this.openOAuthLogin = function() {
        top.location =_strPPkOAuthServiceURI
                +'authorize.php?response_type=code&client_id='
                +_strClientID+'&state='+_strStateFlag
                +'&redirect_uri='+encodeURIComponent(_strOAuthRedirectURI);

    }
  
    //检查是否处于OAuth回调情形
    _this.isOAuthCallback = function(userview_callback){
        var OAuth_state=getQueryString('state');
        if(OAuth_state !=null && OAuth_state == _strStateFlag)
        {
            var oauth_code=getQueryString('code');
            if(oauth_code !=null && oauth_code.length>1)
            {
               console.log( "[PPK_OAUTH] code=" + oauth_code );
               userview_callback(oauth_code); //调用前端页面的提示
            }
        }
    }
  
    //与应用后端交互完成ODIN号授权登录
    _this.verifyOAuthLogin = function(oauth_code,userview_callback) {
        var api_url = _strAppApiURI 
                     + '?code=' + encodeURIComponent(oauth_code)
                     + '&redirect_uri=' + encodeURIComponent(_strOAuthRedirectURI);
        console.log("[PPK_OAUTH] api_url = "+api_url);
        $.ajax({
            type: "get",
            url: api_url,
            data: null,
            success: function (result) {
                console.log('[PPK_OAUTH] api_result='+result);
                
                userview_callback(result);

            }
        });
    }
    
    return _this;
  };
  
  
  //获取网页URL中的参数
  var  getQueryString = function(name){
     var reg = new RegExp("(^|&)"+ name +"=([^&]*)(&|$)");
     var r = window.location.search.substr(1).match(reg);//search,查询？后面的参数，并匹配正则
     if(r!=null)return  unescape(r[2]); return null;
  }

  //获取网页URL前缀（http(s)://域名）
  var getUrlPrefix = function(){ 
    var tmp_url=window.location.protocol+"//"+window.location.host;
    return tmp_url;
  }

  return PPkOAuth; 
}();


(function (factory) {
  if (typeof define === 'function' && define.amd) {
      define([], factory);
  } else if (typeof exports === 'object') {
      module.exports = factory();
  }
}(function () {
    return PPkOAuth;
}));
  
