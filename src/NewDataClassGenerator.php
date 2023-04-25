<?php

namespace Itwmw\Validate\Attributes\Mysql;

use Itwmw\Validation\Support\Str;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Trait_;
use PhpParser\BuilderFactory;

class NewDataClassGenerator extends Generator
{
    public function make(string $table, string $namespace_string = null, string $class_name = null): string
    {
        $columns = $this->getTableColumns($table);
        if (empty($columns)) {
            throw new RuntimeException("Table {$table} not found");
        }
        [$rules, $allClass] = $this->getRules($columns);

        $fields           = array_keys($rules);
        $builder          = new BuilderFactory();
        $namespace_string = is_null($namespace_string) ? $this->config->getNamespacePrefix() : $namespace_string;
        $namespace        = $builder->namespace($namespace_string);

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
        $this->addFieldToClass($fieldHandler, $class);

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
                    $methodGenerator->addCreateFunc($fieldHandler);
                    $methodGenerator->addCallFunc();
                    $methodGenerator->addToStringFunc();
                }
            } else {
                if ($this->config->getAddFuncExtends()) {
                    $this->makeBaseDataClass();
                    $class->addStmt($builder->useTrait('BaseDataTrait'));
                } else {
                    $methodGenerator->addCreateFunc($fieldHandler);
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

    private function addFieldToClass(FieldHandler $handler, Class_|Trait_ $class): void
    {
        $handler->each(function (FieldInfo $fieldInfo) use ($class) {
            $field = $this->builderField($fieldInfo);
            $class->addStmt($field->getNode());
        });
    }
}
