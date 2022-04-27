function WebsocketClient(host) {
    this.ws = null;
    this.uniqueId = null;
    this.registeCallMap = {};
    this.timer = -1;
    // 入口函数
    this.start = function () {
        this.logo()
        this.uniqueId = this.uuid();
        this.ws = new WebSocket(host);
        this.ws.onopen = this.open();
        this.ws.onmessage = this.message();
        this.ws.onclose = this.close();
        return this;
    }
    // 心跳函数
    this.heartbeat = function () {
        let _this = this;
        let post = {"msg": "success", "data": "ping", "code": 200, "uuid": this.uniqueId};
        if (this.ws.readyState === WebSocket.OPEN) {
            this.timer = setInterval(function () {
                _this.ws.send(JSON.stringify(post));
            }, 60000);
        }
        return this;
    }
    // 打印logo
    this.logo = function () {
        let logo = `%c 
 ______     __  __     ______     ______     __    __     __     ______    
/\\  ___\\   /\\ \\_\\ \\   /\\  ___\\   /\\  == \\   /\\ "-./  \\   /\\ \\   /\\  ___\\   
\\ \\___  \\  \\ \\  __ \\  \\ \\  __\\   \\ \\  __<   \\ \\ \\-./\\ \\  \\ \\ \\  \\ \\  __\\   
 \\/\\_____\\  \\ \\_\\ \\_\\  \\ \\_____\\  \\ \\_\\ \\_\\  \\ \\_\\ \\ \\_\\  \\ \\_\\  \\ \\_____\\ 
  \\/_____/   \\/_/\\/_/   \\/_____/   \\/_/ /_/   \\/_/  \\/_/   \\/_/   \\/_____/
`
        console.log(logo, "color:blue;")
    }
    // 回调函数
    this.open = function () {
        let _this = this;
        return function (event) {
            _this.log("info", "连接服务器成功");
            // 心跳定时器
            _this.heartbeat();
            // 向服务器发送消息
            _this.sendSuccess(null);
        }
    }
    // 回调函数
    this.message = function () {
        let _this = this;
        return function (event) {
            let receive = event.data;
            _this.log("info", "收到服务器消息：" + receive);
            // 解析分组和函数参数
            receive = JSON.parse(receive);
            const {group, action, input} = receive.data;
            if (_this.uniqueId !== group) {
                _this.sendError("客户端分组不存在");
                return;
            }
            if (!_this.registeCallMap[group][action]) {
                _this.sendError("调用函数未注册");
                return;
            }
            // 调用函数
            try {
                _this.registeCallMap[group][action](_this.resolve(input, action), input);
            } catch (e) {
                _this.log("error", "调用函数报错：" + e.message);
                _this.sendError("调用函数报错：" + e.message);
            }
        }
    }
    // 处理数据
    this.resolve = function (input, action) {
        let _this = this;
        return function (data) {
            _this.log("info", `调用函数${action}返回：${data}`);
            _this.sendSuccess({result: data, input: input});
        }
    }
    // 回调函数
    this.close = function () {
        let _this = this;
        return function (event) {
            clearInterval(_this.timer)
            _this.log("info", "服务器断开连接");
        }
    }
    // 生成客户端id
    this.uuid = function () {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0,
                v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    // 日志函数
    this.log = function (type, msg) {
        let datetime = (new Date()).toLocaleString()
        let color = type.toLowerCase() === "info" ? "green" : "red";
        console.log(`[${datetime}][${type}] %c ${msg}`, `color:${color};`);
    }
    // 注册调用函数
    this.registeCall = function (callName, callback) {
        this.log("info", `注册函数${callName}成功`);
        // 保存传入的函数
        this.registeCallMap[this.uniqueId] = {
            [callName]: callback
        };
        // 访问地址
        this.log("info", `外部访问地址：${host.replace("ws", "http")}/call?group=${this.uniqueId}&action=${callName}&input=`);
    }
    this.sendError = function (msg) {
        let post = {"msg": msg, "data": msg, "code": 9999, "uuid": this.uniqueId};
        this.ws.send(JSON.stringify(post));
    }
    this.sendSuccess = function (data) {
        let post = {"msg": "success", "data": data, "code": 200, "uuid": this.uniqueId};
        this.ws.send(JSON.stringify(post));
    }
}

var client = (new WebsocketClient("ws://127.0.0.1:9501")).start();
