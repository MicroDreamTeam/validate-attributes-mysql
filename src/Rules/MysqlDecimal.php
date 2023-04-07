<?php

namespace Itwmw\Validate\Attributes\Mysql\Rules;

use Attribute;
use Itwmw\Validate\Attributes\Rules\RuleInterface;

/**
 * Decimal类型,定点数（M，D），别称：numeric
 *
 * 整数部分（M）最大为 65（默认 10），小数部分（D）最大为 30（默认 0）
 * */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class MysqlDecimal implements RuleInterface
{
    protected array $args = [];

    public function __construct(bool $unsigned = false, int $length = 65, int $precision = -1)
    {
        $this->args = func_get_args();
    }

    public function getArgs(): array
    {
        return $this->args;
    }
}
