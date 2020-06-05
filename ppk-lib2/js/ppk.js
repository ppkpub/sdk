/* 
访问PPk开放协议的JS代码
JS SDK for PPk ODIN&PTTP protocols   
-- PPkPub.org
-- 2020-04-09
*/
 
var PPKLIB = function() {
    // *****************************************************************
    // *** PRIVATE PROPERTIES AND METHODS *******************************
    // *****************************************************************
    
    //是否支持PPk插件
    var _mbSupportPeerWebPlugin = false;
    
    //ODIN根标识解析服务
    var PPK_ROOT_ODIN_API_URL = "https://tool.ppkpub.org/ppkapiv2/";
    
    //用于在当前请求处理过程中缓存获得的PPk数据
    //var gCachedPPkResources={}; 
    
    //解构PPK资源地址
    var _splitPPkURI = function(ppk_uri){
        if(ppk_uri.substring(0,"ppk:".length).toLowerCase()!="ppk:" ) { 
            return null;
        }

        odin_chunks=[];
        parent_odin_path="";
        resource_id="";
        req_resource_versoin="";

        tmp_chunks=ppk_uri.substring("ppk:".length).split("#");
        console.log("tmp_chunks=",JSON.stringify(tmp_chunks));
        if(tmp_chunks.length>=2){
          req_resource_versoin=tmp_chunks[1];
        }

        odin_chunks=tmp_chunks[0].split("/");
        if(odin_chunks.length==1){
          parent_odin_path="";
          resource_id=odin_chunks[0];
        }else{
          resource_id=odin_chunks[odin_chunks.length-1];
          odin_chunks[odin_chunks.length-1]="";
          parent_odin_path=odin_chunks.join("/");
        }

        console.log("odin_chunks=",JSON.stringify(odin_chunks));
        console.log("odin_chunks.length=",odin_chunks.length);
        console.log("resource_id=",resource_id);
        console.log("parent_odin_path=",parent_odin_path);

        return {
                'uri':ppk_uri,
                'parent_odin_path':parent_odin_path,
                'resource_id':resource_id,
                'resource_versoin':req_resource_versoin,
                'odin_chunks':odin_chunks
            };
    };
    
    var _parseJsonObjFromAjaxResult = function(result){
        return typeof result == 'string ' ?  
                                    JSON.parse( result ) : result;
    };


    var _getApPayloadByAjax = function(obj_ppk_uri_info,path_level,parent_odin_setting,callback_function){
        console.log("getApData() obj_ppk_uri_info="+JSON.stringify(obj_ppk_uri_info));
        console.log("path_level="+path_level);
        console.log("parent_odin_setting="+JSON.stringify(parent_odin_setting));
        
        var current_uri=null;
        var str_ap_url=null;
        var is_leaf_resource = ( path_level == obj_ppk_uri_info.odin_chunks.length - 1 );
        var next_parent_odin_path=null;
        
        if(path_level==0){ //根标识
            current_uri = 'ppk:'+ obj_ppk_uri_info.odin_chunks[0] + "#";
            
            if(is_leaf_resource){
                current_uri += obj_ppk_uri_info.resource_versoin;
            }
            
            str_ap_url = PPK_ROOT_ODIN_API_URL+"?pttp_interest="+encodeURIComponent(current_uri);
            next_parent_odin_path=obj_ppk_uri_info.odin_chunks[0]+"/";
        }else { //扩展标识
            current_uri = 'ppk:'+ obj_ppk_uri_info.odin_chunks[0];
            for(kk=1;kk<path_level;kk++){
                current_uri += "/"+obj_ppk_uri_info.odin_chunks[kk];
            }
            
            if(is_leaf_resource){
                current_uri += "/"+obj_ppk_uri_info.resource_id +"#"+obj_ppk_uri_info.resource_versoin;
            }else{
                next_parent_odin_path = current_uri +"/";
                current_uri += "/"+obj_ppk_uri_info.odin_chunks[path_level]+"#";
            }
            
            
            if( !parent_odin_setting.hasOwnProperty("ap_set") ){
                callback_function("Invalid ap_set",{'status_code':503,'status_detail':"Invalid parent ap_set for "+current_uri});
                return;
            }
            
            for(var ap_id in parent_odin_setting.ap_set) {
                tmp_ap_url = parent_odin_setting.ap_set[ap_id]['url'];
                console.log("AP[",ap_id,"]:",tmp_ap_url);
                str_ap_url = tmp_ap_url+"?pttp_interest="+encodeURIComponent(current_uri);
                break; //目前只是用第一个AP,待完善遍历使用全部可能的AP直到获得有效应答
            }
        }

        console.log("current_uri=",current_uri);
        console.log("str_ap_url=",str_ap_url);
        console.log("is_leaf_resource=",is_leaf_resource);
        console.log("next_parent_odin_path=",next_parent_odin_path);
        
        $.ajax({
            type: "GET",
            url: str_ap_url,
            data: {},
            success : function (result) {
                var obj_resp = _parseJsonObjFromAjaxResult(result);

                if(obj_resp==null){
                    callback_function("AP response is null",null);
                    return;
                }
                                    
                obj_payload = JSON.parse( obj_resp.hasOwnProperty("payload")? obj_resp.payload : obj_resp.data ); //兼容旧版本PTTP协议数据
                
                if(obj_payload.status_code==200){
                    var content=obj_payload.content;
                    //var content=window.atob(obj_payload.content_base64);
                    console.log("type=",obj_payload.metainfo.content_type," \nlength=",obj_payload.metainfo.content_length,"\nservice_url=",obj_payload.uri);
                    //console.log("content=",content);
                    
                    //验证签名合法性待完善

                    if(is_leaf_resource){
                        callback_function("OK",obj_payload);
                    }else{
                        next_parent_odin_setting = JSON.parse( obj_payload.content );
                        if(parent_odin_setting!=null){
                            if( !next_parent_odin_setting.hasOwnProperty("ap_set") &&  parent_odin_setting.hasOwnProperty("ap_set") )
                            { //子级未设置有效AP参数时，默认继承上一级
                                next_parent_odin_setting.ap_set = parent_odin_setting.ap_set;
                            }
                            if( !next_parent_odin_setting.hasOwnProperty("vd_set") &&  parent_odin_setting.hasOwnProperty("vd_set")  )
                            { //子级未设置有效验证参数时，默认继承上一级
                                next_parent_odin_setting.vd_set = parent_odin_setting.vd_set;
                            }
                        }
                        _getApPayloadByAjax(obj_ppk_uri_info,path_level+1,next_parent_odin_setting,callback_function);
                    }
                }else{
                    console.log("出错了，请重试！\n"+obj_payload.status_detail);
                    callback_function("PTTP ERROR",null);
                }
            },
            error:function(xhr,state,errorThrown){
                console.log("出错了，请重试！\n"+obj_payload.status_detail);
                callback_function("AJAX ERROR",null);
            }
        });
    };
    
    //获得方法对象的名称
    var _getFuncName = function(callee)
    {
        /*
        Function.prototype.getFuncName = function(){
            return this.name || this.toString().match(/function\s*([^(]*)\(/)[1]
        }
        return _callee.getFuncName();
        */
        
        var _callee = callee.toString().replace(/[\s\?]*/g,""),

        comb = _callee.length >= 50 ? 50 :_callee.length;
        _callee = _callee.substring(0,comb);

        var name = _callee.match(/^function([^\(]+?)\(/);
        if(name && name[1]){

            return name[1];

        }

        var caller = callee.caller,
        _caller = caller.toString().replace(/[\s\?]*/g,"");

        var last = _caller.indexOf(_callee),
        str = _caller.substring(last-30,last);
        name = str.match(/var([^\=]+?)\=/);
        if(name && name[1]){
            return name[1];
        }

        return "anonymous";
    };
    
    //初始化
    var _init = function( ){
        console.log("PPkLib-JS initing");
        if(typeof(PeerWeb) !== 'undefined'){ //检查PPk开放协议相关PeerWeb JS接口可用性
            console.log("PeerWeb enabled");
            _mbSupportPeerWebPlugin=true;
        }else{
            console.log("PeerWeb not valid");
            _mbSupportPeerWebPlugin=false;
        }
    };
    
    _init();
    
    // *****************************************************************
    // *** PUBLIC PROPERTIES AND METHODS *******************************
    // *****************************************************************
    return {
        version: "0.1.20200409", 
        
        // -- UTILITY METHODS ------------------------------------------------------------
        
         
        //检查PPk开放协议相关PeerWeb插件接口可用性, 可用返回true,否则返回false
        isPeerWebPluginEnabled: function(){ 
            return _mbSupportPeerWebPlugin;
        },
        
        //禁止PeerWeb插件接口, 强制使用AJAX方式
        disablePeerWebPlugin: function(){ 
            _mbSupportPeerWebPlugin=false;
        },
        
        //兼容DID的用户标识处理，得到以ppk:起始的URI
        formatPPkURI: function(user_uri){ 
            if(typeof user_uri == "undefined" || user_uri==null || user_uri.length==0)
                return null;
            
            if(user_uri.substring(0,"ppk:".length).toLowerCase()=="ppk:" ) { 
                user_uri = "ppk:"+user_uri.substring("ppk:".length);
            }else if(user_uri.substring(0,"did:ppk:".length).toLowerCase()=="did:ppk:" ) { 
                user_uri = user_uri.substring("did:".length);
            }else{
                user_uri = null ;
            }
            
            return user_uri;
        },
        
        //调用AJAX方式获取PPK网络资源
        loadPPkResourceByAjax: function(ppk_uri,callback,enable_cache = false){
            ppk_uri=this.formatPPkURI(ppk_uri);   

            if(ppk_uri==null){
                callback("Invalid ppk uri",null);
            }

            /*
            if(enable_cache){ //缓存处理
                if(isset(gCachedPPkResources)){
                    if(isset(gCachedPPkResources[ppk_uri])){
                        //echo "Matched cache<br>\n";
                        return gCachedPPkResources[ppk_uri];
                    }
                }else{
                    gCachedPPkResources=array();
                }
            }
            */
            
            var obj_ppk_uri_info = _splitPPkURI(ppk_uri);
            
            console.log("callback=",_getFuncName(callback));

            //递归解析多级路径直到叶子资源
            _getApPayloadByAjax(obj_ppk_uri_info,0,null,callback);

        },
        
        //调用Plugin方式获取PPK网络资源
        loadPPkResourceByPlugin: function(ppk_uri,callback,enable_cache = false){
            if(!_mbSupportPeerWebPlugin){
                callback("Not support plugin",null);
            }
            
            ppk_uri=this.formatPPkURI(ppk_uri);  
            
            if(ppk_uri==null){
                callback("Invalid ppk uri",null);
            }

            PeerWeb.getPPkResource(
                ppk_uri,
                'content',
                _getFuncName(callback)  //回调方法名称
            );

        },
        
        //获取PPk网络资源,自动选用Plugin（优先支持）或AJAX方式
        //所获得结果将作为参数传给指定的callback回调方法处理
        //回调方法参数为status字符串（正常为OK,出错为相应的错误代码）和对应PTTP数据报文的payload对象字段（无法获得数据时为null）
        loadPPkResource: function(ppk_uri,callback,enable_cache = false){
            if(_mbSupportPeerWebPlugin){
                this.loadPPkResourceByPlugin(ppk_uri,callback,enable_cache);
            }else{
                this.loadPPkResourceByAjax(ppk_uri,callback,enable_cache)
            }
        },
    }
}();