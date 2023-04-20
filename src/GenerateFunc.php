<?php

namespace Itwmw\Validate\Attributes\Mysql;

use PhpParser\Builder\Class_;
use PhpParser\Builder\Trait_;
use PhpParser\BuilderFactory;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;

/**
 * @internal
 */
class GenerateFunc
{
    protected BuilderFactory $builder;
    public function __construct(protected Config $config, protected  Trait_|Class_ $class)
    {
        $this->builder = new BuilderFactory();
    }

    public function addCallFunc(bool $force = false): void
    {
        if (false === $force && !$this->config->getGenerateGetter()) {
            if (!$this->config->getGenerateSetter() || $this->config->getPropertyReadOnly()) {
                return;
            }
        }

        $callFunc = $this->builder->method('__call');
        $callFunc->makePublic();
        $callFunc->addParam($this->builder->param('name'));
        $callFunc->addParam($this->builder->param('arguments'));
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

        $stmts[] = new Return_(new StaticCall(new Name('parent'), new Identifier('__call'), [
            new Arg(new Variable('name')),
            new Arg(new Variable('arguments'))
        ]));

        $callFunc->addStmts($stmts);
        $this->class->addStmt($callFunc->getNode());
    }

    public function addCreateFunc(): void
    {
        $createFunc = $this->builder->method('create');
        $createFunc->makePublic()->makeStatic();
        $createFunc->setReturnType('static');

        $createFunc->addParam($this->builder->param('data')->setType('array'));

        $class  = new Expression(new Assign(new Variable('class'), new New_(new Name('static'))));
        $foreach = new Foreach_(new Variable('data'), new Variable('value'), [
            'keyVar' => new Variable('key'),
            'stmts'  => [
                new If_(new FuncCall(new Name('property_exists'), [
                    new Arg(new Variable('class')),
                    new Arg(new Variable('key'))
                ]), [
                    'stmts' => [
                        new Expression(
                            new Assign(
                                new PropertyFetch(
                                    new Variable('class'),
                                    new Variable('key')
                                ),
                                new Variable('value')
                            )
                        )
                    ]
                ])
            ]
        ]);

        $return = new Return_(new Variable('class'));

        $createFunc->addStmts([$class, $foreach, $return]);
        $this->class->addStmt($createFunc->getNode());
    }

    public function addToStringFunc(): void
    {
        $toStringFunc = $this->builder->method('__toString');
        $toStringFunc->makePublic();
        $toStringFunc->setReturnType('string');
        $toStringFunc->addStmt(new Return_(
            new FuncCall(new Name('json_encode'), [
                new Arg(new FuncCall(new Name('$this->toArray'))),
                new Arg(new ConstFetch(new Name('JSON_UNESCAPED_UNICODE')))
            ])
        ));
        $this->class->addStmt($toStringFunc->getNode());
    }

    public function addBaseToArrayFunc(): void
    {
        $toArrayFunc = $this->builder->method('toArray');
        $toArrayFunc->makePublic();
        $toArrayFunc->setReturnType('array');
        $toArrayFunc->addStmt(new Return_(
            new \PhpParser\Node\Expr\Cast\Array_(new Variable('this'))
        ));
        $this->class->addStmt($toArrayFunc->getNode());
    }

    public function addToArrayFunc(array $fields): void
    {
        $method = $this->builder->method('toArray');
        $method->makePublic();
        $method->setReturnType('array');
        $array = new Array_();
        foreach ($fields as $field) {
            $array->items[] = new ArrayItem(new PropertyFetch(new Variable('this'), $field), new String_($field));
        }
        $method->addStmt(new Return_($array));
        $this->class->addStmt($method->getNode());
    }
}
