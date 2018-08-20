<?php

/**
 * Class Zls_Dao
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

    public function getColumns()
    {
        /**
         * @var \Zls\Command\Create\Mysql $CommandCreateMysql
         */
        $CommandCreateMysql = z::extension('Command\Create\Mysql');

        return array_keys($CommandCreateMysql->getTableFieldsInfo(static::getTable(), static::getDb()));
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
     * 获取Dao中使用的数据库操作对象
     * @return Zls_Database_ActiveRecord
     */
    public function &getDb()
    {
        return $this->Db;
    }

    /**
     * 设置Dao中使用的数据库操作对象
     * @param Zls_Database_ActiveRecord $Db
     * @return $this
     */
    public function setDb(Zls_Database_ActiveRecord $Db)
    {
        $this->Db = $Db;

        return $this;
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
        $this->getDb()->insert($this->getTable(), $data);
        $Before = static::insertBefore($this->getDb(), 'insert');
        if (is_null($Before)) {
            $num = $this->getDb()->execute();

            return $num ? $this->getDb()->lastId() : 0;
        } else {
            return $Before;
        }
    }

    /**
     * 新增前置
     * @param Zls_Database_ActiveRecord $db
     * @param string                    $method
     * @return void|int
     */
    public static function insertBefore($db, $method)
    {

    }

    /**
     * 批量添加数据
     * @param array $rows 需要添加的数据
     * @return int 插入的数据中第一条的id，失败为0
     */
    public function insertBatch($rows)
    {
        $this->getDb()->insertBatch($this->getTable(), $rows);
        $Before = static::insertBefore($this->getDb(), 'insertBatch');
        if (is_null($Before)) {
            $num = $this->getDb()->execute();

            return $num ? $this->getDb()->lastId() : 0;
        } else {
            return $Before;
        }
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
        $this->getDb()->where($where)->update($this->getTable(), $data);
        $Before = static::updateBefore($this->getDb(), 'update');

        return is_null($Before) ? $this->getDb()->execute() : $Before;
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
     * 更新前置
     * @param Zls_Database_ActiveRecord $db
     * @param string                    $method
     * @return void|boolean|array|int
     */
    public static function updateBefore($db, $method)
    {
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
        $this->getDb()->updateBatch($this->getTable(), $data, $index);
        $Before = static::updateBefore($this->getDb(), 'updateBatch');

        return is_null($Before) ? $this->getDb()->execute() : $Before;
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
        if (!$fields) {
            $fields = $this->getReversalColumns(null, ',');
        }
        $this->getDb()->select($fields);
        if (!is_null($where)) {
            $this->getDb()->where($where);
        }
        foreach ($orderBy as $k => $v) {
            $this->getDb()->orderBy($k, $v);
        }
        if (!is_null($limit)) {
            $this->getDb()->limit(0, $limit);
        }
        if (!is_null($this->CacheTime)) {
            $this->getDb()->cache($this->CacheTime, $this->CacheKey);
        }
        $this->getDb()->from($this->getTable());
        $result = static::findBefore($this->getDb(), 'findAll');
        if (is_null($result)) {
            $this->Rs = $this->getDb()->execute();
            $result = $this->Rs->rows();
        }

        return z::tap($result, function () {
            $this->__destruct();
        });
    }

    /**
     * 获取字段列表（排除掉隐藏的字段）
     * @param      $field
     * @param bool $exPre
     * @return array|string
     */
    public function getReversalColumns($field = null, $exPre = false)
    {
        if (!$field) {
            $field = static::getHideColumns();
        }
        //z::throwIf(!$field, 500,'[ '.get_class($this).'->getHideColumns() ] not found, did you forget to set ?');
        /** @noinspection PhpParamsInspection */
        $fields = array_diff(static::getColumns(), is_array($field) ? $field : ($field ? explode(',', $field) : []));

        return $exPre ? join(is_string($exPre) ? $exPre : ',', $fields) : $fields;
    }

    public function getHideColumns()
    {
        return [];
    }

    /**
     * 查询前置
     * @param Zls_Database_ActiveRecord $db
     * @param string                    $method
     * @return void|boolean|array|int
     */
    public static function findBefore($db, $method)
    {

    }

    public function __destruct()
    {
        $this->cache();
    }

    /**
     * 设置缓存
     * @param int    $cacheTime
     * @param string $cacheKey
     * @return $this
     */
    public function cache($cacheTime = 0, $cacheKey = '')
    {
        $this->CacheTime = (int)$cacheTime;
        $this->CacheKey = $cacheKey;

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
        if (!$fields) {
            $fields = $this->getReversalColumns(null, true);
        }
        $this->getDb()->select($fields);
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
        if (!is_null($this->CacheTime)) {
            $this->getDb()->cache($this->CacheTime, $this->CacheKey);
        }
        $this->getDb()->from($this->getTable());
        $result = static::findBefore($this->getDb(), 'find');
        if (is_null($result)) {
            $this->Rs = $this->getDb()->execute();
            $result = $isRows ? $this->Rs->rows() : $this->Rs->row();
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
        $Before = static::deleteBefore($this->getDb(), 'delete');

        return is_null($Before) ? $this->getDb()->delete($this->getTable())->execute() : $Before;
    }

    /**
     * 删除前置
     * @param Zls_Database_ActiveRecord $db
     * @param string                    $method
     * @return void|boolean|array|int
     */
    public static function deleteBefore($db, $method)
    {

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
        $fields = null,
        array $where = null,
        array $orderBy = [],
        $pageBarACount = 6
    ) {
        $data = [];
        if (is_array($where)) {
            $this->getDb()->where($where);
        }
        if (!$fields) {
            $fields = $this->getReversalColumns(null, true);
        }
        $this->getDb()->select('count(*) as total')
             ->from($this->getTable());
        static::findBefore($this->getDb(), 'getPage');
        $total = $this->getDb()->execute()->value('total');
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
        $this->getDb()
             ->select($fields)
             ->limit(($page - 1) * $pagesize, $pagesize)
             ->from($this->getTable());
        $result = static::findBefore($this->getDb(), 'getPage');
        if (is_null($result)) {
            $result = $this->getDb()->execute()->rows();
        }
        $data['items'] = $result;
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
        $fields = null,
        $cond = '',
        array $values = [],
        $pageBarACount = 10
    ) {
        $data = [];
        if (!$fields) {
            $fields = $this->getReversalColumns(null, true);
        }
        $table = $this->getDb()->getTablePrefix() . $this->getTable();
        /** @noinspection SqlNoDataSourceInspection */
        /** @noinspection SqlDialectInspection */
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
