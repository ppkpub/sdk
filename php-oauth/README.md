ODIN标识兼容oAuth的应用端和服务端实现示例

client.php    应用端示例动态页面

client.html   应用端示例静态页面
client_api.html   配合静态页面的后台API实现
js/ppk_oauth_sdk.js  对应JS SDK

index.php     服务端登记入口

authorize.php 服务端调用接口
  如: https://tool2.ppkpub.org/oauth/authorize.php?response_type=code&client_id=testclient&state=testclient&redirect_uri=https://tool2.ppkpub.org/oauth/client.php

  
运行服务端的提示：
1.此服务端示例采用mysql数据库，注意需先导入 db/demo_oauth2_db.sql 初始化相应的数据库表，并相应修改源文件 config/config.inc.php 中的数据库访问参数。
2.需修改源文件 config/config.inc.php 中 CLIENT_URL 和 SERVER_URL 两项设置参数。
3.需修改源文件 client.html 中 mStrOAuthRedirectURI 和  mStrAppApiURI 两项设置参数。

