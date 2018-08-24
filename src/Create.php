<?php

namespace Zls\Dao;

use Z;

/**
 * Zls.
 *
 * @author        影浅
 * @email         seekwe@gmail.com
 *
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 *
 * @see          ---
 * @since         v0.0.1
 * @updatetime    2018-08-01 13:00
 */
class Create
{
    public function bean($columns)
    {
        $fields = [];
        $fieldTemplate = "    //{comment}\n    protected \${column0};";
        foreach ($columns as $value) {
            $column = str_replace(' ', '', ucwords(str_replace('_', ' ', $value['name'])));
            $column0 = $value['name'];
            /*$column1 = lcfirst($column);*/
            $fields[] = str_replace(
                ['{column0}', '{comment}'],
                [$column0, $value['comment']],
                $fieldTemplate
            );
        }
        $code = "\n{fields}\n\n";
        $code = str_replace(
            ['{fields}'],
            [implode("\n\n", $fields)],
            $code
        );

        return $code;
    }

    public function dao($columns, $table)
    {
        $primaryKey = '';
        $_columns = [];
        foreach ($columns as $value) {
            if ($value['primary']) {
                $primaryKey = $value['name'];
            }
            $_columns[] = '\''.$value['name']."'//".$value['comment'].PHP_EOL.'               ';
        }
        $columnsString = 'array('.PHP_EOL.'              '.implode(',', $_columns).')';
        $code = "public function getColumns() {\n        return {columns};\n    }\n\n    public function getHideColumns() {\n        return array();\n    }\n\n    public function getPrimaryKey() {\n        return '{primaryKey}';\n    }\n\n    public function getTable() {\n        return '{table}';\n    }\n";
        if (false !== strpos(z::getOpt(1), 'bean')) {
            $code .= "\n    public function getBean() {\n        return parent::getBean();\n    }\n";
        }
        $code = str_replace(['{columns}', '{primaryKey}', '{table}'], [$columnsString, $primaryKey, $table], $code);

        return $code;
    }
}
