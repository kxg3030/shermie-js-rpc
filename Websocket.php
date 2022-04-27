<?php

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Table;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

/**
 * Class WebsocketServer
 */
class WebsocketServer
{
    /**
     * @var int
     */
    public int $port = 9501;
    /**
     * @var Server|null
     */
    public ?Server $server = null;
    /**
     * @var Table|null
     */
    public ?Table $group = null;

    /**
     *
     */
    const  OPEN_EVENT = "open";
    /**
     *
     */
    const  REQU_EVENT = "request";
    /**
     *
     */
    const  START_EVENT = "start";
    /**
     *
     */
    const  MSG_EVENT = "message";
    /**
     *
     */
    const  CLS_EVENT = "close";

    /**
     * WebsocketServer constructor.
     */
    public function __construct() {
        ini_set('date.timezone', 'Asia/Shanghai');
        $this->server = new Swoole\WebSocket\Server("0.0.0.0", $this->port);
        $this->server->set([
            "worker_num"               => 2,
            "heartbeat_check_interval" => 60,
            "heartbeat_idle_time"      => 600,
        ]);
        $table = new Table(1024);
        $table->column("fd", Table::TYPE_STRING, 1024);
        $table->column("data", Table::TYPE_STRING, 1024);
        $table->create();
        $this->group = $table;
    }

    /**
     *
     */
    public function registeEvent() {
        $this->server->on(self::START_EVENT, [$this, 'start']);
        $this->server->on(self::REQU_EVENT, [$this, 'request']);
        $this->server->on(self::OPEN_EVENT, [$this, 'open']);
        $this->server->on(self::MSG_EVENT, [$this, 'message']);
        $this->server->on(self::CLS_EVENT, [$this, 'close']);
    }

    /**
     * @param Server $server
     */
    public function start(Server $server) {
        $log = <<<EOF

 ______     __  __     ______     ______     __    __     __     ______    
/\  ___\   /\ \_\ \   /\  ___\   /\  == \   /\ "-./  \   /\ \   /\  ___\   
\ \___  \  \ \  __ \  \ \  __\   \ \  __<   \ \ \-./\ \  \ \ \  \ \  __\   
 \/\_____\  \ \_\ \_\  \ \_____\  \ \_\ \_\  \ \_\ \ \_\  \ \_\  \ \_____\ 
  \/_____/   \/_/\/_/   \/_____/   \/_/ /_/   \/_/  \/_/   \/_/   \/_____/
  
EOF;
        $this->log(self::START_EVENT, $log);
        $this->log(self::START_EVENT, "server listen at 0.0.0.0:{$this->port}");
    }

    /**
     * @param Server $server
     * @param Request $request
     */
    public function open(Server $server, Request $request) {
        $this->log(self::OPEN_EVENT, "new websocket client connect {$request->fd}");
    }

    /**
     * @param Server $server
     * @param Frame $frame
     */
    public function message(Server $server, Frame $frame) {
        $this->log(self::MSG_EVENT, "receive data from websocket client {$frame->fd}：" . $frame->data);
        $data = json_decode($frame->data, true);
        if ($data["data"] == "ping") {
            return;
        }
        $this->group->set($data["uuid"], ["fd" => $frame->fd, "data" => json_encode($data, 256)]);
    }

    /**
     * @param Server $server
     * @param int $fd
     */
    public function close(Server $server, int $fd) {
        // TODO 删除内存表中的客户端的fd
        $this->log(self::CLS_EVENT, "server close websocket client {$fd}");
    }

    /**
     * @param Request $request
     * @param Response $response
     */
    public function request(Swoole\Http\Request $request, Swoole\Http\Response $response) {
        $server = $request->server;
        $method = strtoupper($server["request_method"]);
        $path   = $server["request_uri"];
        $remote = $server["remote_addr"];
        $port   = $server["remote_port"];
        $params = $request->get;
        $this->log(self::REQU_EVENT, "$remote:$port $method $path " . (json_encode($params, JSON_UNESCAPED_UNICODE) ?: ""));
        $response->header("Content-Type", "application/json;charset=utf-8");
        switch ($path) {
            case "/call":
                // 分组参数
                $group = $params["group"] ?? "";
                if (!$group) {
                    $data = ["code" => 9999, "data" => null, "msg" => "客户端分组参数不能为空"];
                    $response->end(json_encode($data, 256));
                    return;
                }
                // 调用函数式
                $action = $params["action"] ?? "";
                if (!$action) {
                    $data = ["code" => 9999, "data" => null, "msg" => "调用方法参数不能为空"];
                    $response->end(json_encode($data, 256));
                    return;
                }
                // 传入参数
                $input = $params["input"] ?? "";
                if (!$input) {
                    $data = ["code" => 9999, "data" => null, "msg" => "调用方法传入参数不能为空"];
                    $response->end(json_encode($data, 256));
                    return;
                }
                // 创建连接或者发送数据
                if (!($this->group->exist($group))) {
                    $data = ["code" => 9999, "data" => null, "msg" => "当前分组内不存在已连接的客户端"];
                    $response->end(json_encode($data, 256));
                    return;
                }
                $fd = $this->group->get($group)["fd"];
                // 判断连接是否可用
                if (!$this->server->exist($fd) || !$this->server->isEstablished($fd)) {
                    $data = ["code" => 9999, "data" => null, "msg" => "当前分组客户端连接已超时断开"];
                    $response->end(json_encode($data, 256));
                    return;
                }
                $data = ["code" => 200, "data" => $params, "msg" => "success"];
                $this->server->push($fd, json_encode($data, 256), WEBSOCKET_OPCODE_TEXT, true);
                while (true) {
                    if (!$this->group->exist($group)) {
                        continue;
                    }
                    $receiveData = json_decode($this->group->get($group)["data"], true);
                    usleep(200);
                    if ($receiveData["data"] ?? null) {
                        $wsData = $receiveData["data"] ?? null;
                        break;
                    }
                }
                // 清空数据
                $this->group->set($group, ["fd" => $fd, "data" => null]);
                $result = ["code" => 200, "data" => $wsData, "msg" => "success"];
                $response->end(json_encode($result, 256));
                return;
            case "/list":
                $clients = [];
                foreach ($this->group as $row) {
                    if ($row) {
                        $rowData   = json_decode($row["data"], true);
                        $clients[] = [
                            "uuid" => $rowData["uuid"],
                            "fd"   => $row["fd"]
                        ];
                    }
                }
                $result = ["code" => 200, "data" => $clients, "msg" => "success"];
                $response->end(json_encode($result, 256));
                return;
        }
        $response->setStatusCode(404);
        $response->end();
    }

    /**
     *
     */
    public function run() {
        $this->registeEvent();
        $this->server->start();
    }

    /**
     * @param string $event
     * @param string $msg
     */
    private function log(string $event, string $msg) {
        $msg = sprintf("[%s][$event]：%s" . PHP_EOL, date("Y-m-d H:i:s"), $msg);
        fwrite(STDOUT, $msg);
        fflush(STDOUT);
    }
}

(new WebsocketServer())->run();