<?php

namespace Itwmw\Validate\Attributes\Mysql;

use PhpParser\Comment\Doc;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Builder;
use PhpParser\Lexer;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\NodeVisitor;
use PhpParser\PrettyPrinter;

class UpdateDataClassGenerator extends Generator
{
    public function make(string $table, string $phpCode, string $namespace_string = null, string $class_name = null): bool|string
    {
        $lexer = new Lexer\Emulative([
            'usedAttributes' => [
                'comments',
                'startLine', 'endLine',
                'startTokenPos', 'endTokenPos',
            ],
        ]);
        $parser    = new Parser\Php7($lexer);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeVisitor\CloningVisitor());
        $printer = new PrettyPrinter\Standard([
            'shortArraySyntax' => true
        ]);

        try {
            $oldStmts  = $parser->parse($phpCode);
            $oldTokens = $lexer->getTokens();
            $newStmts  = $traverser->traverse($oldStmts);
        } catch (Error) {
            return false;
        }
        $class     = null;
        $namespace = null;
        $allUse    = [];
        foreach ($newStmts as $node) {
            if ($node instanceof Stmt\Namespace_) {
                $namespace = &$node;
                foreach ($node->stmts as &$stmt) {
                    if ($stmt instanceof Stmt\Class_) {
                        $class = &$stmt;
                    } elseif ($stmt instanceof Stmt\Trait_) {
                        $class = &$stmt;
                    } elseif ($stmt instanceof Stmt\Use_) {
                        $use      = implode('\\', $stmt->uses[0]->name->parts ?? []);
                        $allUse[] = $use;
                    }
                }
                break;
            }
        }
        unset($stmt);
        unset($node);
        if (is_null($class)) {
            return false;
        }

        $namespace->name = new Name($namespace_string ?: $this->config->getNamespacePrefix());

        // class 和 trait的类型转换
        $newClass = null;
        if ($class instanceof Stmt\Class_ && $this->config->getGenerateTrait()) { // 将class转trait
            $newClass = new Builder\Trait_($class->name);
        } elseif ($class instanceof Stmt\Trait_ && !$this->config->getGenerateTrait()) { // 将trait转class
            $newClass = new Builder\Class_($class->name);
        }

        if (!is_null($newClass)) {
            // 处理继承部分
            if ($this->config->getAddFuncExtends()) {
                $this->makeBaseDataClass();
                if ($newClass instanceof Builder\Class_) {
                    $use = $this->config->getNamespacePrefix() . '\\BaseData';
                    if (!in_array($use, $allUse) && $use !== $namespace_string . '\\BaseData') {
                        array_unshift($namespace->stmts, (new Builder\Use_($use, Stmt\Use_::TYPE_NORMAL))->getNode());
                    }
                    $newClass->extend('BaseData');
                } elseif ($newClass instanceof Builder\Trait_) {
                    $use = $this->config->getNamespacePrefix() . '\\BaseDataTrait';
                    if (!in_array($use, $allUse) && $use !== $namespace_string . '\\BaseDataTrait') {
                        array_unshift($namespace->stmts, (new Builder\Use_($use, Stmt\Use_::TYPE_NORMAL))->getNode());
                    }
                    $class->stmts[] = (new Builder\TraitUse('BaseDataTrait'))->getNode();
                }
            }

            $newClass             = $newClass->getNode();
            $newClass->attrGroups = $class->attrGroups;
            $newClass->stmts      = $class->stmts;
            if ($newClass instanceof Stmt\Class_) {
                foreach ($newClass->stmts as $index => $stmt) {
                    if ($stmt instanceof Stmt\TraitUse) {
                        foreach ($stmt->traits as $i => $t) {
                            if ('BaseDataTrait' === $t->parts[0]) {
                                unset($stmt->traits[$i]);
                                break;
                            }
                        }
                        if (0 === count($stmt->traits)) {
                            unset($newClass->stmts[$index]);
                            break;
                        }
                    }
                }
            }

            $class = $newClass;
        }

        $class->name = new Identifier(is_null($class_name) ? ucfirst($table) : $class_name);

        // 处理原有属性和方法
        $classStmts = &$class->stmts;
        /** @var Stmt\Property[] $properties */
        $properties  = [];
        $addFuncName = ['create', '__call', '__toString', 'toArray', '__construct'];
        foreach ($classStmts as $index => $stmt) {
            // 属性全部删除，方便以后排序添加
            if ($stmt instanceof Stmt\Property) {
                $properties[$stmt->props[0]->name->name] = $stmt;
                unset($classStmts[$index]);
            }

            if ($stmt instanceof Stmt\ClassMethod) {
                // 删除方法，重新写入
                if (in_array($stmt->name->name, $addFuncName)) {
                    unset($classStmts[$index]);
                }
            }
        }

        // 获取当前表数据
        $columns                = $this->getTableColumns($table);
        list($rules, $allClass) = $this->getRules($columns);
        $fieldHandler           = new FieldHandler($rules, $columns, $this->typeMap);
        $fields                 = $fieldHandler->getFields();

        // 补全缺失的类引入
        $missingUse = array_diff($allClass, $allUse);
        foreach ($missingUse as $use) {
            array_unshift($namespace->stmts, (new Builder\Use_($use, Stmt\Use_::TYPE_NORMAL))->getNode());
        }

        // 找出当前类中的Mysql字段
        $mysqlFields = [];
        foreach ($properties as $key => $property) {
            foreach ($property->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    if (str_starts_with($attr->name->parts[0], 'Mysql')) {
                        $mysqlFields[$key] = $property;
                    }
                }
            }
        }

        // 删除不存在的Mysql属性
        $removeFields = array_diff(array_keys($mysqlFields), array_keys($fields));
        $properties   = array_diff_key($properties, array_flip($removeFields));

        // 更新已存在的Mysql属性,保留用户自定义的验证规则
        $updateFields = array_intersect(array_keys($fields), array_keys($mysqlFields));
        $numericTypes = ['int', '?int', 'float', '?float'];

        foreach ($updateFields as $fieldName) {
            $property = $this->builderField($fields[$fieldName]);

            $oldAttribute = [];
            $oldMysqlRule = '';
            foreach ($properties[$fieldName]->attrGroups as $attrGroup) {
                $attributeName = $attrGroup->attrs[0]->name->parts[0];
                if (str_starts_with($attributeName, 'Mysql')) {
                    $oldMysqlRule = $attributeName;
                }
                $oldAttribute[$attributeName] = $attrGroup->attrs[0];
            }

            $newAttribute = [];
            foreach ($property->getNode()->attrGroups as $attrGroup) {
                $attributeName                = $attrGroup->attrs[0]->name->parts[0];
                $newAttribute[$attributeName] = $attrGroup->attrs[0];
            }

            // 删除旧的字段验证规则，以新的为准
            unset($oldAttribute[$oldMysqlRule]);

            // 删除掉旧字段附带的验证规则
            if (in_array($this->getPropertyType($properties[$fieldName]), $numericTypes)) {
                if (in_array('Numeric', array_keys($oldAttribute))) {
                    unset($oldAttribute['Numeric']);
                }
            } elseif('array' === $this->getPropertyType($properties[$fieldName])) {
                if (in_array('ArrayRule', array_keys($oldAttribute))) {
                    unset($oldAttribute['ArrayRule']);
                }
            }

            // 还原用户自定义的属性
            $appendAttribute = array_diff_key($oldAttribute, $newAttribute);
            foreach ($appendAttribute as $attribute) {
                $property->addAttribute($attribute);
            }

            $properties[$fieldName] = $property->getNode();
        }

        // 添加新增的Mysql属性
        /** @var FieldInfo[] $appendFields */
        $appendFields = array_diff_key($fields, $mysqlFields);
        foreach ($appendFields as $field) {
            $property                 = $this->builderField($field);
            $properties[$field->name] = $property->getNode();
        }

        // 添加属性到class
        array_unshift($classStmts, ...array_values($properties));

        // 添加基础方法
        $methodGenerator = new GenerateFunc($this->config, $class);

        if ($this->config->getAddFunc()) {
            if (!$this->config->getAddFuncExtends()) {
                $methodGenerator->addCreateFunc($fieldHandler);
                $methodGenerator->addCallFunc();
                $methodGenerator->addToStringFunc();
            }

            $comment = $this->getMethodComment($fieldHandler);
            $methodGenerator->addToArrayFunc(array_keys($fields));
        }

        // 处理类注释
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
            $class->setDocComment(new Doc($this->makeComment($comment)));
        }

        $newCode = $printer->printFormatPreserving($newStmts, $oldStmts, $oldTokens);
        if ($this->config->getAddFunc()) {
            $fixToArrayFunc = new FixToArrayFunc($newCode);
            $newCode        = $fixToArrayFunc->fix();
        }
        return $this->fixPhpCode($newCode);
    }

    private function getPropertyType(Stmt\Property $property)
    {
        return $property->type->name ?? $property->type->type->name;
    }
}
