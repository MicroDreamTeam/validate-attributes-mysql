<?php

namespace Itwmw\Validate\Attributes\Mysql\Rules;

use Attribute;
use Itwmw\Validate\Attributes\Rules\RuleInterface;

/**
 * Int类型,4 字节整数 别称：integer
 *
 * 有符号范围从 -2147483648 到 2147483647，无符号范围从 0 到 4294967295
 * */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class MysqlInt implements RuleInterface
{
    protected array $args = [];

    public function __construct(bool $unsigned = false)
    {
        $this->args = func_get_args();
    }

    public function getArgs(): array
    {
        return $this->args;
    }
}
