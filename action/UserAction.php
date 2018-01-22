<?php
class UserAction
{
    private $server = FALSE;
    private $redis = FALSE;
    private $pdo = FALSE;
    private $gameAction = NULL;

    public function __construct($server, $redis, $pdo)
    {
        $this->server = $server;
        $this->redis = $redis;
        $this->pdo = $pdo;
    }

    /**
     * @todo 发送消息
     * @param $from 来自
     * @param $to 发送给谁
     * @param $content 内容
     * @param $hall 大厅
     * @param $room 房间
     */
    public function send_Message($from, $to, $content, $hall='', $room='')
    {
        $data = ['type'=>'message', 'from'=>$from, 'to'=>$to, 'content'=>$content];
        $data = json_encode($data);
        if($to == 'all')
        {
            $list = $this->server->connection_list();
            foreach ($list as $fd) {
                $this->server->push($fd, $data);
            }
        }
        else if($to == 'hall')
        {
            if (! $this->isInHall($fd, $hall, $method))
            {
                return FALSE;
            }
            $hall_Info = $this->redis->get($hall);
            foreach ($hall_Info as $val) {
                $this->server->push($val->fd, $data);
            }
        }
        else if($to == 'room')
        {
            $room_users = $this->redis->get($room);
            if (! $room_users || ! isset($room_users['fd']))
            {
                $result = json_encode(['type'=>$method, 'status'=>'error', 'msg'=>'不在房间内!']);
                return $this->server->push($fd, $result);
            }
            foreach ($room_users as $val) {
                $this->server->push($val['fd'], $data);
            }
        }
        else
        {
            $this->server->push($to, $data);
        }
    }

    /**
     * @todo 玩家准备&取消准备 操作
     * @param int $fd 用户ID
     * @param string $room 用户房间
     * @param string $hall 用户大厅
     */
    public function ready_Play($fd, $room, $hall)
    {
        $room_Info = $this->redis->get($room);
        if (! $room_Info || ! isset($room_Info['users'][$fd]))
        {
            $result = json_encode(['type'=>'ready_Play', 'status'=>'error', 'msg'=>'你不在该房间内:'.$room]);
            return $this->server->push($fd, $result);
        }
        $room_Info['users'][$fd]['ready'] = $room_Info['users'][$fd]['ready'] == 1 ? 0 : 1;
        if ($room_Info['users'][$fd]['ready'] == 1)
        {
            $flag = TRUE;
            foreach ($room_Info['users'] as $k => $v) {
                if ($v['ready'] == 0)
                {
                    $flag = FALSE;
                    break;
                }
            }
            if($flag && count($room_Info['users']) >= ROOM_LIMIT)
            {
                return 'GAME_START';
            }
        }
        $ret = $this->redis->set($room, $room_Info);
        if (!$ret)
        {
            $result = ['type'=>'ready_Play', 'status'=>'error', 'msg'=>'准备(取消准备)失败'];
            return $this->server->push($fd, $result);
        }
        $result = ['type'=>'ready_Play', 'status'=>'ok', 'msg'=>'准备(取消准备)成功', 'fd'=>$fd];
        foreach ($room_Info['users'] as $k => $v) {
            $this->server->push($k, $result);
        }
        return TRUE;
    }

    public function enter_Hall($fd, $hall)
    {
        if (!$this->isHallExist($fd, $hall, 'enter_Hall'))
        {
            return FALSE;
        }

        $hall_Info = $this->redis->get($hall);
        if (! $hall_Info)
        {
            $hall_Info = [];
            $hall_Info['rooms'] = [];
        }
        $hall_Info['users'][$fd]['fd'] = $fd;
        $user = $this->redis->get('global_user_'.$fd);
        $user['hall'] = $hall;
        $this->redis->multi();
        $this->redis->set($hall, $hall_Info);
        $this->redis->set('global_user_'.$fd, $user);
        $ret = $this->redis->exec();

        if (! $ret)
        {
            $result = ['type'=>'enter_hall', 'status'=>'error', 'msg'=>'系统错误'];
        }
        else
        {
            $result = ['type'=>'enter_hall', 'status'=>'ok', 'msg'=>$hall];
            $this->server->push($fd, json_encode($result));
            $push = json_encode(['type'=>'hall_user_list', 'hall'=>$hall, 'rooms'=>$hall_Info]);
            foreach ($hall_Info['users'] as $k => $v) {
                $this->server->push($k, $push);
            }
        }
    }

    public function quit_Hall($fd, $hall)
    {
        if (! $this->isHallExist($fd, $hall, 'quit_Hall'))
        {
            return FALSE;
        }
        $hall_Info = $this->redis->get($hall);
        if (isset($hall_Info['users'][$fd]))
        {
            unset($hall_Info['users'][$fd]);
        }
        $user = $this->redis->get('global_user_'.$fd);
        $user['hall'] = '';

        $this->redis->multi();
        $this->redis->set($hall, $hall_Info);
        $this->redis->set('global_user_'.$fd, $user);
        $ret = $this->redis->exec();

        $result = $ret ? ['type'=>'quit_Hall','status'=>'ok','msg'=>$hall] : ['type'=>'quit_Hall', 'status'=>'error', 'msg'=>'系统错误'];
        $this->server->push($fd, json_encode($result));
        if ($ret)
        {
            $push = json_encode(['type'=>'hall_user_list', 'hall'=>$hall, 'rooms'=>$hall_Info]);
            foreach ($hall_Info as $k => $v) {
                $this->server->push($k, $push);
            }
        }

    }

    public function isHallExist($fd, $hall, $method)
    {
        if (! in_array($hall, ['hall_FIR', 'hall_Landlord', 'hall_BlackJack', 'hall_Baccarat', 'hall_GoldenFlower']))
        {
            $result = json_encode(['type'=>$method, 'status'=>'error', 'msg'=>'不存在的大厅']);
            return $this->server->push($fd, $result);
        }
        return TRUE;
    }

    public function isInHall($fd, $hall, $method)
    {
        $data = $this->redis->get($hall);
        if (! $data || ! isset($data['users'][$fd]))
        {
            $result = ['type'=>'quit_Hall', 'status'=>'error', 'msg'=>'还没有在该大厅中'];
            $this->server->push($fd, json_encode($result));
            return FALSE;
        }
        else
        {
            return TRUE;
        }
    }

    public function create_Room($fd, $hall)
    {
        if (! $this->isHallExist($fd, $hall, 'create_Room') || ! $this->isInHall($fd, $hall, 'create_Room'))
        {
            return FALSE;
        }
        $it = NULL;
        $pattern = $hall.'_*';
        $count = 500;
        $list = [];
        do {
            $keysArr = $this->redis->scan($it, $pattern, $count);
            if ($keysArr)
            {
                foreach ($keysArr as $key) {
                    $list[] = $key;
                }
            }
        } while ($it > 0);

        $hall_Info = $this->redis->get($hall);

        if (count($hall_Info['rooms']) == 0)
        {
            $id = 100000;
        }
        else
        {
            $offset = count($hall_Info['rooms'])-1;
            $id = (int)str_replace($hall.'_', '', $hall_Info['rooms'][$offset]) + 1;
        }

        $roomid = $hall.'_'.$id;
        $room_Info = [];
        $room_Info['status'] = 0;
        $room_Info['users'][$fd]['fd'] = $fd;
        $room_Info['users'][$fd]['ready'] = 0;

        $user = $this->redis->get('global_user_'.$fd);
        $user['roomid'] = $roomid;

        $hall_Info['rooms'][] = $roomid;

        $this->redis->multi();
        $this->redis->set($roomid, $room_Info);
        $this->redis->set($hall, $hall_Info);
        $this->redis->set('global_user_'.$fd, $user);
        $ret = $this->redis->exec();
        if (! $ret)
        {
            $result = ['type'=>'create_Room', 'status'=>'error', 'msg'=>'创建房间失败'];
        }
        else
        {
            $result = ['type'=>'create_Room', 'status'=>'ok', 'msg'=>'成功创建房间', 'roomid'=>$roomid];
        }
        $this->server->push($fd, json_encode($result));
        $users = $this->redis->get($hall);
    }

    public function enter_Room($fd, $room, $hall)
    {
        if (! $this->isRoomExist($fd, $room, 'enter_Room') || ! $this->isInHall($fd, $hall, 'enter_Room'))
        {
            return FALSE;
        }
        $room_Info = $this->redis->get($room);
        $max = $this->getRoomMax($hall);
        if (count($data) == $max)
        {
            $result = ['type'=>'enter_Room', 'status'=>'error', 'msg'=>'房间人数满了:'.$user['room'], 'list'=>$data];
            return $this->server->push($fd, json_encode($result));
        }
        $room_Info['users'][$fd]['fd'] = $fd;
        $room_Info['users'][$fd]['ready'] = 0;

        $user = $this->redis->get('global_user_'.$fd);
        $user['room'] = $room;

        $this->redis->multi();
        $this->redis->set($room, $room_Info);
        $this->redis->set('global_user_'.$fd, $user);
        $ret = $this->redis->exec();
        if ($ret)
        {
            $result = ['type'=>'enter_Room', 'status'=>'ok', 'msg'=>'进入房间成功', 'roomid'=>$room];
            $this->server->push($k, json_encode($result));
            $push = ['type'=>'usersInRoom', 'roomid'=>$room, 'list'=>$data];
            foreach ($data as $k => $v) {
                $this->server->push($k, json_encode($push));
            }
        }
        else
        {
            $result = ['type'=>'enter_Room', 'status'=>'error', 'msg'=>'进入房间失败'];
            $this->server->push($fd, json_encode($result));
        }
    }

    public function quit_Room($fd, $room, $hall)
    {
        if (! $this->isRoomExist($fd, $room, 'quit_Room'))
        {
            return FALSE;
        }

        $user = $this->redis->get('global_user_'.$fd);
        $user['room'] = '';

        $hall_Info = $this->redis->get($hall);

        $room_Info = $this->redis->get($room);
        unset($room_Info['users'][$fd]);

        $this->redis->multi();
        if (count($room_Info['users']) >= 1)
        {
            $this->redis->set($room, $room_Info);
        }
        else
        {
            unset($hall_Info['rooms'][$room]);
            $this->redis->set($hall, $hall_Info);
            $this->redis->delete($room);
        }
        $this->redis->set('global_user_'.$fd, $user);
        $ret = $this->redis->exec();

        if ($ret)
        {
            $result = json_encode(['type'=>'quit_Room', 'status'=>'ok', 'msg'=>'退出房间成功']);
            $this->server->push($fd, $result);
            if (count($room_Info['users']) >= 1)
            {
                $push = ['type'=>'usersInRoom', 'roomid'=>$room, 'list'=>$room_Info['users']];
                foreach ($room_Info['users'] as $k => $v) {
                    $this->server->push($k, $push);
                }
            }
        }
        else
        {
            $result = ['type'=>'quit_Room', 'status'=>'error', 'msg'=>'退出房间失败'];
            $this->server->push($fd, json_encode($result));
        }

    }

    public function isRoomExist($fd, $room, $method)
    {
        if (! $this->redis->exists($room))
        {
            $result = json_encode(['type'=>$method, 'status'=>'error', 'msg'=>'不存在的房间:'.$room]);
            return $this->server->push($fd, $result);
        }
        return TRUE;
    }

    private function getRoomMax($hall)
    {
        switch($hall)
        {
            case 'hall_FIR':
                return WUZI_ROOM_MAX;
            case 'hall_BlackJack':
                return BLACKJACK_ROOM_MAX;
            case 'hall_Baccarat':
                return 1000;
            case 'hall_GoldenFlower':
                return GOLDEN_ROOM_MAX;
            default:
                return 0;
        }
    }

    public function clearUser($fd)
    {
        $user = $this->redis->get('global_user_'.$fd);
        if(!empty($user['room']))
        {
            $room = $this->redis->get($user['room']);
            unset($room[$fd]);
            if (count($room) == 0)
            {
                $this->redis->delete($user['room']);
                return TRUE;
            }
            $this->redis->set($user['room'], $room);
        }
        if(!empty($user['hall']))
        {
            $hall = $this->redis->get($user['hall']);
            unset($hall[$fd]);
            $this->redis->set($user['hall'], $room);
        }
        $this->redis->delete('global_user_'.$fd);
    }





}
