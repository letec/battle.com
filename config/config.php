<?php
/**
 * WEBSOCKET SERVER CONFIG
 */
define('SERVER_HOST', '0.0.0.0');
define('SERVER_PORT', 19502);


/**
 * DB CONFIG
 */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'battle');
define('DB_CHARSET', 'utf8');
define('DB_USER', 'root');
define('DB_PASSWORD', '103243916');

/**
 * REDIS CONFIG
 */
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);

/**
 * Room Max Number
 */
define('ROOM_MAX_NUMBER', 500);

defined('GAME_SERVER') ? GAME_SERVER : exit('GAME_SERVER配置错误!');

//五子棋配置
switch (GAME_SERVER) {
    case 'WUZI':
        require_once 'linefive_config.php';
        break;
    case 'BLACKJACK':
        require_once 'blakcjack_config.php';
        break;
    case 'GOLDENFLOWER':
        require_once 'goldenflower_config.php';
        break;
    default:
        exit('GAME_SERVER配置错误!');
        break;
}
