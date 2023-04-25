<?php

namespace Itwmw\Validate\Attributes\Mysql;

use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;

/**
 * @internal
 */
class FieldInfo
{
    /**
     * @param string $name
     * @param string $type
     * @param string|null $comment
     * @param string $commentType
     * @param array<Attribute|AttributeGroup> $attribute
     */
    public function __construct(
        public string $name = '',
        public string $type = '',
        public ?string $comment = null,
        public string $commentType = 'mixed',
        public array $attribute = []
    ) {
    }
}
