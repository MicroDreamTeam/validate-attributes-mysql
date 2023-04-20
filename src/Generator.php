<?php

namespace Itwmw\Validate\Attributes\Mysql;

use Itwmw\Table\Structure\Mysql\Column;
use Itwmw\Table\Structure\Mysql\Mysql;
use Itwmw\Table\Structure\Mysql\MysqlConnection;
use Itwmw\Validate\Attributes\Preprocessor;
use Itwmw\Validate\Attributes\Rules\ArrayRule;
use Itwmw\Validate\Attributes\Rules\Nullable;
use Itwmw\Validate\Attributes\Rules\Numeric;
use Itwmw\Validate\Attributes\Rules\Required;
use Itwmw\Validate\Attributes\Rules\RuleInterface;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Trait_;
use PhpParser\BuilderFactory;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\PrettyPrinter\Standard;
use SplFileInfo;
use W7\Validate\Support\Processor\ProcessorExecCond;
use W7\Validate\Support\Processor\ProcessorSupport;

class Generator
{
    protected MysqlConnection $connection;

    protected Mysql $mysql;

    protected Config $config;

    protected array $length = [
        'binary',
        'bit',
        'char',
        'decimal',
        'double',
        'float',
        'varchar',
        'varbinary',
    ];

    protected array $numeric = [
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

    protected array $precision = [
        'decimal',
        'double',
        'float',
    ];

    protected array $unsigned = [
        'bigint',
        'decimal',
        'double',
        'float',
        'int',
        'mediumint',
        'smallint',
        'tinyint'
    ];

    protected array $typeMap = [
        'bigint'          => 'int',
        'binary'          => 'string',
        'bit'             => 'int',
        'blob'            => 'string',
        'bool'            => 'bool',
        'boolean'         => 'bool',
        'char'            => 'string',
        'date'            => 'string',
        'datetime'        => 'string',
        'decimal'         => 'float',
        'double'          => 'float',
        'enum'            => 'string',
        'float'           => 'float',
        'geometry'        => 'string',
        'geomcollection'  => 'string',
        'int'             => 'int',
        'json'            => 'string',
        'longblob'        => 'string',
        'longtext'        => 'string',
        'linestring'      => 'string',
        'mediumblob'      => 'string',
        'mediumint'       => 'int',
        'mediumtext'      => 'string',
        'multipoint'      => 'string',
        'multilinestring' => 'string',
        'multipolygon'    => 'string',
        'point'           => 'string',
        'polygon'         => 'string',
        'set'             => 'string',
        'smallint'        => 'int',
        'text'            => 'string',
        'time'            => 'string',
        'timestamp'       => 'string',
        'tinyblob'        => 'string',
        'tinyint'         => 'int',
        'tinytext'        => 'string',
        'varbinary'       => 'string',
        'varchar'         => 'string',
        'year'            => 'int',
    ];

    public function __construct(Config $config = null)
    {
        $this->config     = $config ?? Config::instance();
        $this->typeMap    = array_merge($this->typeMap, $this->config->getTypeMap());
        $this->connection = new MysqlConnection();
        $this->connection->setConnection($config->getMysqlConnection());
        $this->mysql = new Mysql($this->connection);
    }

    public function makeDataClass(string $table, string $namespace_string = null, string $class_name = null): string
    {
        $columns = $this->getTableColumns($table);
        if (empty($columns)) {
            throw new \RuntimeException("Table {$table} not found");
        }
        $rules         = [];
        $allClass      = [];
        $methodComment = '';

        foreach ($columns as $column) {
            $rules[$column->field] = $this->makeValidateRule($column);
            $allRules              = [];
            foreach ($rules[$column->field] as $rule) {
                $this->makeAst($allClass, $allRules, $rule);
            }
            $rules[$column->field] = $allRules;
        }

        $fields           = array_keys($rules);
        $builder          = new BuilderFactory();
        $namespace_string = is_null($namespace_string) ? $this->config->getNamespacePrefix() : $namespace_string;
        $namespace        = $builder->namespace($namespace_string);
        $allClass         = array_unique($allClass);

        if ($this->config->getAddFunc()
            && !$this->config->getAddFuncExtends()
            && !empty($namespace_string)
        ) {
            $allClass[] = \Stringable::class;
        }
        if ($namespace_string !== $this->config->getNamespacePrefix()) {
            $allClass[] = $this->config->getNamespacePrefix() . '\BaseData';
        }

        foreach ($allClass as $class) {
            $namespace->addStmt($builder->use($class)->getNode());
        }

        if ($this->config->getGenerateTrait()) {
            $class = $builder->trait(is_null($class_name) ? ucfirst($table) : $class_name);
        } else {
            $class = $builder->class(is_null($class_name) ? ucfirst($table) : $class_name);
        }

        $this->addFieldToClass($rules, $class, $columns, $builder, $this->config->getUseConstruct(), $methodComment);

        if ($this->config->getAddFunc()) {
            if (!$this->config->getGenerateTrait()) {
                if ($this->config->getAddFuncExtends()) {
                    $this->makeBaseDataClass();
                    $class->extend('BaseData');
                } else {
                    $class->implement(\Stringable::class);
                    $this->addToStringFunc($class, $builder);
                    $this->addCallFunc($class, $builder);
                }
            } else {
                $this->addToStringFunc($class, $builder);
                $this->addCallFunc($class, $builder);
            }

            $this->addToArrayFunc($class, $builder, $fields);
        }
        if ($this->config->getAddComment()) {
            $tableComment = $this->mysql->getTableComment($table);

            $comment = '';
            if (!empty($tableComment)) {
                $comment = "$tableComment\n\n" . $methodComment;
            }
            $class->setDocComment($this->makeComment($comment));
        }
        $namespace->addStmt($class);
        $ast = $namespace->getNode();
        $php = $this->getPhpCode([$ast]);
        if ($this->config->getAddFunc()) {
            $fixToArrayFunc = new FixToArrayFunc($php);
            $php            = $fixToArrayFunc->fix();
        }
        return $this->fixPhpCode($php);
    }

    protected function paramsToAst(mixed $value): DNumber|ConstFetch|LNumber|String_|Array_
    {
        if (is_string($value)) {
            return new String_($value);
        } elseif (is_int($value)) {
            return new LNumber($value);
        } elseif (is_float($value)) {
            return new DNumber($value);
        } elseif (is_bool($value)) {
            return new ConstFetch(new Name($value ? 'true' : 'false'));
        } elseif (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                $items[] = new ArrayItem($this->paramsToAst($item));
            }
            return new Array_($items);
        } elseif (is_null($value)) {
            return new ConstFetch(new Name('null'));
        }
        throw new \Exception('暂不支持该类型');
    }

    protected function makeAst(array &$allClass, array &$allRules, mixed $rule): void
    {
        if (is_subclass_of($rule, RuleInterface::class)) {
            $allClass[] = $rule;
            $allRules[] = new Attribute(new Name(basename($rule)));
        } elseif (is_array($rule)) {
            $class      = $rule[0];
            $args       = [];
            $allClass[] = $class;
            if (is_subclass_of($class, RuleInterface::class)) {
                foreach (array_slice($rule, 1) as $arg) {
                    $args[] = $this->paramsToAst($arg);
                }
                $allRules[] = new Attribute(new Name(basename($class)), $args);
            } elseif (is_a($class, Preprocessor::class) || Preprocessor::class === $class) {
                foreach (array_slice($rule, 1) as $arg) {
                    if ($arg instanceof ProcessorSupport) {
                        $allClass[] = get_class($arg);
                        $args[]     = new ClassConstFetch(new Name(basename(get_class($arg))), $arg->name);
                    } else {
                        $args[] = $this->paramsToAst($arg);
                    }
                }
                $allRules[] = new Attribute(new Name(basename($class)), $args);
            }
        }
    }

    /**
     * @param string $table
     * @return Column[]
     */
    private function getTableColumns(string $table): array
    {
        return $this->mysql->listTableColumns($table);
    }

    private function makeValidateRule(Column $column): ?array
    {
        $type      = $column->type;
        $rules     = [];
        $ruleClass = 'Itwmw\\Validate\\Attributes\\Mysql\\Rules\\Mysql' . ucfirst($type);
        if (!class_exists($ruleClass)) {
            return null;
        }

        $args = [];
        if (in_array($type, $this->unsigned)) {
            $args[] = $column->unsigned;
        }
        if (in_array($type, $this->length)) {
            $args[] = $column->length;
        }
        if (in_array($type, $this->precision)) {
            $args[] = $column->precision;
        }

        if ('enum' === $column->type || 'set' === $column->type) {
            $args = $column->options;
        }

        $rules[] = [
            $ruleClass, ...$args
        ];

        if (in_array($type, $this->numeric)) {
            $rules[] = [Numeric::class];
        }

        if ('json' === $type && 'array' === strtolower($this->typeMap[$type])) {
            $rules[] = [ArrayRule::class];
        }

        if ('set' === $type && 'array' === strtolower($this->typeMap[$type])) {
            $rules[] = [ArrayRule::class, '@keyInt'];
        }

        if ($column->notNull) {
            $rules[] = Required::class;
        } else {
            $rules[] = Nullable::class;
        }

        if (null !== $column->default) {
            if ('set' === $type && 'array' === strtolower($this->typeMap[$type])) {
                $rules[] = [
                    Preprocessor::class,
                    explode(',', $column->default),
                    ProcessorExecCond::WHEN_EMPTY
                ];
            } else {
                $rules[] = [
                    Preprocessor::class,
                    $this->getDefaultValue($column->default, $type),
                    ProcessorExecCond::WHEN_EMPTY
                ];
            }

        }

        return $rules;
    }

    private function getDefaultValue(mixed $default, string $type)
    {
        if (is_null($default)) {
            return null;
        }

        if (in_array($type, $this->numeric)) {
            return (int)$default;
        } elseif ('string' === $type) {
            return (string)$default;
        } elseif (in_array($type, $this->precision)) {
            return (float)$default;
        } elseif ('array' === $type) {
            return explode(',', $default);
        }

        return $default;
    }

    private function getPhpCode(array $ast): string
    {
        $prettyPrinter = new Standard([
            'shortArraySyntax' => true
        ]);

        return $prettyPrinter->prettyPrintFile($ast);
    }

    private function fixPhpCode(string $php): string
    {
        $fix = new Fixer();
        // 使用内置规则先强制修复一次
        $tmpFile = tempnam(sys_get_temp_dir(), 'php');
        file_put_contents($tmpFile, $php);
        $php = $fix->fix(new SplFileInfo($tmpFile), true);
        @unlink($tmpFile);
        // 使用用户的规则再修复一次
        $tmpFile = tempnam(sys_get_temp_dir(), 'php');
        file_put_contents($tmpFile, $php);
        $php = $fix->fix(new SplFileInfo($tmpFile));
        @unlink($tmpFile);
        return $php;
    }

    private function makeBaseDataClass(): void
    {
        if (!empty($namespace = $this->config->getNamespacePrefix())) {
            if (!empty($this->config->getBaseNamespace())) {
                $namespace = str_replace($this->config->getBaseNamespace(), '', $namespace);
            }
            $baseDataPhpPath = $this->config->getBasePath()
                . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $namespace)
                . DIRECTORY_SEPARATOR
                . '/BaseData.php';
        } else {
            $baseDataPhpPath = $this->config->getBasePath() . '/BaseData.php';
        }

        if (file_exists($baseDataPhpPath)) {
            return;
        }

        $builder   = new BuilderFactory();
        $namespace = $builder->namespace($this->config->getNamespacePrefix());
        if (!empty($this->config->getNamespacePrefix())) {
            $namespace->addStmt($builder->use(\Stringable::class)->getNode());
        }
        $class = $builder->class('BaseData')->implement(\Stringable::class);
        $this->addToStringFunc($class, $builder);
        $this->addBaseToArrayFunc($class, $builder);
        $this->addCallFunc($class, $builder, true);
        $namespace->addStmt($class);
        $ast = $namespace->getNode();
        $php = $this->getPhpCode([$ast]);
        $php = $this->fixPhpCode($php);
        file_put_contents($baseDataPhpPath, $php);
    }

    private function makeComment(string $comment): string
    {
        $comments = explode("\n", $comment);
        foreach ($comments as $index => $comment) {
            if ($index > 0) {
                $comments[$index] = ' * ' . str_replace('*/', "*\/", $comment);
            }
        }
        $comment = implode("\n", $comments);
        return "/**\n * {$comment}\n */";
    }

    private function addFieldToClass(array $rules, Class_|Trait_ $class, array $columns, BuilderFactory $builder, bool $useConstruct = false, string &$methodComment = ''): void
    {
        $methodComment        = '';
        $params               = [];
        $comments             = [];
        $getterMethodComments = [];
        $setterMethodComments = [];
        $addGetter            = $this->config->getGenerateGetter();
        $addSetter            = $this->config->getGenerateSetter() && !$this->config->getPropertyReadOnly();
        $propertyScope        = 'make' . ucfirst($this->config->getPropertyScope());
        $propertyReadOnly     = $this->config->getPropertyReadOnly();
        foreach ($rules as $key => $value) {
            $field = $builder->property($key);
            $field->$propertyScope();
            if ($propertyReadOnly) {
                $field->makeReadonly();
            }
            $type = strtolower($this->typeMap[$columns[$key]->type]) ?? 'mixed';
            if (!$columns[$key]->notNull && 'mixed' !== $type) {
                $default = $this->getDefaultValue($columns[$key]->default, $type);
                $type    = "?$type";
                $field->setType($type);
                if (!$propertyReadOnly) {
                    $field->setDefault($default);
                }
                if ($useConstruct) {
                    $param = $builder->param($key);
                    $param->setType($type);
                    $param->setDefault($default);
                    $params[$key] = [$param, true];
                }
            } else {
                $field->setType($type);
                $default = $this->getDefaultValue($columns[$key]->default, $type);
                if ($useConstruct) {
                    $param = $builder->param($key);
                    $param->setType($type);
                }
                if (!is_null($default)) {
                    if (!$propertyReadOnly) {
                        $field->setDefault($default);
                    }
                    if ($useConstruct) {
                        $param->setDefault($default);
                        $params[$key] = [$param, true];
                    }
                } else {
                    if ($useConstruct) {
                        if ($this->config->getConstructAllOptional()) {
                            $param->setDefault($this->getDefaultForType($type));
                            $params[$key] = [$param, true];
                        } else {
                            $params[$key] = [$param, false];
                        }
                    }
                }
            }
            if ($this->config->getAddComment()) {
                $comment = '';
                if (!empty($columns[$key]->comment)) {
                    $comment = $columns[$key]->comment;
                    $field->setDocComment($this->makeComment($comment));
                }
                $commentType = str_replace('?', 'null|', $type);
                if ($useConstruct) {
                    $comment    = "@param $commentType \$$key $comment";
                    $comments[] = $comment;
                }

                $key = ucfirst($key);
                if ($addGetter) {
                    $getterMethodComments[] = "@method $commentType get${key}()";

                }

                if ($addSetter) {
                    $setterMethodComments[] = "@method \$this set${key}($commentType \$$key)";
                }
            }

            foreach ($value as $item) {
                $field->addAttribute($item);
            }
            $class->addStmt($field->getNode());
        }

        $methodComment = implode("\n", array_merge($getterMethodComments, $setterMethodComments));

        if ($useConstruct) {
            $method = $builder->method('__construct');
            if (!$this->config->getConstructAllOptional()) {
                uasort($params, function ($a, $b) {
                    return $a[1] <=> $b[1];
                });
            }
            array_map(fn ($param) => $method->addParam($param[0]), $params);
            foreach ($params as $key => $param) {
                $method->addStmt(new Expression(new Assign(new PropertyFetch(new Variable('this'), $key), new Variable($key))));
            }

            if ($this->config->getAddComment()) {
                $comment = $this->makeComment(implode("\n", $comments));
                $method->setDocComment($comment);
            }
            $class->addStmt($method->getNode());
        }
    }

    private function addToArrayFunc(Class_|Trait_ $class, BuilderFactory $builder, array $fields): void
    {
        $method = $builder->method('toArray');
        $method->makePublic();
        $method->setReturnType('array');
        $array = new Array_();
        foreach ($fields as $field) {
            $array->items[] = new ArrayItem(new PropertyFetch(new Variable('this'), $field), new String_($field));
        }
        $method->addStmt(new Return_($array));
        $class->addStmt($method->getNode());
    }

    private function addBaseToArrayFunc(Class_|Trait_ $class, BuilderFactory $builder): void
    {
        $toArrayFunc = $builder->method('toArray');
        $toArrayFunc->makePublic();
        $toArrayFunc->setReturnType('array');
        $toArrayFunc->addStmt(new Return_(
            new \PhpParser\Node\Expr\Cast\Array_(new Variable('this'))
        ));
        $class->addStmt($toArrayFunc->getNode());
    }

    private function addToStringFunc(Trait_|Class_ $class, BuilderFactory $builder): void
    {
        $toStringFunc = $builder->method('__toString');
        $toStringFunc->makePublic();
        $toStringFunc->setReturnType('string');
        $toStringFunc->addStmt(new Return_(
            new FuncCall(new Name('json_encode'), [
                new Arg(new FuncCall(new Name('$this->toArray'))),
                new Arg(new ConstFetch(new Name('JSON_UNESCAPED_UNICODE')))
            ])
        ));
        $class->addStmt($toStringFunc->getNode());
    }

    private function addCallFunc(Trait_|Class_ $class, BuilderFactory $builder, bool $force = false): void
    {
        if (false === $force && !$this->config->getGenerateGetter()) {
            if (!$this->config->getGenerateSetter() || $this->config->getPropertyReadOnly()) {
                return;
            }
        }

        $callFunc = $builder->method('__call');
        $callFunc->makePublic();
        $callFunc->addParam($builder->param('name'));
        $callFunc->addParam($builder->param('arguments'));
        $stmts  = [];
        $prefix = new FuncCall(new Name('substr'), [
            new Arg(new Variable('name')),
            new Arg(new LNumber(0)),
            new Arg(new LNumber(3))
        ]);
        $stmts[]  = new Expression(new Assign(new Variable('prefix'), $prefix));
        $property = new FuncCall(new Name('lcfirst'), [
            new Arg(new FuncCall(new Name('substr'), [
                new Arg(new Variable('name')),
                new Arg(new LNumber(3))
            ]))
        ]);
        $stmts[]          = new Expression(new Assign(new Variable('property'), $property));
        $ifPropertyExists = new FuncCall(new Name('property_exists'), [
            new Arg(new Variable('this')),
            new Arg(new Variable('property'))
        ]);
        $getter = new If_(new Equal(left: new Variable('prefix'), right: new String_('get')), [
            'stmts' => [
                new Return_(new PropertyFetch(new Variable('this'), new Variable('property')))
            ]
        ]);

        if ($this->config->getWritePropertyValidate()) {
            $setterExpression = [
                new Expression(new Assign(new Variable('data'), new FuncCall(new Name('validate_attribute'), [
                    new Arg(new ClassConstFetch(class:new Name(['static']), name: new Identifier('class'))),
                    new Arg(value: new Array_([
                        new ArrayItem(value: new ArrayDimFetch(new Variable('arguments'), new LNumber(0)), key: new Variable('property')),
                    ])),
                    new Arg(value: new Array_([
                        new ArrayItem(value: new Variable('property')),
                    ])),
                ]))),
                new Expression(new Assign(
                    new PropertyFetch(new Variable('this'), new Variable('property')),
                    new PropertyFetch(new Variable('data'), new Variable('property'))
                )),
            ];
        } else {
            $setterExpression = [
                new Expression(
                    new Assign(
                        new PropertyFetch(new Variable('this'), new Variable('property')),
                        new ArrayDimFetch(new Variable('arguments'), new LNumber(0))
                    )
                )
            ];
        }

        $setter = new If_(new Equal(left: new Variable('prefix'), right: new String_('set')), [
            'stmts' => [
                ...$setterExpression,
                new Return_(new Variable('this'))
            ]
        ]);
        $callSubFunc = [];
        if (!$this->config->getPropertyReadOnly() && $this->config->getGenerateSetter() || $force) {
            $callSubFunc[] = $setter;
        }

        if ($this->config->getGenerateGetter() || $force) {
            $callSubFunc[] = $getter;
        }

        $stmts[] = new If_(cond: $ifPropertyExists, subNodes:[
            'stmts' => $callSubFunc
        ]);

        $callFunc->addStmts($stmts);
        $class->addStmt($callFunc->getNode());
    }

    private function getDefaultForType(string $type): mixed
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
