<?php

class RedisHandle
{
    public $ws = FALSE;

    public function __construct($redis_host, $redis_port, $mode = 0)
    {
        if($this->redis == FALSE)
        {
            $this->redis = new Redis();
            if($mode == 1)
            {
                $this->redis->pconnect($redis_host, $redis_port, 1);
            }
            else
            {
                $this->redis->connect($redis_host, $redis_port, 1);
            }
        }
    }

    public function set($key, $val, $expire=24*60*60)
    {
        if (is_array($val))
        {
            $val = json_encode($val);
        }
        return $this->redis->setex($key, $expire, $val);
    }

    public function get($key)
    {
        $val = $this->redis->get($key);
        if (!$val)
        {
            return FALSE;
        }
        $val_se = json_decode($val, TRUE);
        if (is_array($val_se))
        {
            return $val_se;
        }
        return $val;
    }

    public function lPush($key, $value)
    {
        return $this->redis->lPush($key, $value);
    }

    public function rPush($key, $value)
    {
        return $this->redis->rPush($key, $value);
    }

    public function lPop($key)
    {
        return $this->redis->lPop($key);
    }

    public function rPop($key)
    {
        return $this->redis->rPop($key);
    }

    public function lGet($key, $index)
    {
        return $this->redis->lGet($key, $index);
    }

    public function multi()
    {
        return $this->redis->multi();
    }

    public function exec()
    {
        return $this->redis->exec();
    }

    public function scan($it, $pattern, $count)
    {
        return $this->redis->scan($it, $pattern, $count);
    }

    public function delete($key)
    {
        $boolean = $this->redis->delete($key);
        return $boolean;
    }

    public function exists($key)
    {
        $boolean = $this->redis->exists($key);
        return $boolean;
    }


}
