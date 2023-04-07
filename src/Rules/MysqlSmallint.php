<?php

namespace Itwmw\Validate\Attributes\Mysql\Rules;

use Attribute;
use Itwmw\Validate\Attributes\Rules\RuleInterface;

/**
 * 2 字节整数
 *
 * 有符号范围从 -32768 到 32767，无符号范围从 0 到 65535
 * */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class MysqlSmallint implements RuleInterface
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
