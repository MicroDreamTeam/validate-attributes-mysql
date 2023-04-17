<?php

namespace Itwmw\Validate\Attributes\Mysql\Providers;

use Illuminate\Support\ServiceProvider;
use Itwmw\Validate\Attributes\Mysql\Config;
use Itwmw\Validate\Attributes\Mysql\MakeMysqlDataCommand;
use W7\Validate\Support\Storage\ValidateConfig;

class ValidateProvider extends ServiceProvider
{
    public function register()
    {
        ValidateConfig::instance()->setRulesPath('Itwmw\\Validate\\Mysql\\Rules\\');

        $default  = config('database.default');
        $database = config('database.connections.' . $default);
        if ('mysql' !== strtolower($database['driver'])) {
            throw new \RuntimeException('Database types other than mysql are not supported at this time');
        }

        Config::instance()
            ->setMysqlConnection($database)
            ->setRemoveTablePrefix($database['prefix'] ?? '')
            ->setTypeMap([
                'json' => 'array',
                'set'  => 'array'
            ]);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands(MakeMysqlDataCommand::class);
        }
    }
}
