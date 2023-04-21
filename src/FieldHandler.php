<?php

namespace Itwmw\Validate\Attributes\Mysql;

class FieldHandler
{
    /** @var array<FieldInfo>  */
    protected array $fields = [];

    public static array $length = [
        'binary',
        'bit',
        'char',
        'decimal',
        'double',
        'float',
        'varchar',
        'varbinary',
    ];

    public static array $numeric = [
        'bigint',
        'bit',
        'decimal',
        'double',
        'float',
        'int',
        'mediumint',
        'smallint',
        'tinyint'
    ];

    public static array $precision = [
        'decimal',
        'double',
        'float',
    ];

    public static array $unsigned = [
        'bigint',
        'decimal',
        'double',
        'float',
        'int',
        'mediumint',
        'smallint',
        'tinyint'
    ];

    public function __construct(array $rules, array $columns, protected array $typeMap)
    {
        foreach ($rules as $key => $value) {
            $field = new FieldInfo($key);
            $type  = strtolower($this->typeMap[$columns[$key]->type]) ?? 'mixed';
            if (!$columns[$key]->notNull && 'mixed' !== $type) {
                $field->default = $this->getDefaultValue($columns[$key]->default, $type);
                $field->type    = "?$type";
            } else {
                $field->type = $type;
                $default     = $this->getDefaultValue($columns[$key]->default, $type);
                if (!is_null($default)) {
                    $field->default = $default;
                }
            }

            $field->commentType = str_replace('?', 'null|', $field->type);
            $field->comment     = $columns[$key]->comment;
            $field->attribute   = $value;
            $this->fields[$key] = $field;
        }
    }

    public function sort(): static
    {
        uasort($this->fields, function (FieldInfo $a, FieldInfo $b) {
            return $b->default instanceof None <=> $a->default instanceof None;
        });
        return $this;
    }

    public function addDefault(): static
    {
        $this->each(function (FieldInfo $field) {
            if ($field->default instanceof None) {
                $field->default = $this->getDefaultForType($field->type);
            }
        });
        return $this;
    }

    public function each(callable $callback): static
    {
        foreach ($this->fields as $key => $item) {
            if (false === $callback($item, $key)) {
                break;
            }
        }
        return $this;
    }

    public static function getDefaultValue(mixed $default, string $type)
    {
        if (is_null($default)) {
            return null;
        }

        if (in_array($type, self::$numeric)) {
            return (int)$default;
        } elseif ('string' === $type) {
            return (string)$default;
        } elseif (in_array($type, self::$precision)) {
            return (float)$default;
        } elseif ('array' === $type) {
            return explode(',', $default);
        }

        return $default;
    }

    public static function getDefaultForType(string $type): mixed
    {
        if (str_contains($type, '?')) {
            return null;
        }

        return match ($type) {
            'int', 'float' => 0,
            'bool'   => false,
            'string' => '',
            'array'  => [],
            default  => null,
        };
    }
}
