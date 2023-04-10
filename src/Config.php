<?php

namespace Itwmw\Validate\Attributes\Mysql;

use Closure;

class Config
{
    protected ?string $namespacePrefix = null;

    protected bool $addFunc = true;

    protected bool $addFuncExtends = false;

    protected bool $splitTableName = false;

    protected bool $addComment = true;

    protected string $basePath = __DIR__;

    protected string $removeTablePrefix = '';

    protected array $typeMap = [];

    protected array $mysqlConnection = [];

    protected ?Closure $tableArgHandler = null;

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

    public function getNamespacePrefix(): ?string
    {
        return $this->namespacePrefix;
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
}
