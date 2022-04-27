#### 一、食用方法
-启动服务

进入bin目录,在命令行执行下面的命令

```javascript
./cli.exe Websocket.php
```

- 浏览器运行 

1.在浏览器创建Websocket连接(把Websocket.js里面的复制出来粘贴到浏览器命令行运行),会返回一个client对象


2.在client对象上注册需要调用的js函数
```javascript
# 假设我们需要通过http调用btoa这个函数,第一个参数随便命名,第二个参数是函数执行的内容
client.registeCall("btoa",function(params){
    return btoa(params);
});

# 会输出一个访问地址,比如这样

[2022/4/24 18:16:01][info]  连接到服务器成功
> client.registeCall("btoa",function(params){
    return window.btoa(params);
});
[2022/4/24 18:16:52][info]  注册函数btoa成功
[2022/4/24 18:16:52][info]  访问地址：http://127.0.0.1:9501/call?group=ef8d3da2-dca4-4236-ba99-82f76a5e1901&action=btoa&input=

# 参数说明
group:客户端分组ID(不用管)

action:注册的需要调用的函数(不用管)

input:调用这个函数传入的参数(需要输入)
```

- 访问地址获取结果
访问上面打印的地址,并传入自定义参数：
  
`http://127.0.0.1:9501?group=df777a58-ff44-41bb-81ce-935b6bea9c25&action=btoa&input="abc"`
最终返回的就是：window.btoa("ss")执行的结果

#### 二、路由

```javascript
/call 调用函数获取返回值

/list 获取当前服务的websocket客户端数量
```
