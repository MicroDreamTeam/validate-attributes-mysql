<?php

namespace Itwmw\Validate\Attributes\Mysql;

use Itwmw\Validation\Support\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeMysqlDataCommand extends Command
{
    protected function configure()
    {
        $this->setName('make:mysql-data')->setDescription('根据数据库表生成数据类')
            ->addArgument('table', InputArgument::REQUIRED, '表名');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!class_exists("Itwmw\Table\Structure\Mysql\Mysql")) {
            $output->writeln("\033[0;31m\nError: Please reinstall the itwmw/validate-attributes-mysql package. To use the command-line functionality, you will need to install the dependencies in the require-dev section of this package.\033[0m");
            return 1;
        }
        $config = Config::instance();
        $table  = $input->getArgument('table');
        if (null !== $config->getTableArgHandler()) {
            $table = call_user_func($config->getTableArgHandler(), $table);
        }
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

        if (empty($config->getBaseNamespace())) {
            $fileNameSpace = $namespace ?: '';
        } else {
            $fileNameSpace = str_replace($config->getBaseNamespace(), '', $namespace ?: '');
        }

        $filePaths = [
            $config->getBasePath(),
            str_replace('\\', DIRECTORY_SEPARATOR, $fileNameSpace ?: ''),
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
