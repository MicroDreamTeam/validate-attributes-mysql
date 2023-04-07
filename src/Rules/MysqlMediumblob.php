<?php

namespace Itwmw\Validate\Attributes\Mysql\Rules;

use Attribute;
use Itwmw\Validate\Attributes\Rules\RuleInterface;

/**
 * 最多存储 16777215字节的 BLOB 字段，存储时在内容前使用 3 字节表示内容的字节数
 * */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class MysqlMediumblob implements RuleInterface
{
    protected array $args = [];

    public function __construct(int $length = 2 ^ 24 - 1)
    {
        $this->args = func_get_args();
    }

    public function getArgs(): array
    {
        return $this->args;
    }
}
