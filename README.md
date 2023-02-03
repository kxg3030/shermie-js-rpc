#### 一、使用方法
- 启动服务

进入bin目录,在命令行执行下面的命令(cli.exe的下载去我的另一篇文章：https://learnku.com/articles/67419)

```javascript
./cli.exe Websocket.php
```

- 浏览器运行

1.在浏览器创建Websocket连接(先把Websocket.js文件注入浏览器中)

2.在client对象上注册需要调用的js函数
```javascript
# 假设我们需要通过http调用btoa这个函数,第一个参数随便命名,第二个参数是函数执行的内容，需要自己定义执行内容
let client = (new WebsocketClient("ws://127.0.0.1:9501")).start();
client.registeCall("btoa",function(resolve,params){
    let result = btoa(params);
    resolve(result);
});

# 会输出一个访问地址,比如这样
[2022/4/24 18:16:01][info]  连接到服务器成功
[2022/4/24 18:16:52][info]  注册函数btoa成功
[2022/4/24 18:16:52][info]  访问地址：http://127.0.0.1:9501/call?group=ef8d3da2-dca4-4236-ba99-82f76a5e1901&action=btoa&input=

# 参数说明
group:客户端分组ID(不用管)

action:注册的需要调用的函数(不用管)

input:调用这个函数传入的参数(需要输入)
```

- 获取结果
访问上面打印的地址,并传入自定义参数：`http://127.0.0.1:9501/call?group=df777a58-ff44-41bb-81ce-935b6bea9c25&action=btoa&input="abc"`，最终返回的就是：`window.btoa("ss")`执行的结果

#### 二、动态注入
> 往往加密的参数是在某一个js文件中的某个函数生成的，我们需要做的就是通过断点找到这个加密参数生成的位置，然后动态注入我们的脚本，使用外部的代码进行调用，这里假设你已经找到了关键代码，所以只需要动态注入我们的脚本，这里分为两步进行。

- 替换文件

需要把关键加密的函数加上连接ws的逻辑，保存为新的js文件，然后使用浏览器的override或者fiddler替换加密的js文件，假如我们找到了加密函数

```
function sign(){
    // w函数存在其他地方
    return w(x+y);
}
```

对其进行改造后
```
function sign() {
    // 动态注入js文件
    (function () {
        var newElement = document.createElement("script");
        newElement.setAttribute("type", "text/javascript");
        newElement.setAttribute("src", "https://github.com/kxg3030/js-rpc/blob/main/Websocket.js");
        document.body.appendChild(newElement);
        function startWs() {
            var client = (new WebsocketClient("ws://127.0.0.1:9501")).start();

            client.registeCall("a", function (resolve, params) {
                // 重点!在这里我们主动调用w函数并传入参数
                resolve(w(params));
            })
        }
        setTimeout(startWs, 1000)
    })();
    // w函数存在其他地方
    return w(x + y);
}
```
然后将改造后的js文件保存下来，替换网页中原有的同名js文件

- 远程调用

使用外部ws服务器和浏览器通信即可

#### 三、功能路由

```javascript
/call 调用函数获取返回值

/list 获取当前服务的websocket客户端数量
```
