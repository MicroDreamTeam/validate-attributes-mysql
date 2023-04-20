## 微擎表单验证器Mysql注解验证
此扩展可根据Mysql表结构生成数据类，包含字段验证规则。

### 安装
```
composer require itwmw/validate-attributes-mysql
```

### 配置
在使用之前，你需要配置一些参数

#### 以下为必要配置：
- 配置Mysql规则集
> ** Laravel 框架无需配置，会自动获取配置信息**
```php
use W7\Validate\Support\Storage\ValidateConfig;

ValidateConfig::instance()->setRulesPath('Itwmw\\Validate\\Mysql\\Rules\\');
```
- 配置Mysql连接信息
> **Laravel框架无需配置，会自动获取配置信息**
```php
$database = [
    'url'         => '', // 数据库连接地址
    'host'        => '', // 数据库地址
    'port'        => '', // 数据库端口
    'username'    => '', // 数据库用户名
    'password'    => '', // 数据库密码
    'database'    => '', // 数据库名称
    'unix_socket' => '', // 数据库socket
    'charset'     => '', // 数据库编码,
    'prefix'      => '', // 数据库表前缀,
    'options'     => []  // PDO参数
]

Config::instance()->setMysqlConnection($database);
```
#### 以下为`Config`可选配置：
- `setBasePath` 配置生成数据类的基础路径 
- `setNamespacePrefix` 配置生成数据类的命名空间前缀 
- `setBaseNamespace` 配置生成数据类的基础命名空间，此命名空间应与`basePath`对应
- `setAddFunc` 是否添加基础方法 
- `setAddFuncExtends` 添加基础方法的方式, `true` 为通过继承的方式添加， `false` 为直接在当前类下添加方法
- `setAddComment` 是否添加注释，注释来源为 `Mysql` 中的注释
- `setSplitTableName` 是否分割表名，如 `user_info` 会处理为，命名空间： `$NamespacePrefix\User` , 类名： `Info`
- `setRemoveTablePrefix` 生成的数据类要移除的表前缀，如： `t_`
- `setTypeMap` 字段类型映射，可自定义字段类型映射，如： `['json' => 'array']`
- `setGenerateTrait` 是否生成 `Trait` ，启用后会生成 `trait` 文件,否则生成类文件
- `setUseConstruct` 是否使用构造函数，启用后会在类中添加构造函数
- `setConstructAllOptional` 构造函数是否全部可选，启用后，构造函数中的参数全部可选,仅当 `useConstruct` 为 `true` 时有效
- `setTableArgHandler` 表名处理函数，可自定义表名处理方式，如：`function ($tableName) { return 't_' . $tableName; }`
- `setPropertyScope` 属性作用域，可自定义属性作用域，如：`private`,默认为 `public`
- `setPropertyReadOnly` 属性是否只读，启用后，属性类型会添加 `readonly` 关键字
- `setGenerateSetter` 是否生成修改器，启用后，会生成修改器，如：`setName`，`setAge`
- `setGenerateGetter` 是否生成访问器，启用后，会生成访问器，如：`getName`，`getAge`
- `setWritePropertyValidate` 是否启用写入数据时验证数据，启用后，当使用修改器，会自动验证数据

以下为`Laravel` Model 文件转表名的示例：
```php
Config::instance()->setTableArgHandler(function ($table) {
    if (!file_exists($table)) {
        return $table;
    }

    // ClassMapGenerator 来自 composer/class-map-generator 包
    $class = ClassMapGenerator::createMap($table);
    if (empty($class)) {
        return $table;
    }
    $className = array_keys($class)[0];
    if (!is_subclass_of($className, Model::class)) {
        throw new \RuntimeException("不支持非Model类生成Data类: {$className}");
    }
    $model = new $className();
    return $model->getTable();
})
```
### 使用
#### 生成数据类
`Laravel` 框架，可以使用`php artisan make:mysql-data table_name` 命令生成数据类
#### 使用数据类
如有以下表结构：
```sql
CREATE TABLE `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '名字',
  `age` tinyint unsigned DEFAULT '18' COMMENT '年龄',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';
```
所有可选配置为默认的情况下会生成以下数据类：
```php
<?php

namespace {
    use Itwmw\Validate\Attributes\Mysql\Rules\MysqlInt;
    use Itwmw\Validate\Attributes\Rules\Numeric;
    use Itwmw\Validate\Attributes\Rules\Required;
    use Itwmw\Validate\Attributes\Mysql\Rules\MysqlVarchar;
    use Itwmw\Validate\Attributes\Preprocessor;
    use W7\Validate\Support\Processor\ProcessorExecCond;
    use Itwmw\Validate\Attributes\Mysql\Rules\MysqlTinyint;
    use Itwmw\Validate\Attributes\Rules\Nullable;

    class User
    {
        #[MysqlInt(false)]
        #[Numeric]
        #[Required]
        public int $id;

        #[MysqlVarchar(255)]
        #[Required]
        #[Preprocessor('', ProcessorExecCond::WHEN_EMPTY)]
        public string $name = '';

        #[MysqlTinyint(true)]
        #[Numeric]
        #[Nullable]
        #[Preprocessor(18, ProcessorExecCond::WHEN_EMPTY)]
        public ?int $age = 18;
    }
}
```
使用示例：
```php
$data = validate_attribute(User::class, [
    'id'   => 1,
    'name' => '虞灪',
    'age'  => 17
]);

print_r($data);
// 输出:
//User Object
//(
//    [id] => 1
//    [name] => 虞灪
//    [age] => 17
//)
```
如果只需要验证其中的几个字段，可以指定字段：
```php
$data = validate_attribute(User::class, [
    'name' => '虞灪',
    'age'  => 17
], ['name', 'age']);

print_r($data);
// 输出
//User Object
//(
//    [name] => 虞灪
//    [age] => 17
//)
```
> 验证完毕后，会实例化一个该类，并将值写入对应的属性中，写入不受属性的访问权限影响

实例化类后进行验证：
```php
$user = new User();
$user->id   = 1;
$user->name = '虞灪';
$user->age  = 18;
$data = validate_attribute($user);
print_r($data);
// 输出：
//User Object
//(
//    [id] => 1
//    [name] => 虞灪
//    [age] => 18
//)
```
也可以在生成类验证数据时，使用`setUseConstruct`方法，启用后会在类中添加构造函数，构造函数如下：
```php
/**
 * @param int $id
 * @param string $name 名字
 * @param null|int $age 年龄
 */
public function __construct(int $id, string $name = '', ?int $age = 18)
{
    $this->id   = $id;
    $this->name = $name;
    $this->age  = $age;
}
```
> 为了代码更容易维护，这里没有使用`php8`的新特征 [Constructor property promotion](https://www.php.net/manual/en/language.oop5.decon.php#language.oop5.decon.constructor.promotion)

使用示例如下：
```php
$user = new User(1, 'test', 18);
$data = validate_attribute($user);
print_r($data);
// 输出：
//User Object
//(
//    [id] => 1
//    [name] => test
//    [age] => 18
//)
```
当然，你也可以将这两种使用方式结合起来：
```php
$user = new User(1, 'test', 18);
$data = validate_attribute($user, [
    'name' => '虞灪',
    'age'  => 20
]);
print_r($data)
// 输出：
//User Object
//(
//    [id] => 1
//    [name] => 虞灪
//    [age] => 20
//)
```
> 这个时候，`input`中的参数会覆盖`构造函数`中的参数
