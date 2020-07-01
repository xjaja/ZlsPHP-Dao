<?php

/**
 * Class Zls_Dao
 * @method int selectCount($where = null, $field = '*')
 * @method int selectSum($where = null, $field = 'primaryKey')
 * @method int selectMax($where = null, $field = 'primaryKey')
 * @method int selectMin($where = null, $field = 'primaryKey')
 * @method int selectAvg($where = null, $field = 'primaryKey')
 */
abstract class Zls_Dao
{
    private $Db;
    private $Rs;
    private $CacheTime = null;
    private $CacheKey;

    public function __construct()
    {
        $this->Db = Z::db();
    }

    public function __call($name, $args)
    {
        if (Z::strBeginsWith($name, 'select')) {
            $select = strtoupper(substr($name, 6));
            $where  = Z::arrayGet($args, 0, []);
            $db     = $this->getDb();
            is_callable($where) ? $where($db) : $db->where($where);
            $isAll  = ['COUNT'];
            $fields = Z::arrayGet($args, 1, in_array($select, $isAll) ? '*' : $this->getPrimaryKey());
            $db->from($this->getTable())->select("{$select}({$fields}) as {$select}");
            static::findBefore($db, $name);

            return $db->execute()->value($select);
        } else {
            $class = get_called_class();
            Z::throwIf(true, 500, "Call to undefined method {$class}->{$name}()");
        }
    }

    /**
     * 读取数据.
     *
     * @param      $data
     * @param null $field     字段
     * @param bool $replenish 自动补齐
     *
     * @return array
     */
    public function readData($data, $field = null, $replenish = false)
    {
        if (!$field) {
            $field = static::getColumns();
        }

        return Z::readData($field, $data, $replenish);
    }

    public function getColumns()
    {
        /**
         * @var \Zls\Command\Create\Mysql
         */
        $CommandCreateMysql = Z::extension('Command\Create\Mysql');

        return array_keys($CommandCreateMysql->getTableFieldsInfo(static::getTable(), static::getDb()));
    }

    /**
     * 获取表名.
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
     * 获取Dao中使用的数据库操作对象
     * @return Zls_Database_ActiveRecord
     */
    public function &getDb()
    {
        return $this->Db;
    }

    /**
     * 设置Dao中使用的数据库操作对象
     *
     * @param Zls_Database_ActiveRecord $Db
     *
     * @return $this
     */
    public function setDb(Zls_Database_ActiveRecord $Db)
    {
        $this->Db = $Db;

        return $this;
    }

    public function bean($row, $beanName = '')
    {
        return Z::bean($beanName ? $beanName : $this->getBean(), $row, false)->toArray();
    }

    protected function getBean()
    {
        $beanName = strstr(get_class($this), 'Dao', false);
        $beanName = str_replace('Dao_', '', $beanName);
        $beanName = str_replace('Dao\\', '', $beanName);
        try {
            if (z::strEndsWith($beanName, 'Dao')) {
                $newBeanName = substr($beanName, 0, -3) . 'Bean';
                z::bean($newBeanName);

                return $newBeanName;
            }
        } catch (\Zls_Exception_500 $e) {
            Z::throwIf(!Z::strEndsWith($e->getMessage(), 'not found'), 500, $e->getMessage());
        }

        return $beanName;
    }

    public function beans($rows, $beanName = '')
    {
        $beanName = $beanName ?: $this->getBean();
        $objects  = [];
        foreach ($rows as $row) {
            $object = Z::bean($beanName, $row, false);
            foreach ($row as $key => $value) {
                $method = 'set' . Z::strSnake2Camel($key);
                $object->{$method}($value);
            }
            $objects[] = $object->toArray();
        }

        return $objects;
    }

    /**
     * 添加数据.
     *
     * @param array $data 需要添加的数据
     *
     * @return int 最后插入的id，失败为0
     */
    public function insert($data)
    {
        $Before = static::insertBefore($this->getDb(), 'insert', $data);
        $this->getDb()->insert($this->getTable(), $data);
        if (is_null($Before)) {
            $num = $this->getDb()->execute();

            return $num ? $this->getDb()->lastId() : 0;
        } else {
            return $Before;
        }
    }

    /**
     * 新增前置.
     *
     * @param Zls_Database_ActiveRecord $db
     * @param string                    $method
     * @param array                     $data 批量添加时为多维数组
     *
     * @return void|int
     */
    public static function insertBefore(\Zls_Database_ActiveRecord $db, $method, &$data)
    {
    }

    /**
     * 批量添加数据.
     *
     * @param array $rows 需要添加的数据
     *
     * @return int 插入的数据中第一条的id，失败为0
     */
    public function insertBatch($rows)
    {
        $Before = static::insertBefore($this->getDb(), 'insertBatch', $rows);
        $this->getDb()->insertBatch($this->getTable(), $rows);
        if (is_null($Before)) {
            $num = $this->getDb()->execute();

            return $num ? $this->getDb()->lastId() : 0;
        } else {
            return $Before;
        }
    }

    /**
     * 更新数据.
     *
     * @param array     $data  需要更新的数据
     * @param array|int $where 可以是where条件关联数组，还可以是主键值
     *
     * @return bool
     */
    public function update($data, $where)
    {
        $where  = is_array($where) ? $where : [$this->getPrimaryKey() => $where];
        $db     = $this->getDb()->where($where);
        $Before = static::updateBefore($db, 'update', $data);
        $db->update($this->getTable(), $data);

        return is_null($Before) ? $db->execute() : $Before;
    }

    /**
     * 获取主键.
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->getDb()->from($this->getTable())->getPrimaryKey();
    }

    /**
     * 更新前置.
     *
     * @param Zls_Database_ActiveRecord $db
     * @param string                    $method
     * @param array                     $data 批量更新时为多维数组
     *
     * @return void|int
     */
    public static function updateBefore(\Zls_Database_ActiveRecord $db, $method, &$data)
    {
    }

    /**
     * 更新数据.
     *
     * @param array  $data  需要批量更新的数据
     * @param string $index 需要批量更新的数据中的主键名称
     *
     * @return bool
     */
    public function updateBatch($data, $index = null)
    {
        $db     = $this->getDb();
        $Before = static::updateBefore($this->getDb(), 'updateBatch', $data);
        $db->updateBatch($this->getTable(), $data, $index ?: $this->getPrimaryKey());

        return is_null($Before) ? $db->execute() : $Before;
    }

    /**
     * 获取所有数据.
     *
     * @param array|null     $where   where条件数组
     * @param array|null     $orderBy 排序，比如：array('time'=>'desc')或者array('time'=>'desc','id'=>'asc')
     * @param int|null|array $limit   limit数量，比如：10
     * @param string|null    $fields  要搜索的字段，比如：id,name。留空默认*
     *
     * @return array
     */
    public function findAll($where = null, array $orderBy = [], $limit = null, $fields = null)
    {
        $db = $this->getDb();
        $db->select($fields ?: $this->getReversalColumns(null, ','));
        foreach ($orderBy as $k => $v) {
            $db->orderBy($k, $v);
        }
        if (!is_null($limit)) {
            if (is_array($limit)) {
                list($offset, $count) = $limit;
            } else {
                $offset = 0;
                $count  = $limit;
            }
            $db->limit($offset, $count);
        }
        if (!is_null($this->CacheTime)) {
            $db->cache($this->CacheTime, $this->CacheKey);
        }
        $db->from($this->getTable());
        is_array($where) ? $db->where($where) : (is_callable($where) ? $where($db) : $db->where([$this->getPrimaryKey() => $where]));
        $result = static::findBefore($db, 'findAll');
        if (is_null($result)) {
            $this->Rs = $db->execute();
            $result   = $this->Rs->rows();
        }

        return z::tap($result, function () {
            $this->__destruct();
        });
    }

    /**
     * 获取字段列表（排除掉隐藏的字段）.
     *
     * @param      $field
     * @param bool $exPre
     *
     * @return array|string
     */
    public function getReversalColumns($field = null, $exPre = false)
    {
        if (!$field) {
            $field = static::getHideColumns();
        }
        // z::throwIf(!$field, 500,'[ '.get_class($this).'->getHideColumns() ] not found, did you forget to set ?');
        /** @noinspection PhpParamsInspection */
        $fields = array_diff(static::getColumns(), is_array($field) ? $field : ($field ? explode(',', $field) : []));

        return $exPre ? join(is_string($exPre) ? $exPre : ',', $fields) : $fields;
    }

    public function getHideColumns()
    {
        return [];
    }

    /**
     * 查询前置.
     *
     * @param Zls_Database_ActiveRecord $db
     * @param string                    $method
     *
     * @return void|array
     */
    public static function findBefore(\Zls_Database_ActiveRecord $db, $method)
    {
    }

    public function __destruct()
    {
        $this->cache();
    }

    /**
     * 设置缓存.
     *
     * @param int    $cacheTime
     * @param string $cacheKey
     *
     * @return $this
     */
    public function cache($cacheTime = 0, $cacheKey = '')
    {
        $this->CacheTime = (int)$cacheTime;
        $this->CacheKey  = $cacheKey;

        return $this;
    }

    /**
     * 根据条件获取一个字段的值或者数组.
     *
     * @param string                $col     字段名称
     * @param string|array|callable $where   可以是一个主键的值或者主键的值数组，还可以是where条件
     * @param bool                  $isRows  返回多行记录还是单行记录，true：多行，false：单行
     * @param array                 $orderBy 当返回多行记录时，可以指定排序，比如：array('time'=>'desc')或者array('time'=>'desc','id'=>'asc')
     *
     * @return string|array
     */
    public function findCol($col, $where, $isRows = false, array $orderBy = [])
    {
        $row = $this->find($where, $isRows, $orderBy, $col);
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
     * 获取一条或者多条数据.
     *
     * @param string|array|callable $values  可以是一个主键的值或者主键的值数组，还可以是where条件
     * @param bool                  $isRows  返回多行记录还是单行记录，true：多行，false：单行
     * @param array                 $orderBy 当返回多行记录时，可以指定排序，比如：array('time'=>'desc')或者array('time'=>'desc','id'=>'asc')
     * @param string|null           $fields  要搜索的字段，比如：id,name。留空默认*
     *
     * @return array
     */
    public function find($values, $isRows = false, array $orderBy = [], $fields = null)
    {
        $db = $this->getDb();
        $db->select($fields ?: $this->getReversalColumns(null, true));
        if (!!$orderBy) {
            foreach ($orderBy as $k => $v) {
                $db->orderBy($k, $v);
            }
        } else {
            $db->orderBy($this->getPrimaryKey(), 'asc');
        }
        if (!$isRows) {
            $db->limit(0, 1);
        }
        if (!is_null($this->CacheTime)) {
            $db->cache($this->CacheTime, $this->CacheKey);
        }
        $db->from($this->getTable());
        if (!empty($values)) {
            if (is_array($values)) {
                $isAsso = array_diff_assoc(array_keys($values), range(0, sizeof($values))) ? true : false;
                if ($isAsso) {
                    $db->where($values);
                } else {
                    $db->where([$this->getPrimaryKey() => array_values($values)]);
                }
            } else {
                is_callable($values) ? $values($db) : $db->where([$this->getPrimaryKey() => $values]);
            }
        }
        $result = static::findBefore($db, 'find');
        if (is_null($result)) {
            $this->Rs = $db->execute();
            $result   = $isRows ? $this->Rs->rows() : $this->Rs->row();
        }

        return z::tap($result, function () {
            $this->__destruct();
        });
    }

    public function reaultset()
    {
        return $this->Rs;
    }

    /**
     * 根据条件删除记录.
     *
     * @param string|array          $values 可以是一个主键的值或者主键主键的值数组
     * @param string|array|callable $cond   附加的where条件，关联数组
     *
     * @return int|bool 成功则返回影响的行数，失败返回false
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
        $Before = static::deleteBefore($this->getDb(), 'delete');

        return is_null($Before) ? $this->getDb()->delete($this->getTable())->execute() : $Before;
    }

    /**
     * 删除前置.
     *
     * @param Zls_Database_ActiveRecord $db
     * @param string                    $method
     *
     * @return void|int
     */
    public static function deleteBefore(\Zls_Database_ActiveRecord $db, $method)
    {
    }

    /**
     * 分页方法.
     *
     * @param int           $page          第几页
     * @param int           $pagesize      每页多少条
     * @param string        $url           基础url，里面的{page}会被替换为实际的页码
     * @param string        $fields        select的字段，全部用*，多个字段用逗号分隔
     * @param array|Closure $where         where条件，关联数组
     * @param array         $orderBy       排序字段，比如：array('time'=>'desc')或者array('time'=>'desc','id'=>'asc')
     * @param int           $pageBarACount 分页条的数量
     *
     * @return array
     */
    public function getPage($page = 1, $pagesize = 10, $url = '{page}', $fields = null, $where = null, array $orderBy = [], $pageBarACount = 6)
    {
        $fields   = $fields ?: $this->getReversalColumns(null, true);
        $pagesize = ((int)$pagesize > 0) ? $pagesize : 1;
        $pageRes  = $this->_pageCommon($page, $pagesize, $where, $orderBy, $fields);
        $result   = static::findBefore($this->getDb(), 'getPage');
        if (is_null($result)) {
            $result = $this->getDb()->execute()->rows();
        }

        return ['items' => $result, 'page' => Z::page($pageRes['total'], $page, $pagesize, $url, $pageBarACount)];
    }

    final private function _pageCommon($page, $pagesize, $where, $orderBy, $fields)
    {
        $total = $this->selectCount($where);
        if (is_array($where)) {
            $this->getDb()->where($where);
        } elseif ($where instanceof Closure) {
            $where($this->getDb());
        }
        foreach ($orderBy as $k => $v) {
            $this->getDb()->orderBy($k, $v);
        }
        $this->getDb()->select($fields)->from($this->getTable())
            ->limit((($page > 0) ? $page - 1 : 0) * $pagesize, $pagesize);

        return ['total' => $total];
    }

    public function getPageId($page = 1, $pagesize = 10, $where = null, array $orderBy = [], $idField = '')
    {
        if (!$idField) {
            $idField = $this->getPrimaryKey();
        }
        $pagesize = ((int)$pagesize > 0) ? $pagesize : 1;
        $pageRes  = $this->_pageCommon($page, $pagesize, $where, $orderBy, $idField);
        $result   = static::findBefore($this->getDb(), 'getPageId');
        if (is_null($result)) {
            $result = $this->getDb()->execute()->values($idField);
        }

        return ['total' => $pageRes['total'], 'ids' => $result];
    }

    /**
     * SQL搜索.
     *
     * @param int    $page          第几页
     * @param int    $pagesize      每页多少条
     * @param string $url           基础url，里面的{page}会被替换为实际的页码
     * @param string $fields        select的字段，全部用*，多个字段用逗号分隔
     * @param string $cond          是条件字符串，SQL语句where后面的部分，不要带limit
     * @param array  $values        $cond中的问号的值数组，$cond中使用?可以防止sql注入
     * @param int    $pageBarACount 分页条数量
     *
     * @return array
     * @deprecated   请使用getPage()，其中的$where支持闭包可以满足大部分需求
     */
    public function search($page = 1, $pagesize = 10, $url = '{page}', $fields = null, $cond = '', array $values = [], $pageBarACount = 10)
    {
        $data = [];
        if (!$fields) {
            $fields = $this->getReversalColumns(null, true);
        }
        $where = (0 === strpos(trim($cond), 'order') ? ' ' : ' where ') . ($cond ?: 'true');
        $table = $this->getDb()->getTablePrefix() . $this->getTable();
        /** @noinspection SqlNoDataSourceInspection */
        /** @noinspection SqlDialectInspection */
        $rs            = $this->getDb()
            ->execute(
                'select count(*) as total from ' . $table . $where,
                $values
            );
        $total         = $rs->total() > 1 ? $rs->total() : $rs->value('total');
        $data['items'] = $this->getDb()
            ->execute(
                'select ' . $fields . ' from ' . $table . $where . ' limit ' . (($page - 1) * $pagesize) . ',' . $pagesize,
                $values
            )
            ->rows();
        $data['page']  = Z::page($total, $page, $pagesize, $url, $pageBarACount);

        return $data;
    }
}
