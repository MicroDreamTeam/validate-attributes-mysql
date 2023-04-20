<?php

namespace Itwmw\Validate\Attributes\Mysql;

use Closure;
use JetBrains\PhpStorm\ExpectedValues;

class Config
{
    public const PROPERTY_SCOPE_PUBLIC = 'public';

    public const PROPERTY_SCOPE_PROTECTED = 'protected';

    public const PROPERTY_SCOPE_PRIVATE = 'private';

    protected ?string $namespacePrefix = null;

    protected ?string $baseNamespace = null;

    protected bool $addFunc = true;

    protected bool $addFuncExtends = false;

    protected bool $splitTableName = false;

    protected bool $addComment = true;

    protected string $basePath = __DIR__;

    protected string $removeTablePrefix = '';

    protected array $typeMap = [];

    protected array $mysqlConnection = [];

    protected bool $generateTrait = false;

    protected ?Closure $tableArgHandler = null;

    protected bool $useConstruct = false;

    protected bool $constructAllOptional = false;

    protected string $propertyScope = 'public';

    protected bool $propertyReadOnly = false;

    protected bool $generateGetter = false;

    protected bool $generateSetter = false;

    protected bool $writePropertyValidate = false;

    protected static Config $instance;

    public static function instance(): Config
    {
        if (empty(self::$instance)) {
            self::$instance = new Config();
        }

        return self::$instance;
    }

    /**
     * 设置命名空间前缀
     *
     * @param string|null $namespacePrefix
     * @return $this
     */
    public function setNamespacePrefix(?string $namespacePrefix): Config
    {
        if (!is_null($namespacePrefix)) {
            if (str_ends_with($namespacePrefix, '\\')) {
                $namespacePrefix = substr($namespacePrefix, 0, -1);
            }

            if (str_starts_with($namespacePrefix, '\\')) {
                $namespacePrefix = substr($namespacePrefix, 1);
            }
        }
        $this->namespacePrefix = $namespacePrefix;
        return $this;
    }

    /**
     * 设置基础命名空间
     *
     * @param string|null $baseNamespace
     * @return $this
     */
    public function setBaseNamespace(?string $baseNamespace): Config
    {
        if (!is_null($baseNamespace)) {
            if (str_ends_with($baseNamespace, '\\')) {
                $baseNamespace = substr($baseNamespace, 0, -1);
            }

            if (str_starts_with($baseNamespace, '\\')) {
                $baseNamespace = substr($baseNamespace, 1);
            }
        }
        $this->baseNamespace = $baseNamespace;
        return $this;
    }

    /**
     * 设置是否添加函数
     *
     * @param bool $addFunc
     * @return $this
     */
    public function setAddFunc(bool $addFunc): Config
    {
        $this->addFunc = $addFunc;
        return $this;
    }

    /**
     * 设置是否通过继承的方式添加函数
     *
     * @param bool $addFuncExtends 如果为true,则通过继承的方式添加函数,否则直接在类中添加函数
     * @return $this
     */
    public function setAddFuncExtends(bool $addFuncExtends): Config
    {
        $this->addFuncExtends = $addFuncExtends;
        return $this;
    }

    /**
     * 设置是否拆分表名来生成文件
     *
     * @param bool $splitTableName
     * @return $this
     */
    public function setSplitTableName(bool $splitTableName): Config
    {
        $this->splitTableName = $splitTableName;
        return $this;
    }

    /**
     * 设置基础路径
     *
     * @param string $basePath
     * @return $this
     */
    public function setBasePath(string $basePath): Config
    {
        if (str_ends_with($basePath, DIRECTORY_SEPARATOR)) {
            $basePath = substr($basePath, 0, -1);
        }
        $this->basePath = $basePath;
        return $this;
    }

    /**
     * 设置类型映射
     *
     * @param array $typeMap
     * @return $this
     */
    public function setTypeMap(array $typeMap): Config
    {
        $this->typeMap = $typeMap;
        return $this;
    }

    /**
     * 设置移除表前缀
     *
     * @param string $removeTablePrefix
     * @return $this
     */
    public function setRemoveTablePrefix(string $removeTablePrefix): Config
    {
        $this->removeTablePrefix = $removeTablePrefix;
        return $this;
    }

    /**
     * 设置是否添加注释
     *
     * @param bool $addComment
     * @return $this
     */
    public function setAddComment(bool $addComment): Config
    {
        $this->addComment = $addComment;
        return $this;
    }

    /**
     * 设置表参数处理器
     *
     * @param Closure $tableArgHandler
     * @return $this
     */
    public function setTableArgHandler(Closure $tableArgHandler): Config
    {
        $this->tableArgHandler = $tableArgHandler;
        return $this;
    }

    /**
     * 设置mysql连接参数
     *
     * @param array{
     *     url: string,
     *     username: string,
     *     password: string,
     *     unix_socket: string,
     *     host: string,
     *     port: int,
     *     database: string,
     *     charset: string,
     *     prefix: string,
     *     options: array,
     * } $mysqlConnection
     * @return $this
     */
    public function setMysqlConnection(array $mysqlConnection): Config
    {
        $this->mysqlConnection = $mysqlConnection;
        return $this;
    }

    /**
     * 设置是否生成trait,启用后会生成trait文件,否则生成类文件
     *
     * 当使用trait时,addFuncExtends设置无效
     *
     * @param bool $generateTrait
     * @return $this
     */
    public function setGenerateTrait(bool $generateTrait): Config
    {
        $this->generateTrait = $generateTrait;
        return $this;
    }

    /**
     * 设置是否使用构造函数
     *
     * @param bool $useConstruct
     * @return $this
     */
    public function setUseConstruct(bool $useConstruct): Config
    {
        $this->useConstruct = $useConstruct;
        return $this;
    }

    /**
     * 设置构造函数参数是否全部可选
     *
     * @param bool $constructAllOptional
     * @return $this
     */
    public function setConstructAllOptional(bool $constructAllOptional): Config
    {
        $this->constructAllOptional = $constructAllOptional;
        return $this;
    }

    /**
     * 设置属性作用域
     *
     * @param string $propertyScope
     * @return $this
     */
    public function setPropertyScope(
        #[ExpectedValues(valuesFromClass: Config::class)]
        string $propertyScope
    ): Config {
        $this->propertyScope = $propertyScope;
        return $this;
    }

    /**
     * 设置属性是否只读
     *
     * @param bool $propertyReadOnly
     * @return $this
     */
    public function setPropertyReadOnly(bool $propertyReadOnly): Config
    {
        $this->propertyReadOnly = $propertyReadOnly;
        return $this;
    }

    /**
     * 设置是否生成getter
     *
     * @param bool $generateGetter
     * @return $this
     */
    public function setGenerateGetter(bool $generateGetter): Config
    {
        $this->generateGetter = $generateGetter;
        return $this;
    }

    /**
     * 设置是否生成setter
     *
     * @param bool $generateSetter
     * @return $this
     */
    public function setGenerateSetter(bool $generateSetter): Config
    {
        $this->generateSetter = $generateSetter;
        return $this;
    }

    /**
     * 设置写入属性时是否进行验证
     *
     * @param bool $writePropertyValidate
     * @return $this
     */
    public function setWritePropertyValidate(bool $writePropertyValidate): Config
    {
        $this->writePropertyValidate = $writePropertyValidate;
        return $this;
    }

    public function getNamespacePrefix(): ?string
    {
        return $this->namespacePrefix;
    }

    public function getBaseNamespace(): ?string
    {
        return $this->baseNamespace;
    }

    public function getAddFunc(): bool
    {
        return $this->addFunc;
    }

    public function getAddFuncExtends(): bool
    {
        return $this->addFuncExtends;
    }

    public function getSplitTableName(): bool
    {
        return $this->splitTableName;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getTypeMap(): array
    {
        return $this->typeMap;
    }

    public function getRemoveTablePrefix(): string
    {
        return $this->removeTablePrefix;
    }

    public function getAddComment(): bool
    {
        return $this->addComment;
    }

    public function getTableArgHandler(): ?Closure
    {
        return $this->tableArgHandler;
    }

    public function getMysqlConnection(): array
    {
        return $this->mysqlConnection;
    }

    public function getGenerateTrait(): bool
    {
        return $this->generateTrait;
    }

    public function getUseConstruct(): bool
    {
        return $this->useConstruct;
    }

    public function getConstructAllOptional(): bool
    {
        return $this->constructAllOptional;
    }

    public function getPropertyScope(): string
    {
        return $this->propertyScope;
    }

    public function getPropertyReadOnly(): bool
    {
        return $this->propertyReadOnly;
    }

    public function getGenerateGetter(): bool
    {
        return $this->generateGetter;
    }

    public function getGenerateSetter(): bool
    {
        return $this->generateSetter;
    }

    public function getWritePropertyValidate(): bool
    {
        return $this->writePropertyValidate;
    }
}
