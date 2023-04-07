<?php

namespace Itwmw\Validate\Attributes\Mysql\Rules;

use Attribute;
use Itwmw\Validate\Attributes\Rules\RuleInterface;

/**
 * 枚举，可从最多 65535 个值的列表中选择
 * */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class MysqlEnum implements RuleInterface
{
    protected array $args = [];

    public function __construct(...$value)
    {
        $this->args = func_get_args();
    }

    public function getArgs(): array
    {
        return $this->args;
    }
}
