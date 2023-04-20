<?php

namespace Itwmw\Validate\Attributes\Mysql;

use PhpParser\Builder\TraitUse;
use PhpParser\BuilderFactory;

/**
 * @internal
 */
class GenerateBaseClass
{
    public function __construct(protected Config $config, protected Generator $generator)
    {
    }

    public function getFilePath(string $class_name): string
    {
        if (!empty($namespace = $this->config->getNamespacePrefix())) {
            if (!empty($this->config->getBaseNamespace())) {
                $namespace = str_replace($this->config->getBaseNamespace(), '', $namespace);
            }
            return $this->config->getBasePath()
                . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $namespace)
                . DIRECTORY_SEPARATOR
                . "/$class_name.php";
        } else {
            return $this->config->getBasePath() . "/$class_name.php";
        }
    }

    public function getClassUid(): string
    {
        $configInfo = sprintf(
            '%d',
            $this->config->getWritePropertyValidate(),
        );
        return str_replace(1, 'O', $configInfo);
    }

    public function checkClassNeedUpdate(): bool
    {
        $configInfo = $this->getClassUid();
        $class      = $this->config->getNamespacePrefix() . '\\' . 'BaseDataTrait';
        return !property_exists($class, '__' . $configInfo . '__');
    }

    public function generateTrait(): void
    {
        $configInfo = $this->getClassUid();
        $path       = $this->getFilePath('BaseDataTrait');

        $builder   = new BuilderFactory();
        $namespace = $builder->namespace($this->config->getNamespacePrefix());
        $class     = $builder->trait('BaseDataTrait');
        $class->addStmt($builder->property('__' . $configInfo . '__')->setType('int')->makePrivate()->getNode());
        $this->generator->addToStringFunc($class, $builder);
        $this->generator->addBaseToArrayFunc($class, $builder);
        $this->generator->addCallFunc($class, $builder, true);
        $namespace->addStmt($class);
        $ast = $namespace->getNode();
        $php = $this->generator->getPhpCode([$ast]);
        $php = $this->generator->fixPhpCode($php);
        file_put_contents($path, $php);
    }

    public function generateClass(): void
    {
        $path      = $this->getFilePath('BaseData');
        $builder   = new BuilderFactory();
        $namespace = $builder->namespace($this->config->getNamespacePrefix());
        if (!empty($this->config->getNamespacePrefix())) {
            $namespace->addStmt($builder->use(\Stringable::class)->getNode());
        }

        $class = $builder->class('BaseData')->implement(\Stringable::class);
        $class->addStmt(new TraitUse('BaseDataTrait'));
        $namespace->addStmt($class);
        $ast = $namespace->getNode();
        $php = $this->generator->getPhpCode([$ast]);
        $php = $this->generator->fixPhpCode($php);
        file_put_contents($path, $php);
    }
}
