<?php

namespace Itwmw\Validate\Attributes\Mysql;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Config;
use PhpCsFixer\Console\ConfigurationResolver;
use PhpCsFixer\FileReader;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\ToolInfo;
use SplFileInfo;

class Fixer
{
    private array $defaultFixers = [];

    public function __construct()
    {
        $this->defaultFixers = [
            '@PSR2'                                      => true,
            'single_quote'                               => true, // 简单字符串应该使用单引号代替双引号；
            'no_unused_imports'                          => true, // 删除没用到的use
            'no_singleline_whitespace_before_semicolons' => true, // 禁止只有单行空格和分号的写法；
            'no_empty_statement'                         => true, // 多余的分号
            'no_extra_blank_lines'                       => true, // 多余空白行
            'no_blank_lines_after_phpdoc'                => true, // 注释和代码中间不能有空行
            'no_empty_phpdoc'                            => true, // 禁止空注释
            'phpdoc_indent'                              => true, // 注释和代码的缩进相同
            'no_blank_lines_after_class_opening'         => true, // 类开始标签后不应该有空白行；
            'include'                                    => true, // include 和文件路径之间需要有一个空格，文件路径不需要用括号括起来；
            'no_trailing_comma_in_list_call'             => true, // 删除 list 语句中多余的逗号；
            'no_leading_namespace_whitespace'            => true, // 命名空间前面不应该有空格；
            'standardize_not_equals'                     => true, // 使用 <> 代替 !=；
            'blank_line_after_opening_tag'               => true, // PHP开始标记后换行
            'indentation_type'                           => true, // 代码必须使用配置的缩进类型。
            'concat_space'                               => [     // 连接应根据配置进行间隔。
                'spacing' => 'one',
            ],
            'space_after_semicolon' => [ // 修复分号后面的空白。
                'remove_in_empty_for_expressions' => true,
            ],
            'binary_operator_spaces'          => ['default' => 'align_single_space_minimal'], // 等号对齐、数字箭头符号对齐
            'whitespace_after_comma_in_array' => true, // 在数组声明中，每个逗号后面必须有空格。
            'array_syntax'                    => ['syntax' => 'short'], // PHP数组应该使用配置的语法声明。
            'ternary_operator_spaces'         => true, // 标准化三元运算符周围的空格。
            'yoda_style'                      => true, // 根据配置，以Yoda风格(true)、非Yoda风格(['equal' => false， ' same ' => false， 'less_and_greater' => false])写条件或忽略这些条件(null)。
            'normalize_index_brace'           => true, // 数组索引应始终使用方括号写入。
            'short_scalar_cast'               => true, // 类型转换(boolean)和(integer)应该写为(bool)和(int)， (double)和(real)写为(float)， (binary)写为(string)。
            'function_typehint_space'         => true, // 确保函数的参数和它的类型提示之间只有一个空格。
            'function_declaration'            => true, // 在函数声明中应该适当地放置空格。
            'return_type_declaration'         => true, // 在返回类型声明和支持的enum类型中调整冒号周围的空格。
            'class_attributes_separation'     => [
                'elements' => [
                    'const'        => 'one',
                    'method'       => 'one',
                    'property'     => 'one',
                    'trait_import' => 'none',
                    'case'         => 'none'
                ]
            ]
        ];
    }

    private function forceApplyFixer(ConfigurationResolver $configurationResolver, Config $config): void
    {
        $ref       = new \ReflectionClass($configurationResolver);
        $refConfig = $ref->getProperty('config');
        $refConfig->setValue($configurationResolver, $config);
    }

    /**
     * 格式化文件
     *
     * @param SplFileInfo $file
     * @param bool        $force
     * @return string
     *
     * @noinspection PhpInternalEntityUsedInspection
     */
    public function fix(SplFileInfo $file, bool $force = false): string
    {
        $config = new Config();
        $config->setRules($this->defaultFixers)
            ->setIndent("\t")
            ->setUsingCache(false);

        $resolver = new ConfigurationResolver($config, [
            'show-progress' => false
        ], getcwd(), new ToolInfo());

        if ($force) {
            $this->forceApplyFixer($resolver, $config);
        }

        $old           = FileReader::createSingleton()->read($file->getRealPath());
        $tokens        = Tokens::fromCode($old);
        $new           = $old;
        $appliedFixers = [];
        $fixers        = $resolver->getFixers();

        foreach ($fixers as $fixer) {
            if (
                !$fixer instanceof AbstractFixer
                && (!$fixer->supports($file) || !$fixer->isCandidate($tokens))
            ) {
                continue;
            }

            $fixer->fix($file, $tokens);

            if ($tokens->isChanged()) {
                $tokens->clearEmptyTokens();
                $tokens->clearChanged();
                $appliedFixers[] = $fixer->getName();
            }

            if (!empty($appliedFixers)) {
                $new = $tokens->generateCode();
            }
        }

        return $new;
    }
}
