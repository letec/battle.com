<?php

$server->ws->on('close', function($server, $fd) use ($userAction) {
    $userAction->clearUser($fd);
});
