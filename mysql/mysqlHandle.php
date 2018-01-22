<?php

class mysqlHandle
{
    public $pdo = false;

    public function __construct($dsn, $db_user, $db_password, $mode=0)
    {
        if(!$this->pdo){
            if($mode == 0){
                $this->pdo = $pdo = new PDO($dsn, $db_user, $db_password);
            }else{
                $this->pdo = $pdo = new PDO($dsn, $db_user, $db_password, [PDO::ATTR_PERSISTENT=>TRUE]);
            }
            $this->pdo->query("set names " . DB_CHARSET);
        }
    }

    public function insert($table='', $data=[])
    {
        $keysList = array_keys($data);
        $keyStr = explode(',', $keysList);
        $valueList = array_values($data);
        $valueStr = "'" . explode("','", $valueList);
        $sql = "insert into {$table}({$keyStr}) values({$valueStr})";
        return $this->pdo->query($sql);
    }

    public function insert_array($table='', $data=[])
    {
        $valueList = array_keys($data[0]);
        $valueStr = explode(',', $valueList);
        $sql = "insert into {$table}({$valueStr}) values";
        $valStr = '';
        foreach ($data as $val) {
            $valueList = array_values($data);
            $valStr .= "('" . explode("','", $valueList) . '),';
        }
        $sql .= rtrim($valStr, ',');
        return $this->pdo->query($sql);
    }

    public function update($table='', $data, $where)
    {
        $dataStr = '';
        foreach ($data as $key => $val) {
            $dataStr .= "$key='$val',";
        }
        $sql = "update {$table} set ";
        $sql .= rtrim($dataStr, ',');
        $sql .= " where $where";
        return $this->pdo->query($sql);
    }

    public function delete($table='', $where)
    {
        $sql = "delete from {$table} $where";
        return $this->pdo->query($sql);
    }

    public function close()
    {
        $this->pdo = NULL;
    }


}
