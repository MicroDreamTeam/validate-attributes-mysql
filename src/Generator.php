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
use Itwmw\Validation\Support\Str;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Trait_;
use PhpParser\BuilderFactory;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;
use SplFileInfo;
use Stringable;
use W7\Validate\Support\Processor\ProcessorExecCond;
use W7\Validate\Support\Processor\ProcessorSupport;

/**
 * @internal
 */
class Generator
{
    protected MysqlConnection $connection;

    protected Mysql $mysql;

    protected Config $config;

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
        $this->config = $config ?? Config::instance();
        $this->checkConfig();
        $this->typeMap    = array_merge($this->typeMap, $this->config->getTypeMap());
        $this->connection = new MysqlConnection();
        $this->connection->setConnection($config->getMysqlConnection());
        $this->mysql = new Mysql($this->connection);
    }

    private function checkConfig(): void
    {
        if ($this->config->getGenerateSetter() || $this->config->getGenerateTrait()) {
            if ($this->config->getAddFuncExtends() && Config::PROPERTY_SCOPE_PRIVATE === $this->config->getPropertyScope()) {
                throw new RuntimeException('当使用继承来扩展方法时，获取器和修改器无法访问私有属性');
            }
        }

        if ($this->config->getGenerateSetter() && $this->config->getPropertyReadOnly()) {
            throw new RuntimeException('当属性为只读类型时，无法使用修改器');
        }
    }

    public function makeDataClass(string $table, string $namespace_string = null, string $class_name = null): string
    {
        $columns = $this->getTableColumns($table);
        if (empty($columns)) {
            throw new RuntimeException("Table {$table} not found");
        }
        $rules    = [];
        $allClass = [];

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
            && !$this->config->getGenerateTrait()
            && !empty($namespace_string)
        ) {
            $allClass[] = Stringable::class;
        }

        if ($namespace_string !== $this->config->getNamespacePrefix() && $this->config->getAddFuncExtends()) {
            if ($this->config->getGenerateTrait()) {
                $allClass[] = $this->config->getNamespacePrefix() . '\BaseDataTrait';
            } else {
                $allClass[] = $this->config->getNamespacePrefix() . '\BaseData';
            }
        }

        foreach ($allClass as $class) {
            $namespace->addStmt($builder->use($class)->getNode());
        }

        if ($this->config->getGenerateTrait()) {
            $class = $builder->trait(is_null($class_name) ? ucfirst($table) : $class_name);
        } else {
            $class = $builder->class(is_null($class_name) ? ucfirst($table) : $class_name);
        }

        $methodGenerator = new GenerateFunc($this->config, $class);
        $fieldHandler    = new FieldHandler($rules, $columns, $this->typeMap);
        $this->addFieldToClass($fieldHandler, $class, $builder);

        if ($this->config->getUseConstruct()) {
            $methodGenerator->addConstructFunc($fieldHandler);
        }

        $comment = '';
        if ($this->config->getAddFunc()) {
            if ($this->config->getGenerateTrait() || $this->config->getGenerateSetter()) {
                $namespace->addStmt($builder->use(Str::class)->getNode());
            }

            if (!$this->config->getGenerateTrait()) {
                if ($this->config->getAddFuncExtends()) {
                    $this->makeBaseDataClass();
                    $class->extend('BaseData');
                } else {
                    $class->implement(Stringable::class);
                    $methodGenerator->addCreateFunc();
                    $methodGenerator->addCallFunc();
                    $methodGenerator->addToStringFunc();
                }
            } else {
                if ($this->config->getAddFuncExtends()) {
                    $this->makeBaseDataClass();
                    $class->addStmt($builder->useTrait('BaseDataTrait'));
                } else {
                    $methodGenerator->addCreateFunc();
                    $methodGenerator->addCallFunc();
                    $methodGenerator->addToStringFunc();
                }

            }

            $methodGenerator->addToArrayFunc($fields);
            $comment = $this->getMethodComment($fieldHandler);
        }

        if ($this->config->getAddComment()) {
            $tableComment = $this->mysql->getTableComment($table);

            if (!empty($tableComment)) {
                if (empty($comment)) {
                    $comment = $tableComment;
                } else {
                    $comment = "$tableComment\n\n" . $comment;
                }
            }
        }

        if (!empty($comment)) {
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
        $ruleClass = 'Itwmw\\Validate\\Mysql\\Rules\\Attributes\\Mysql' . ucfirst($type);
        if (!class_exists($ruleClass)) {
            return null;
        }

        $args = [];
        if (in_array($type, FieldHandler::$unsigned)) {
            $args[] = $column->unsigned;
        }
        if (in_array($type, FieldHandler::$length)) {
            $args[] = $column->length;
        }
        if (in_array($type, FieldHandler::$precision)) {
            $args[] = $column->precision;
        }

        if ('enum' === $column->type || 'set' === $column->type) {
            $args = $column->options;
        }

        $rules[] = [
            $ruleClass, ...$args
        ];

        if (in_array($type, FieldHandler::$numeric)) {
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
                    FieldHandler::getDefaultValue($column->default, $type),
                    ProcessorExecCond::WHEN_EMPTY
                ];
            }

        }

        return $rules;
    }

    public static function getPhpCode(array $ast): string
    {
        $prettyPrinter = new Standard([
            'shortArraySyntax' => true
        ]);

        return $prettyPrinter->prettyPrintFile($ast);
    }

    public static function fixPhpCode(string $php): string
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
        $baseGenerator = new GenerateBaseClass($this->config);
        if (!$baseGenerator->checkClassNeedUpdate()) {
            return;
        }

        $baseGenerator->generateTrait();
        $baseGenerator->generateClass();
    }

    public static function makeComment(string $comment): string
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

    private function addFieldToClass(FieldHandler $handler, Class_|Trait_ $class, BuilderFactory $builder): void
    {
        $propertyScope    = 'make' . ucfirst($this->config->getPropertyScope());
        $propertyReadOnly = $this->config->getPropertyReadOnly();
        $handler->each(function (FieldInfo $fieldInfo, string $key) use (
            $builder,
            $propertyScope,
            $propertyReadOnly,
            $class
        ) {
            $field = $builder->property($key);
            $field->$propertyScope();
            if ($propertyReadOnly) {
                $field->makeReadonly();
            }

            $field->setType($fieldInfo->type);
            if (!$propertyReadOnly && !($fieldInfo->default instanceof None)) {
                $field->setDefault($fieldInfo->default);
            }

            foreach ($fieldInfo->attribute as $item) {
                $field->addAttribute($item);
            }

            $class->addStmt($field->getNode());
        });
    }

    private function getMethodComment(FieldHandler $handler): string
    {
        $addGetter = $this->config->getGenerateGetter();
        $addSetter = $this->config->getGenerateSetter() && !$this->config->getPropertyReadOnly();
        if (!$addGetter && !$addSetter) {
            return '';
        }

        $getterMethodComments = [];
        $setterMethodComments = [];

        $handler->each(function (FieldInfo $fieldInfo, string $key) use (
            $addGetter,
            $addSetter,
            &$getterMethodComments,
            &$setterMethodComments
        ) {
            $key = Str::studly($key);
            if ($addGetter) {
                $getterMethodComments[] = "@method {$fieldInfo->commentType} get${key}()";
            }

            if ($addSetter) {
                $paramKey               = lcfirst($key);
                $setterMethodComments[] = "@method \$this set${key}({$fieldInfo->commentType} \$$paramKey)";
            }
        });

        return implode("\n", array_merge($getterMethodComments, $setterMethodComments));
    }
}
