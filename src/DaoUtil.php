<?php

namespace Zls\Dao;

trait DaoUtil
{
    protected static $openDataTime = true;
    protected static $dataTimeFormat = [
        'createKey' => 'create_time',
        'updateKey' => 'update_time',
        'value'     => '',
    ];

    final protected static function setDataTime($method, &$data)
    {
        if (!static::$openDataTime) {
            return;
        }
        $time = static::$dataTimeFormat['value'] ?: date('Y-m-d H:i:s');
        if (in_array($method, ['insert', 'update'], true)) {
            $data[static::$dataTimeFormat['updateKey']] = $time;
            if ($method === 'insert') {
                $data[static::$dataTimeFormat['createKey']] = $time;
            }
        } elseif (in_array($method, ['insertBatch', 'updateBatch'], true)) {
            foreach ($data as &$_data) {
                $_data[static::$dataTimeFormat['updateKey']] = $time;
                if ($method === 'insertBatch') {
                    $_data[static::$dataTimeFormat['createKey']] = $time;
                }
            }
        }
    }

    final protected static function setDataTimeFormat($insertKey, $updateKey, $value = null)
    {
        static::$dataTimeFormat = [
            'createKey' => $insertKey,
            'updateKey' => $updateKey,
            'value'     => $value,
        ];
    }


    protected static $openSoftDelete = true;
    protected static $softDeleteFormat = [
        'key'   => 'status',
        'value' => 0,
    ];

    protected static function softFind(\Zls_Database_ActiveRecord $db, $method)
    {
        $db->where([static::$softDeleteFormat['key'] . ' !=' => static::$softDeleteFormat['value']]);
    }

    protected static function softDelete(\Zls_Database_ActiveRecord $db, $wheres)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $table = (new self())->getTable();
        $data  = [static::$softDeleteFormat['key'] => static::$softDeleteFormat['value']];
        $db->where([static::$softDeleteFormat['key'] . ' !=' => static::$softDeleteFormat['value']]);
        if (static::$openDataTime) {
            $data[static::$dataTimeFormat['updateKey']] = static::$dataTimeFormat['value'] ?: date('Y-m-d H:i:s');
        }

        return $db->update($table, $data)->execute();
    }

    public static function insertBefore(\Zls_Database_ActiveRecord $db, $method, &$data)
    {
        static::setDataTime($method, $data);
    }

    public static function updateBefore(\Zls_Database_ActiveRecord $db, $method, &$data)
    {
        static::setDataTime($method, $data);
    }
}
