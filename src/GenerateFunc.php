<?php

namespace Itwmw\Validate\Attributes\Mysql;

use PhpParser\Builder;
use PhpParser\Builder\Class_ as BuilderClass;
use PhpParser\Builder\Trait_ as BuilderTrait;
use PhpParser\BuilderHelpers;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_ as NodeClass;
use PhpParser\Node\Stmt\Trait_ as NodeTrait;
use PhpParser\BuilderFactory;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\ElseIf_;
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

    public function __construct(protected Config $config, protected  BuilderClass|BuilderTrait|NodeClass|NodeTrait $class)
    {
        $this->builder = new BuilderFactory();
    }

    public function addStmt(Stmt|Builder $stmt)
    {
        if ($this->class instanceof BuilderTrait || $this->class instanceof BuilderClass) {
            return $this->class->addStmt($stmt);
        } else {
            $stmt                 = BuilderHelpers::normalizeNode($stmt);
            $this->class->stmts[] = $stmt;
            return $this->class;
        }
    }

    public function addCallFunc(bool $force = false): void
    {
        $addGetter = $this->config->getGenerateGetter();
        $addSetter = $this->config->getGenerateSetter() && !$this->config->getPropertyReadOnly();

        if (!$addGetter && !$addSetter && !$force) {
            return;
        }

        $callFunc = $this->builder->method('__call');
        $callFunc->makePublic();
        $callFunc->addParam($this->builder->param('name'));
        $callFunc->addParam($this->builder->param('arguments'));

        $prefix = new Expression(new Assign(
            new Variable('prefix'),
            new FuncCall(new Name('substr'), [
                new Arg(new Variable('name')),
                new Arg(new LNumber(0)),
                new Arg(new LNumber(3))
            ])
        ));

        if ($addSetter && $addGetter || $force) {
            $ifPrefixEqSetOrGet = new If_(new BooleanAnd(
                left: new NotIdentical(
                    left: new String_('set'),
                    right: new Variable('prefix')
                ),
                right: new NotIdentical(
                    left: new String_('get'),
                    right: new Variable('prefix')
                )
            ));
        } elseif ($addGetter) {
            $ifPrefixEqSetOrGet = new If_(new NotIdentical(
                left: new String_('get'),
                right: new Variable('prefix')
            ));
        } else {
            $ifPrefixEqSetOrGet = new If_(new NotIdentical(
                left: new String_('set'),
                right: new Variable('prefix')
            ));
        }

        $callParentCallFunc = new If_(new BooleanAnd(
            left: new FuncCall(
                name: new Name('class_parents'),
                args: [
                    new Arg(new ClassConstFetch(new Name('static'), 'class'))
                ]
            ),
            right: new FuncCall(
                name: new Name('method_exists'),
                args: [
                    new Arg(new ClassConstFetch(new Name('parent'), 'class')),
                    new Arg(new String_('__call'))
                ]
            )
        ));

        $returnParentCall = new Return_(new StaticCall(
            class: new Name('parent'),
            name: new Identifier('__call'),
            args: [
                new Arg(new Variable('name')),
                new Arg(new Variable('arguments'))
            ]
        ));

        $callParentCallFunc->stmts = [
            $returnParentCall
        ];

        $throwBadMethodCallException = new Throw_(new New_(new Name\FullyQualified('BadMethodCallException'), [
            new Arg(new FuncCall(new Name('sprintf'), [
                new Arg(new String_('Method %s::%s does not exist.')),
                new ClassConstFetch(new Name('static'), new Identifier('class')),
                new Arg(new Variable('name'))
            ]))
        ]));

        if ($this->config->getAddFuncExtends()) {
            $ifPrefixEqSetOrGet->stmts[] = $callParentCallFunc;
        }

        $ifPrefixEqSetOrGet->stmts[] = $throwBadMethodCallException;

        $funcProperty = new Expression(new Assign(new Variable('funcProperty'), new FuncCall(new Name('substr'), [
            new Arg(new Variable('name')),
            new Arg(new LNumber(3))
        ])));

        $property = new If_(new FuncCall(new Name('property_exists'), [
            new Arg(new Variable('this')),
            new Arg(new Variable('funcProperty'))
        ]));

        $property->stmts = [
            new Expression(new Assign(new Variable('property'), new Variable('funcProperty')))
        ];

        $propertySnake = new ElseIf_(new FuncCall(new Name('property_exists'), [
            new Arg(new Variable('this')),
            new Arg(new StaticCall(new Name('Str'), new Identifier('snake'), [
                new Arg(new Variable('funcProperty'))
            ]))
        ]));

        $propertySnake->stmts = [
            new Expression(new Assign(new Variable('property'), new StaticCall(new Name('Str'), new Identifier('snake'), [
                new Arg(new Variable('funcProperty'))
            ])))
        ];

        $propertyCamel = new ElseIf_(new FuncCall(new Name('property_exists'), [
            new Arg(new Variable('this')),
            new Arg(new StaticCall(new Name('Str'), new Identifier('camel'), [
                new Arg(new Variable('funcProperty'))
            ]))
        ]));

        $propertyCamel->stmts = [
            new Expression(new Assign(new Variable('property'), new StaticCall(new Name('Str'), new Identifier('camel'), [
                new Arg(new Variable('funcProperty'))
            ])))
        ];

        $propertyStudly = new ElseIf_(new FuncCall(new Name('property_exists'), [
            new Arg(new Variable('this')),
            new Arg(new StaticCall(new Name('Str'), new Identifier('studly'), [
                new Arg(new Variable('funcProperty'))
            ]))
        ]));

        $propertyStudly->stmts = [
            new Expression(new Assign(new Variable('property'), new StaticCall(new Name('Str'), new Identifier('studly'), [
                new Arg(new Variable('funcProperty'))
            ])))
        ];

        $property->elseifs = [
            $propertySnake,
            $propertyCamel,
            $propertyStudly
        ];

        $property->else = new Else_();

        if ($this->config->getAddFuncExtends()) {
            $property->else->stmts[] = $callParentCallFunc;
        }

        $property->else->stmts[] = $throwBadMethodCallException;

        $prefixIsSet = new If_(new Identical(
            left: new String_('set'),
            right: new Variable('prefix')
        ));

        $validateData = new Expression(new Assign(new Variable('data'), new FuncCall(
            name: new Name('validate_attribute'),
            args: [
                new Arg(new ClassConstFetch(new Name('static'), 'class')),
                new Arg(new Array_([
                    new ArrayItem(
                        value: new ArrayDimFetch(new Variable('arguments'), new LNumber(0)),
                        key: new Variable('property')
                    )
                ])),
                new Arg(new Array_([
                    new ArrayItem(new Variable('property'))
                ]))
            ]
        )));

        $assignValidateDataToClass = new Expression(new Assign(
            new PropertyFetch(new Variable('this'), new Variable('property')),
            new PropertyFetch(new Variable('data'), new Variable('property')),
        ));

        if ($this->config->getWritePropertyValidate()) {
            $setData = [
                $validateData,
                $assignValidateDataToClass
            ];
        } else {
            $setData = [
                new Expression(new Assign(
                    new PropertyFetch(new Variable('this'), new Variable('property')),
                    new ArrayDimFetch(new Variable('arguments'), new LNumber(0)),
                ))
            ];
        }

        $returnThis         = new Return_(new Variable('this'));
        $prefixIsSet->stmts = [
            ...$setData,
            $returnThis
        ];

        $returnProperty = new Return_(new PropertyFetch(new Variable('this'), new Variable('property')));

        if (!$force) {
            $stmts = [
                $prefix,
                $ifPrefixEqSetOrGet,
                $funcProperty,
                $property
            ];

            if ($addSetter && $addGetter) {
                $stmts[] = $prefixIsSet;
            }

            if ($addSetter && !$addGetter) {
                $stmts   = $stmts + $setData;
                $stmts[] = $returnProperty;
            }

            if ($addGetter) {
                $stmts[] = $returnProperty;
            }
        } else {
            $stmts = [
                $prefix,
                $ifPrefixEqSetOrGet,
                $funcProperty,
                $property,
                $prefixIsSet,
                $returnProperty
            ];
        }

        $callFunc->addStmts($stmts);
        $this->addStmt($callFunc->getNode());
    }

    public function addCreateFunc(?FieldHandler $handler = null): void
    {
        $createFunc = $this->builder->method('create');
        $createFunc->makePublic()->makeStatic();
        $createFunc->setReturnType('static');

        $createFunc->addParam($this->builder->param('data')->setType('array'));
        if ($this->config->getWritePropertyValidate()) {
            $createFunc->addParam($this->builder->param('fields')->setType('array')->setDefault(null));
        }

        $class = new Expression(new Assign(new Variable('class'), new New_(
            new Name('static')
        )));

        $validateData = new Return_(
            new FuncCall(new Name('validate_attribute'), [
                new Arg(new Variable('class')),
                new Arg(new Variable('data')),
                new Arg(new Variable('fields')),
            ])
        );

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

        if ($this->config->getWritePropertyValidate()) {
            $createFunc->addStmts([$class, $validateData]);
        } else {
            $createFunc->addStmts([$class, $foreach, $return]);
        }

        if (!is_null($handler)) {
            $dataParamInfo = [];
            $handler->each(function (FieldInfo $field) use (&$dataParamInfo) {
                $dataParamInfo[] = sprintf('    %s: %s', $field->name, $field->type);
            });
            $comment = "@param array{\n" . implode(",\n", $dataParamInfo) . "\n} \$data";
            if ($this->config->getWritePropertyValidate()) {
                $comment .= "\n@param array|null \$fields";
            }
            $comment .= "\n\n@return static";
            if (empty($this->config->getNamespacePrefix())) {
                $comment .= "\n\n@throws ReflectionException";
            } else {
                $comment .= "\n\n@throws \ReflectionException";
            }

            if ($this->config->getWritePropertyValidate()) {
                $comment .= "\n@throws \W7\Validate\Exception\ValidateException";
            }
            $comment .= "\n\n@noinspection PhpFullyQualifiedNameUsageInspection";
            $createFunc->setDocComment(Generator::makeComment($comment));
        }

        $this->addStmt($createFunc->getNode());
    }

    public function addToStringFunc(): void
    {
        $toStringFunc = $this->builder->method('__toString');
        $toStringFunc->makePublic();
        $toStringFunc->setReturnType('string');

        $return = new Return_(new FuncCall(new Name('json_encode'), [
            new Arg(new MethodCall(new Variable('this'), new Identifier('toArray'))),
            new Arg(new ConstFetch(new Name('JSON_UNESCAPED_UNICODE')))
        ]));

        $toStringFunc->addStmts([$return]);
        $this->addStmt($toStringFunc->getNode());
    }

    public function addBaseToArrayFunc(): void
    {
        $toArrayFunc = $this->builder->method('toArray');
        $toArrayFunc->makePublic();
        $toArrayFunc->setReturnType('array');
        $toArrayFunc->addStmt(new Return_(
            new \PhpParser\Node\Expr\Cast\Array_(new Variable('this'))
        ));
        $this->addStmt($toArrayFunc->getNode());
    }

    public function addToArrayFunc(array $fields): void
    {
        $method = $this->builder->method('toArray');
        $method->makePublic();
        $method->setReturnType('array');
        $stmts   = [];
        $stmts[] = new Expression(
            new Assign(
                new Variable('array'),
                new FuncCall(new Name('get_object_vars'), [
                    new Arg(new Variable('this'))
                ])
            )
        );
        $array = new Array_();
        foreach ($fields as $field) {
            $arrayItem      = new ArrayItem(new String_($field));
            $array->items[] = $arrayItem;
        }

        $stmts[] = new Return_(new FuncCall(new Name('array_intersect_key'), [
            new Arg(new Variable('array')),
            new Arg(new FuncCall(new Name('array_flip'), [
                new Arg($array)
            ]))
        ]));
        $method->addStmts($stmts);
        $this->addStmt($method->getNode());
    }
}
