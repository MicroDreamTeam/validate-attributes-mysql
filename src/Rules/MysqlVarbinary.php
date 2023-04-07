<?php

namespace Itwmw\Validate\Attributes\Mysql\Rules;

use Attribute;
use Itwmw\Validate\Attributes\Rules\RuleInterface;

/**
 * 类似于 VARCHAR 类型，但其存储的是二进制字节串而不是非二进制字符串
 * */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class MysqlVarbinary implements RuleInterface
{
    protected array $args = [];

    public function __construct(int $length = 2 ^ 16 - 1)
    {
        $this->args = func_get_args();
    }

    public function getArgs(): array
    {
        return $this->args;
    }
}
