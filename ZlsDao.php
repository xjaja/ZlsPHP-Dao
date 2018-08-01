<?php

/**
 * Class Zls_Dao
 * @method array getHideColumns
 */
abstract class Zls_Dao
{
    private $db;
    private $rs;
    private $_cacheTime = null;
    private $_cacheKey;

    public function __construct()
    {
        $this->db = Z::db();
    }

    /**
     * 获取字段列表（排除掉隐藏的字段）
     * @param      $field
     * @param bool $exPre
     * @return array|string
     */
    public function getReversalColumns($field = null, $exPre = false)
    {
        if (!$field && method_exists($this, 'getHideColumns')) {
            $field = static::getHideColumns();
        }
        //z::throwIf(!$field, 500,'[ '.get_class($this).'->getHideColumns() ] not found, did you forget to set ?');
        /** @noinspection PhpParamsInspection */
        $fields = array_diff(static::getColumns(), is_array($field) ? $field : ($field ? explode(',', $field) : []));

        return $exPre ? join($exPre, $fields) : $fields;
    }

    abstract public function getColumns();

    /**
     * 读取数据
     * @param      $data
     * @param null $field     字段
     * @param bool $replenish 自动补齐
     * @return array
     */
    public function readData($data, $field = null, $replenish = false)
    {
        if (!$field) {
            $field = static::getColumns();
        }

        return z::readData($field, $data, $replenish);
    }

    public function bean($row, $beanName = '')
    {
        return Z::bean($beanName ?: $this->getBean(), $row)->toArray();
    }

    public function getBean()
    {
        $beanName = strstr(get_class($this), 'Dao', false);
        $beanName = str_replace('Dao_', '', $beanName);
        $beanName = str_replace('Dao\\', '', $beanName);

        return $beanName;
    }

    public function beans($rows, $beanName = '')
    {
        $beanName = $beanName ?: $this->getBean();
        $objects = [];
        foreach ($rows as $row) {
            $object = Z::bean($beanName, $row, false);
            foreach ($row as $key => $value) {
                $method = "set" . Z::strSnake2Camel($key);
                $object->{$method}($value);
            }
            $objects[] = $object->toArray();
        }

        return $objects;
    }

    /**
     * 添加数据
     * @param array $data 需要添加的数据
     * @return int 最后插入的id，失败为0
     */
    public function insert($data)
    {
        $num = $this->getDb()->insert($this->getTable(), $data)->execute();

        return $num ? $this->getDb()->lastId() : 0;
    }

    /**
     * 获取Dao中使用的数据库操作对象
     * @return Zls_Database_ActiveRecord
     */
    public function &getDb()
    {
        return $this->db;
    }

    /**
     * 设置Dao中使用的数据库操作对象
     * @param Zls_Database_ActiveRecord $db
     * @return \Zls_Dao
     */
    public function setDb(Zls_Database_ActiveRecord $db)
    {
        $this->db = $db;

        return $this;
    }

    /**
     * 获取表名
     * @return string
     */
    public function getTable()
    {
        $className = str_replace('Dao', '', get_called_class());
        $className = str_replace('\\', '_', $className);
        $className = substr($className, 1);

        return Z::strCamel2Snake($className);
    }

    /**
     * 批量添加数据
     * @param array $rows 需要添加的数据
     * @return int 插入的数据中第一条的id，失败为0
     */
    public function insertBatch($rows)
    {
        $num = $this->getDb()->insertBatch($this->getTable(), $rows)->execute();

        return $num ? $this->getDb()->lastId() : 0;
    }

    /**
     * 更新数据
     * @param array     $data  需要更新的数据
     * @param array|int $where 可以是where条件关联数组，还可以是主键值。
     * @return boolean
     */
    public function update($data, $where)
    {
        $where = is_array($where) ? $where : [$this->getPrimaryKey() => $where];

        return $this->getDb()->where($where)->update($this->getTable(), $data)->execute();
    }

    /**
     * 获取主键
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->getDb()->from($this->getTable())->getPrimaryKey();
    }

    /**
     * 更新数据
     * @param array  $data  需要批量更新的数据
     * @param string $index 需要批量更新的数据中的主键名称
     * @return boolean
     */
    public function updateBatch($data, $index = null)
    {
        if (!$index) {
            $index = $this->getPrimaryKey();
        }

        return $this->getDb()->updateBatch($this->getTable(), $data, $index)->execute();
    }

    /**
     * 获取所有数据
     * @param array|null  $where   where条件数组
     * @param array|null  $orderBy 排序，比如：array('time'=>'desc')或者array('time'=>'desc','id'=>'asc')
     * @param int|null    $limit   limit数量，比如：10
     * @param string|null $fields  要搜索的字段，比如：id,name。留空默认*
     * @return array
     */
    public function findAll($where = null, array $orderBy = [], $limit = null, $fields = null)
    {
        if (!is_null($fields)) {
            $this->getDb()->select($fields);
        }
        if (!is_null($where)) {
            $this->getDb()->where($where);
        }
        foreach ($orderBy as $k => $v) {
            $this->getDb()->orderBy($k, $v);
        }
        if (!is_null($limit)) {
            $this->getDb()->limit(0, $limit);
        }
        if (!is_null($this->_cacheTime)) {
            $this->getDb()->cache($this->_cacheTime, $this->_cacheKey);
        }
        $this->rs = $this->getDb()->from($this->getTable())->execute();
        $this->cache();

        return $this->rs->rows();
    }

    public function cache($cacheTime = 0, $cacheKey = '')
    {
        $this->_cacheTime = (int)$cacheTime;
        $this->_cacheKey = $cacheKey;

        return $this;
    }

    /**
     * 根据条件获取一个字段的值或者数组
     * @param string       $col     字段名称
     * @param string|array $where   可以是一个主键的值或者主键的值数组，还可以是where条件
     * @param boolean      $isRows  返回多行记录还是单行记录，true：多行，false：单行
     * @param array        $orderBy 当返回多行记录时，可以指定排序，比如：array('time'=>'desc')或者array('time'=>'desc','id'=>'asc')
     * @return array
     */
    public function findCol($col, $where, $isRows = false, array $orderBy = [])
    {
        $row = $this->find($where, $isRows, $orderBy);
        if (!$isRows) {
            return isset($row[$col]) ? $row[$col] : null;
        } else {
            $vals = [];
            foreach ($row as $v) {
                $vals[] = $v[$col];
            }

            return $vals;
        }
    }

    /**
     * 获取一条或者多条数据
     * @param string|array $values  可以是一个主键的值或者主键的值数组，还可以是where条件
     * @param boolean      $isRows  返回多行记录还是单行记录，true：多行，false：单行
     * @param array        $orderBy 当返回多行记录时，可以指定排序，比如：array('time'=>'desc')或者array('time'=>'desc','id'=>'asc')
     * @param string|null  $fields  要搜索的字段，比如：id,name。留空默认*
     * @return array
     */
    public function find($values, $isRows = false, array $orderBy = [], $fields = null)
    {
        if (!is_null($fields)) {
            $this->getDb()->select($fields);
        }
        if (!empty($values)) {
            if (is_array($values)) {
                $is_asso = array_diff_assoc(array_keys($values), range(0, sizeof($values))) ? true : false;
                if ($is_asso) {
                    $this->getDb()->where($values);
                } else {
                    $this->getDb()->where([$this->getPrimaryKey() => array_values($values)]);
                }
            } else {
                $this->getDb()->where([$this->getPrimaryKey() => $values]);
            }
        }
        foreach ($orderBy as $k => $v) {
            $this->getDb()->orderBy($k, $v);
        }
        if (!$isRows) {
            $this->getDb()->limit(0, 1);
        }
        if (!is_null($this->_cacheTime)) {
            $this->getDb()->cache($this->_cacheTime, $this->_cacheKey);
        }
        $this->rs = $this->getDb()->from($this->getTable())->execute();
        $this->cache();
        if ($isRows) {
            return $this->rs->rows();
        } else {
            return $this->rs->row();
        }
    }

    public function reaultset()
    {
        return $this->rs;
    }

    /**
     * 根据条件删除记录
     * @param string $values 可以是一个主键的值或者主键主键的值数组
     * @param array  $cond   附加的where条件，关联数组
     * @return int|boolean  成功则返回影响的行数，失败返回false
     */
    public function delete($values = null, array $cond = null)
    {
        if (empty($values) && empty($cond)) {
            return 0;
        }
        if (!empty($values)) {
            $this->getDb()->where([$this->getPrimaryKey() => is_array($values) ? array_values($values) : $values]);
        }
        if (!empty($cond)) {
            $this->getDb()->where($cond);
        }

        return $this->getDb()->delete($this->getTable())->execute();
    }

    /**
     * 分页方法
     * @param int    $page          第几页
     * @param int    $pagesize      每页多少条
     * @param string $url           基础url，里面的{page}会被替换为实际的页码
     * @param string $fields        select的字段，全部用*，多个字段用逗号分隔
     * @param array  $where         where条件，关联数组
     * @param array  $orderBy       排序字段，比如：array('time'=>'desc')或者array('time'=>'desc','id'=>'asc')
     * @param int    $pageBarACount 分页条a的数量
     * @return array
     */
    public function getPage(
        $page = 1,
        $pagesize = 10,
        $url = '{page}',
        $fields = '*',
        array $where = null,
        array $orderBy = [],
        $pageBarACount = 6
    ) {
        $data = [];
        if (is_array($where)) {
            $this->getDb()->where($where);
        }
        $total = $this->getDb()->select('count(*) as total')
                      ->from($this->getTable())
                      ->execute()
                      ->value('total');
        if (is_array($where)) {
            $this->getDb()->where($where);
        }
        foreach ($orderBy as $k => $v) {
            $this->getDb()->orderBy($k, $v);
        }
        if ($page < 1) {
            $page = 1;
        }
        if ($pagesize < 1) {
            $pagesize = 1;
        }
        $data['items'] = $this->getDb()
                              ->select($fields)
                              ->limit(($page - 1) * $pagesize, $pagesize)
                              ->from($this->getTable())->execute()->rows();
        $data['page'] = Z::page($total, $page, $pagesize, $url, $pageBarACount);

        return $data;
    }

    /**
     * SQL搜索
     * @param int    $page          第几页
     * @param int    $pagesize      每页多少条
     * @param string $url           基础url，里面的{page}会被替换为实际的页码
     * @param string $fields        select的字段，全部用*，多个字段用逗号分隔
     * @param string $cond          是条件字符串，SQL语句where后面的部分，不要带limit
     * @param array  $values        $cond中的问号的值数组，$cond中使用?可以防止sql注入
     * @param int    $pageBarACount 分页条a的数量，可以参考手册分页条部分
     * @return array
     */
    public function search(
        $page = 1,
        $pagesize = 10,
        $url = '{page}',
        $fields = '*',
        $cond = '',
        array $values = [],
        $pageBarACount = 10
    ) {
        $data = [];
        $table = $this->getDb()->getTablePrefix() . $this->getTable();
        $rs = $this->getDb()
                   ->execute(
                       'select count(*) as total from ' . $table . (strpos(trim($cond), 'order') === 0 ? ' ' : ' where ') . $cond,
                       $values
                   );
        $total = $rs->total() > 1 ? $rs->total() : $rs->value('total');
        $data['items'] = $this->getDb()
                              ->execute(
                                  'select ' . $fields . ' from ' . $table .
                                  (strpos(trim($cond), 'order') === 0 ? ' ' : ' where ') .
                                  $cond . ' limit ' . (($page - 1) * $pagesize) . ',' . $pagesize,
                                  $values
                              )
                              ->rows();
        $data['page'] = Z::page($total, $page, $pagesize, $url, $pageBarACount);

        return $data;
    }
}
