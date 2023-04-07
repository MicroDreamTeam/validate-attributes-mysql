<?php

namespace Itwmw\Validate\Attributes\Mysql\Rules;

use Attribute;
use Itwmw\Validate\Attributes\Rules\RuleInterface;

/**
 * 双精度浮点数
 *
 * 别名为：real，注：REAL_AS_FLOAT SQL 模式时它 real 是 FLOAT 的别名
 *
 * 取值范围从 -1.7976931348623157E+308 到 -2.2250738585072014E-308、0 以及从 2.2250738585072014E-308 到 1.7976931348623157E+308
 * */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class MysqlDouble implements RuleInterface
{
    protected array $args = [];

    public function __construct(bool $unsigned = false, int $length = -1, int $precision = -1)
    {
        $this->args = func_get_args();
    }

    public function getArgs(): array
    {
        return $this->args;
    }
}
