<?php
class BlackJack
{

    private $redis = FALSE;
    private $server = FALSE;
    private $pdo = FALSE;
    private $pokerList = NULL;

    public function __construct($server, $redis, $pdo)
    {
        $this->redis = $redis;
        $this->server = $server;
        $this->pdo = $pdo;
        $this->pokerList = $this->init_table();
    }

    /**
     * @todo 取得完整的一副扑克牌数组
     * @return array 扑克牌数组
     */
    private function init_table()
    {
        $pokerList = [
            '11' => ['type' => 'Diamond', 'point' => 1],
            '12' => ['type' => 'Club', 'point' => 1],
            '13' => ['type' => 'Heart', 'point' => 1],
            '14' => ['type' => 'Spade', 'point' => 1],
            '21' => ['type' => 'Diamond', 'point' => 2],
            '22' => ['type' => 'Club', 'point' => 2],
            '23' => ['type' => 'Heart', 'point' => 2],
            '24' => ['type' => 'Spade', 'point' => 2],
            '31' => ['type' => 'Diamond', 'point' => 3],
            '32' => ['type' => 'Club', 'point' => 3],
            '33' => ['type' => 'Heart', 'point' => 3],
            '34' => ['type' => 'Spade', 'point' => 3],
            '41' => ['type' => 'Diamond', 'point' => 4],
            '42' => ['type' => 'Club', 'point' => 4],
            '43' => ['type' => 'Heart', 'point' => 4],
            '44' => ['type' => 'Spade', 'point' => 4],
            '51' => ['type' => 'Diamond', 'point' => 5],
            '52' => ['type' => 'Club', 'point' => 5],
            '53' => ['type' => 'Heart', 'point' => 5],
            '54' => ['type' => 'Spade', 'point' => 5],
            '61' => ['type' => 'Diamond', 'point' => 6],
            '62' => ['type' => 'Club', 'point' => 6],
            '63' => ['type' => 'Heart', 'point' => 6],
            '64' => ['type' => 'Spade', 'point' => 6],
            '71' => ['type' => 'Diamond', 'point' => 7],
            '72' => ['type' => 'Club', 'point' => 7],
            '73' => ['type' => 'Heart', 'point' => 7],
            '74' => ['type' => 'Spade', 'point' => 7],
            '81' => ['type' => 'Diamond', 'point' => 8],
            '82' => ['type' => 'Club', 'point' => 8],
            '83' => ['type' => 'Heart', 'point' => 8],
            '84' => ['type' => 'Spade', 'point' => 8],
            '91' => ['type' => 'Diamond', 'point' => 9],
            '92' => ['type' => 'Club', 'point' => 9],
            '93' => ['type' => 'Heart', 'point' => 9],
            '94' => ['type' => 'Spade', 'point' => 9],
            '101' => ['type' => 'Diamond', 'point' => 10],
            '102' => ['type' => 'Club', 'point' => 10],
            '103' => ['type' => 'Heart', 'point' => 10],
            '104' => ['type' => 'Spade', 'point' => 10],
            '111' => ['type' => 'Diamond', 'point' => 11],
            '112' => ['type' => 'Club', 'point' => 11],
            '113' => ['type' => 'Heart', 'point' => 11],
            '114' => ['type' => 'Spade', 'point' => 11],
            '121' => ['type' => 'Diamond', 'point' => 12],
            '122' => ['type' => 'Club', 'point' => 12],
            '123' => ['type' => 'Heart', 'point' => 12],
            '124' => ['type' => 'Spade', 'point' => 12],
            '131' => ['type' => 'Diamond', 'point' => 13],
            '132' => ['type' => 'Club', 'point' => 13],
            '133' => ['type' => 'Heart', 'point' => 13],
            '134' => ['type' => 'Spade', 'point' => 13],
            '998' => ['type' => 'JOKER_B', 'point' => -1],
            '999' => ['type' => 'JOKER_A', 'point' => -2],
        ];
        return $pokerList;
    }

    /**
     * @todo 检查游戏是否结束
     * @param int $fd 玩家fd
     * @param array $room_Info 房间信息
     */
    private function checkIsGameOver($room_Info)
    {
        // 计算有几位玩家状态已经爆了 或者 扣牌
        $burstCount = 0;
        $lockCount = 0;
        $allCount = 0;
        foreach ($room_Info['users'] as $k => $v) {
            $temp = $room_Info['users'][$k];
            if ($temp['burst'] == 1)
            {
                $burstCount += 1;
            }
            if ($temp['lock'] == 1)
            {
                $lockCount += 1;
            }
            $allCount += 1;
        }
        if ($lockCount == ROOM_MAX || $allCount == ROOM_MAX || $burstCount == (ROOM_MAX - 1))
        {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * @todo 设置下一位顺序玩家
     * @param array $room_Info 房间信息
     */
    private function setNextPlayer($room_Info)
    {
        $turnList = $room_Info['turn'];
        // 弹出下一位玩家 放到数组下标0 更新轮到谁
        $next = array_pop($room_Info['turn']);
        array_unshift($room_Info['turn'], $next);
        $nextInfo = $room_Info['users'][$next];
        $room_Info['turn'] = $next;
        // 如果下一位玩家已经扣牌或者爆了 递归调用再处理一次
        if ($nextInfo['burst'] == 1 || $nextInfo['lock'] == 1)
        {
            $room_Info = $this->setNextPlayer($room_Info);
        }
        return $room_Info;
    }

    /**
     * @todo 游戏结束
     * @param string $room 房间信息
     * @param array $room_Info 房间信息
     */
    private function gameOver($room, $room_Info)
    {
        $max = 0;
        $winner = [];
        $loser = [];
        $list = [];
        $a = [];
        $b = [];
        foreach ($room_Info['users'] as $fds => $fd) {
            if ($room_Info['users'][$fd]['burst'] == 1)
            {
                $loser[] = $fd;
                continue;
            }
            $list[$fd] = $room_Info['users'][$fd]['point'];
        }
        $temp = array_flip($list);
        $countList = array_count_values($list);
        foreach ($countList as $k => $v) {
            if ($v == 3)
            {
                break;
            }
            foreach ($list as $key => $val) {
                $max = $max <= $val ? $val : $max;
                if ($k == $val)
                {
                    $a[] = $key;
                }
                else
                {
                    $b[] = $key;
                }
            }
            if ($v == 2)
            {
                $winner = $list[$a[0]] > $list[$b[0]] ? $a : $b;
                $loser = $list[$a[0]] < $list[$b[0]] ? $a : $b;
                break;
            }
            $winner[] = $temp[$max];
            foreach ($list as $key => $val) {
                if ($key != $temp[$max])
                {
                    $loser[] = $key;
                }
            }
            break;
        }
        return countBonus($winner, $loser);
    }

    /**
     * @todo 输赢记录 加减钱
     * @param array $winner 赢家
     * @param array $loser 输家
     */
    private function countBonus($winner, $loser)
    {
        
    }

    /**
     * @todo 抽牌
     * @param $fd 用户fd
     * @param $room 房间号
     */
    public function draw_Card($fd, $room)
    {
        $result = ['type'=>'draw_Card', 'status'=>'ok', 'msg'=>'抽牌成功', 'fd' => $fd];
        $room_Info = $this->redis->get($room);

        if ($room_Info['turn'] != $fd)
        {
            $result = ['type'=>'draw_Card', 'status'=>'error', 'msg'=>'还没轮到您抽牌!'];
            return $this->server->push($k, json_encode($result));
        }

        // 取得未抽的牌
        $table = $room_Info['table'];
        // 抽一张 放到目标玩家手中 加上分
        $poker = array_rand($table);
        $room_Info['users'][$fd]['pokerList'][] = $poker;
        $room_Info['users'][$fd]['point'] += $this->pokerList[$poker]['point'];

        // 如果等于21点 游戏结束
        if ($room_Info['users'][$fd]['point'] == 21)
        {
            $ret = $this->gameOver($room, $room_Info);
        }
        // 如果爆了 必须计算游戏是否结束
        if ($room_Info['users'][$fd]['point'] > 21)
        {
            $room_Info['users'][$fd]['burst'] == 1;
            if ($this->checkIsGameOver($room_Info))
            {
                $this->gameOver($room, $room_Info);
            }
        }

        // 从未抽牌堆中拿掉抽中的牌
        unset($table[$poker]);
        $room_Info['table'] = $table;
        // 取得玩家顺序
        $room_Info = $this->setNextPlayer($room_Info);
        // 清除上一位的倒计时
        $this->server->swoole_timer_clear($room_Info['timer']);
        // 设置下一位的倒计时
        $room_Info['timer'] = $this->setDrawTimeOut($room);
        // 存入处理结果
        if ($this->redis->set($room, $room_Info))
        {
            foreach ($room_Info['users'] as $k => $v) {
                if ($fd == $k)
                {
                    $result['poker'] = $poker;
                }
                $this->server->push($k, json_encode($result));
                unset($result['poker']);
            }
        }
        else
        {
            $result = ['type'=>'draw_Card', 'status'=>'error', 'msg'=>'抽牌失败'];
            $this->server->push($k, json_encode($result));
        }
    }

    /**
     * @todo 扣牌
     * @param $fd 用户fd
     * @param $room 房间号
     */
    public function lock_Pokers($fd, $room)
    {
        $result = ['type'=>'lock_Pokers', 'status'=>'ok', 'fd' => $fd, 'msg'=>'扣牌成功!'];
        $room_Info = $this->redis->get($room);

        if ($room_Info['turn'] != $fd)
        {
            $result = ['type'=>'lock_Pokers', 'status'=>'error', 'msg'=>'还没轮到您抽牌!'];
            return $this->server->push($k, json_encode($result));
        }

        $room_Info['users'][$fd]['lock'] = 1;
        if ($this->checkIsGameOver($room_Info))
        {
            $this->gameOver($room, $room_Info);
        }

        $room_Info = $this->setNextPlayer($room_Info);
        if ($this->redis->set($room, $room_Info))
        {
            foreach ($room_Info['users'] as $k => $v) {
                $this->server->push($k, json_encode($result));
            }
        }
        else
        {
            $result = ['type'=>'draw_Card', 'status'=>'error', 'msg'=>'抽牌失败'];
            $this->server->push($fd, json_encode($result));
        }
    }

    /**
     * @todo 游戏开始
     * @param $fd 用户fd
     * @param $room 房间号
     * @param $hall 大厅号
     */
    public function gameStart($fd, $room, $hall)
    {
        $room_Info = $this->redis->get($room);
        $pokerList = $this->init_table();
        $fds = array_keys($room_Info['users']);
        foreach ($fds as $fd) {
            $poker = array_rand($pokerList);
            $room_Info['users'][$fd]['pokerList'][] = $poker;
            unset($pokerList[$poker]);
            $this->redis->rPush($room.'_list', $fd);
        }
        $turnList = array_keys($room_Info['users']);
        $room_Info['table'] = $pokerList;
        $room_Info['trun'] = $turnList[0];
        $room_Info['timer'] = $this->setDrawTimeOut($room);
        $room_Info['status'] = 1;

        foreach (array_keys($room_Info['users']) as $v) {
            $room_Info['time_'.$v] = ROUND_SECOND;
        }

        $push = ['type'=>'game_openning', 'room'=>$room, 'turn'=>$room_Info['trun']];
        foreach ($room_Info['users'] as $k => $v) {
            $room_Info['users'][$k]['burst'] = 0;
            $room_Info['users'][$k]['lock'] = 0;
            $room_Info['users'][$k]['ready'] = 2;
            $room_Info['users'][$k]['point'] = 0;
            $this->server->push($k, $push);
        }
        $ret = $this->redis->set($room, $room_Info);
    }

    /**
     * @todo 超时则自动抽牌
     * @param $room 房间名称
     */
    private function setDrawTimeOut($room)
    {
        return $this->server->ws->after(ROUND_MICROTIME, function() use ($room) {
            $room_Info = $this->redis->get($room);
            $nowTurn = $room_Info['turn'];
            $this->draw_Card($nowTurn, $room);
        });
    }


}
