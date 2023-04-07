<?php

namespace Itwmw\Validate\Attributes\Mysql;

use Itwmw\Validation\Support\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MakeMysqlDataCommand extends Command
{
    protected function configure()
    {
        $this->setName('make:mysql-data')->setDescription('根据数据库表生成数据类')
            ->addArgument('table', InputArgument::REQUIRED, '表名')
            ->addOption('namespace', 'a', InputOption::VALUE_OPTIONAL, '命名空间前缀', Config::instance()->getNamespacePrefix())
            ->addOption('add-func', 'f', InputOption::VALUE_OPTIONAL, '是否添加函数', Config::instance()->getAddFunc())
            ->addOption('add-func-extends', 'e', InputOption::VALUE_OPTIONAL, '是否通过继承的方式添加函数', Config::instance()->getAddFuncExtends())
            ->addOption('split-table-name', 's', InputOption::VALUE_OPTIONAL, '是否拆分表名', Config::instance()->getSplitTableName())
            ->addOption('base-path', 'b', InputOption::VALUE_OPTIONAL, '基础路径', Config::instance()->getBasePath())
            ->addOption('remove-table-prefix', 'r', InputOption::VALUE_OPTIONAL, '要删除的表前缀', Config::instance()->getRemoveTablePrefix())
            ->addOption('add-comment', 'c', InputOption::VALUE_OPTIONAL, '是否添加注释', Config::instance()->getAddComment());
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = new Config();
        $config->setNamespacePrefix($input->getOption('namespace'))
            ->setAddFunc($input->getOption('add-func'))
            ->setAddFuncExtends($input->getOption('add-func-extends'))
            ->setSplitTableName($input->getOption('split-table-name'))
            ->setBasePath($input->getOption('base-path'))
            ->setRemoveTablePrefix($input->getOption('remove-table-prefix'))
            ->setAddComment($input->getOption('add-comment'))
            ->setTypeMap(Config::instance()->getTypeMap());

        $table        = $input->getArgument('table');
        $generator    = new Generator($config);
        $newTableName = str_replace($config->getRemoveTablePrefix(), '', $table);
        $namespace    = $config->getNamespacePrefix();

        if ($config->getSplitTableName()) {
            $fileNameSpace = explode('_', $newTableName);
            $newTableName  = array_pop($fileNameSpace);
            $fileNameSpace = array_map(fn ($item) => ucfirst($item), $fileNameSpace);
            $fileNameSpace = implode('\\', $fileNameSpace);

            if (!empty($fileNameSpace)) {
                if (empty($config->getNamespacePrefix())) {
                    $namespace = $fileNameSpace;
                } else {
                    $namespace = $config->getNamespacePrefix() . '\\' . $fileNameSpace;
                }
            }
            $newTableName = ucfirst($newTableName);
        } else {
            $newTableName = Str::studly($newTableName);
        }

        $filePaths = [
            $config->getBasePath(),
            str_replace('\\', DIRECTORY_SEPARATOR, $namespace ?: ''),
            $newTableName
        ];
        $filePaths = array_filter($filePaths);
        $filePath  = implode(DIRECTORY_SEPARATOR, $filePaths) . '.php';
        $dir       = dirname($filePath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            $phpCode = $generator->makeDataClass($table, $namespace, $newTableName);
        } catch (\RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return 1;
        }

        file_put_contents($filePath, $phpCode);
        $output->writeln("<info>$table generate success</info>");
        return 0;
    }
}
