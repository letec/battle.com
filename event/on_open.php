<?php

$server->ws->on('open', function($server, $frame) use ($redis) {
    $data = [
        'fd'   => $frame->fd,
        'hall' => '',
        'room' => '',
    ];
    $redis->set('global_user_'.$frame->fd, $data);
});
