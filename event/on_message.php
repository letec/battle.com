<?php

$server->ws->on('message', function($server, $frame) use ($userAction, $gameAction) {
    $fd = $frame->fd;
    $data = json_decode($frame->data);
    if (!is_object($data))
    {
        $err = ['type'=>'none', 'status'=>'error', 'msg'=>'错误的消息格式'];
        $error = json_encode($err);
        $server->push($fd, $error);
        return FALSE;
    }
    switch ($data->type)
    {
        case 'message':
            $userAction->send_Message($data->from, $data->to, $data->content, $data->hall, $data->room);
            break;
        case 'enter_Hall':
            $userAction->enter_Hall($fd, $data->hall);
            break;
        case 'quit_Hall':
            $userAction->quit_Hall($fd, $data->hall);
            break;
        case 'create_Room':
            $userAction->create_Room($fd, $data->hall);
            break;
        case 'enter_Room':
            $userAction->enter_Room($fd, $data->room, $data->hall);
            break;
        case 'quit_Room':
            $userAction->quit_Room($fd, $data->room, $data->hall);
            break;

        case 'ready_Play':
            $result = $userAction->ready_Play($fd, $data->room, $data->hall);
            if ($result == 'GAME_START')
            {
                $gameAction->gameStart($fd, $data->room);
            }
            break;
        default:
            $result = json_encode(['type'=>'none', 'status'=>'error', 'msg'=>'匹配不到相应的操作']);
            break;
    }


});
