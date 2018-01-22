<?php
class LineFive
{

    private $redis = FALSE;
    private $server = FALSE;
    private $pdo = FALSE;

    public function __construct($server, $redis, $pdo)
    {
        $this->redis = $redis;
        $this->server = $server;
        $this->pdo = $pdo;
    }

    /**
     * @todo 初始化棋盘
     */
    private function init_Board()
    {
        $board = [];
        for ($i = 1; $i <= 17; $i++) {
            $board[$i] = [];
            for ($x = 17; $x >= 1 ; $x--) {
                $board[$i][$x] = 0;
            }
        }
        return $board;
    }

    /**
     * @todo 游戏开始
     * @param int $fd 用户ID
     * @param string $room 用户房间
     * @param string $hall 用户大厅
     */
    public function gameStart($fd, $room, $hall)
    {
        $room_Info = $this->redis->get($room);
        $random = array_rand(array_keys($room_Info['users']));
        $turn = $room_Info['users'][$random];
        $room_Info['users'][$random]['flag'] = 'black';
        $room_Info['status'] = 1;
        $room_Info['step'] = 0;
        foreach (array_keys($room_Info['users']) as $v) {
            $room_Info['time_'.$v] = ROUND_SECOND;
        }
        $room_Info['trun'] = $turn;
        $room_Info['board'] = $this->init_Board();
        $room_Info['timer'] = $this->setPieceTimeOut($room);
        $ret = $this->redis->set($room, $room_Info);
        $push = ['type'=>'game_openning', 'room'=>$room, 'turn'=>$turn];
        foreach ($room_Info['users'] as $k => $v) {
            $room_Info['users'][$k]['ready'] = 2;
            $this->server->push($k, $push);
        }
    }

    /**
     * @todo 玩家落子操作
     * @param int $fd 用户ID
     * @param string $room 用户房间
     * @param int $x 落子点X轴
     * @param int $y 落子点Y轴
     * @param string $x 用户
     */
    public function set_Piece($fd, $room, $x, $y)
    {
        $room_Info = $this->redis->get($room);
        $flag = isset($room_Info['users'][$random]['flag']) ? 'black' : 'white';
        if (!isset($room_Info['board'][$x][$y]))
        {
            $result = ['type'=>'set_Piece', 'status'=>'error', 'msg'=>'不存在的坐标'];
        }
        if (in_array($room_Info['board'][$x][$y], ['black','white']))
        {
            $result = ['type'=>'set_Piece', 'status'=>'error', 'msg'=>'已经存在棋子'];
        }
        $room_Info['board'][$x][$y] = $flag;

        // 如果本次落子决定了胜负
        if ($this->checkOver($room_Info['board'], $x, $y, $flag))
        {
            return $this->gameOver($room, $room_Info, $fd);
        }
        // 如果游戏超过最大步数仍然没有结束 则平局
        else if ($room_Info['step'] >= MAX_STEP)
        {
            return $this->gameOver($room, $room_Info);
        }
        // 换下一位落子
        foreach ($room_Info['users'] as $k => $v) {
            if ($k != $fd)
            {
                $room_Info['turn'] = $k;
                break;
            }
        }
        //落子成功停止倒计时
        $this->server->ws->swoole_timer_clear($room_Info['timer']);
        //计时器开始记录下一位玩家倒计时
        $room_Info['timer'] = $this->setPieceTimeOut($room);
        $room_Info['step'] = $room_Info['step'] + 1;
        $ret = $this->redis->set($room, $room_Info);
        if ($ret)
        {
            $result = ['type'=>'set_Piece', 'status'=>'ok', 'msg'=>'落子成功', 'board'=>$room_Info['board'], 'fd'=>$fd, 'turn'=>$room_Info['turn']];
        }
        else
        {
            $result = ['type'=>'set_Piece', 'status'=>'error', 'msg'=>'落子失败'];
        }
        foreach ($roomData as $k => $fd) {
            $this->server->push($fd, json_encode($result));
        }
    }

    /**
     * @todo 游戏结束
     * @param string $room 用户房间
     * @param array $room_Info 用户房间信息
     * @param string $winner 胜利者fd 如果不传 则是平局
     */
    private function gameOver($room, $room_Info, $winner=NULL)
    {
        // 停止本房间的倒计时计算
        $this->server->ws->swoole_timer_clear($room_Info['timer']);
        if ($winner != NULL)
        {
            $loser = '';
            foreach ($room_Info['users'] as $k => $v) {
                if ($k != $winner)
                {
                    $loser = $k;
                }
            }
            $result = ['type'=>'gameover', 'status'=>'ok', 'msg'=>'游戏结束', 'winner'=>$winner, 'loser' => $loserm, 'drawGame' => 0];
        }
        else
        {
            $result = ['type'=>'gameover', 'status'=>'ok', 'msg'=>'游戏结束', 'drawGame' => 1];
        }
        $room_Info['status'] = 0;
        unset($room_Info['step']);
        unset($room_Info['timer']);
        foreach (array_keys($room_Info['users']) as $v) {
            unset($room_Info['time_'.$v]);
        }
        unset($room_Info['board']);
        unset($room_Info['turn']);
        foreach ($room_Info['users'] as $k => $v) {
            unset($room_Info[$k]['flag']);
            $this->server->push($k, json_encode($result));
        }
        $this->redis->set($room, $room_Info);
    }

    /**
     * @todo 轮到谁落子就减谁的剩余时间
     * @param string 房间名称
     */
    private function setPieceTimeOut($room)
    {
        //轮到谁就开始减谁的时间
        return $this->server->ws->tick(1000, function() use ($room) {
            $room_Info = $this->redis->get($room);
            $nowTurn = $room_Info['turn'];
            $room_Info['time_'.$nowTurn] -= 1;
            if ($room_Info['time_'.$nowTurn] == 0)
            {
                foreach ($room_Info['users'] as $k => $v) {
                    if ($k != $nowTurn)
                    {
                        return $this->gameOver($room, $room_Info, $k);
                    }
                }
            }
            $this->redis->set($room, $room_Info);
        });
    }

    private function checkOver($board, $x, $y, $flag)
    {
        $boolean = $this->checkLineXY($board, $x, $y, $flag) && $this->checkLineY_X($board, $x, $y, $flag);
        return $boolean;
    }

    private function checkLineXY($board, $x, $y, $flag)
    {
        $countX = 0;
        $countY = 0;
        for ($i=1; $i <= 17; $i++) {
            echo $x.'-'.$i.$board[$x][$i]."\n";
            if ($countX == 5 || $countY == 5)
            {
                return TRUE;
            }
            if ($board[$i][$y] == $flag)
            {
                $countX += 1;
            }else{
                $countX = 0;
            }
            if ($board[$x][$i] == $flag)
            {
                $countY += 1;
            }else{
                $countY = 0;
            }
        }
        return FALSE;
    }

    private function checkLineY_X($board, $x, $y, $flag)
    {
        $count = 0;
        $tempX = $x;
        $tempy = $y;

        $top_left_x = $x;
        $top_left_y = $y;
        while ($top_left_x > 1 && $top_left_y > 1) {
            $top_left_x -= 1;
            $top_left_y -= 1;
        }

        $top_right_x = $x;
        $top_right_y = $y;
        while ($top_right_x < 17 && $top_right_y > 1) {
            $top_right_x += 1;
            $top_right_y -= 1;
        }

        $count = 0;
        while ($top_left_x < 17 && $top_left_y < 17) {
            $count = $board[$top_left_x][$top_left_y] == $flag ? ($count + 1) : 0;
            if ($count == 5)
            {
                return TRUE;
            }
            $top_left_x += 1;
            $top_left_y += 1;
        }
        $count = 0;
        while ($top_right_x >= 1 && $top_right_y <= 17) {
            $count = $board[$top_right_x][$top_right_y] == $flag ? ($count + 1) : 0;
            if($board[$top_right_x][$top_right_y] == $flag){
            }
            if ($count == 5)
            {
                return TRUE;
            }
            $top_right_x -= 1;
            $top_right_y += 1;
        }
        return FALSE;
    }





}
