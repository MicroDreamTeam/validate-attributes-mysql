<?php

namespace Itwmw\Validate\Attributes\Mysql\Rules;

use Attribute;
use Itwmw\Validate\Attributes\Rules\RuleInterface;

/**
 * 单精度浮点数
 *
 * 取值范围从 -3.402823466E+38 到 -1.175494351E-38、0 以及从 1.175494351E-38 到 3.402823466E+38
 * */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class MysqlFloat implements RuleInterface
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
