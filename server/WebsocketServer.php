<?php

class WebsocketServer
{
    public $ws = FALSE;

    public function __construct($server_host, $server_port)
    {
        $this->ws = new swoole_websocket_server($server_host, $server_port);
    }

    public function push($fd, $content)
    {
        return $this->ws->push($fd, $content);
    }

}
