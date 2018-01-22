<?php
date_default_timezone_set('Etc/GMT-8');
define('GAME_SERVER', 'BLACKJACK');

require_once 'config/config.php';
require_once 'server/WebsocketServer.php';
require_once 'mysql/init_mysql.php';
require_once 'redis/init_redis.php';

require_once 'action/UserAction.php';
require_once 'games/LineFive.php';

$server = new WebsocketServer(SERVER_HOST, SERVER_PORT);
$userAction = new UserAction($server, $redis, $pdo);
$gameAction = new BlackJack($server, $redis, $pdo);

//$redis->redis->flushAll();

require_once 'event/on_open.php';
require_once 'event/on_message.php';
require_once 'event/on_close.php';

$server->ws->start();
