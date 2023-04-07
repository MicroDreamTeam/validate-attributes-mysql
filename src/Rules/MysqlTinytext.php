<?php

namespace Itwmw\Validate\Attributes\Mysql\Rules;

use Attribute;
use Itwmw\Validate\Attributes\Rules\RuleInterface;

/**
 * 最多存储 255 字节的文本字段，存储时在内容前使用 1 字节表示内容的字节数
 * */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class MysqlTinytext implements RuleInterface
{
    protected array $args = [];

    public function __construct(int $length = 2 ^ 8 -1)
    {
        $this->args = func_get_args();
    }

    public function getArgs(): array
    {
        return $this->args;
    }
}
