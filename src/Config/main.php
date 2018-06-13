<?php

use Reaction\DI\Definition;
use Reaction\DI\Instance;

/** Default config */
return [
    //Static application config
    'appStatic' => [
        'debug' => true,
        'charset' => 'utf-8',
        'hostname' => '127.0.0.1',
        'port' => 4000,
        //Initial app aliases
        'aliases' => [
            '@root' => getcwd(),
            '@app' => getcwd(),
            '@runtime' => '@root/Runtime',
            '@views' => '@root/Views',
            '@reaction' => dirname(__DIR__),
            '@web' => '',
            '@webroot' => '@root/Web',
            '@vendor' => '@root/vendor',
            '@bower' => '@vendor/bower-asset',
        ],
        //Components
        'components' => [
            'router' => 'Reaction\Routes\RouterInterface',
            'urlManager' => [
                'class' => 'Reaction\Routes\UrlManagerInterface',
                'baseUrl' => '',
                'hostInfo' => 'http://vitrager.loc',
                'dependsOn' => ['i18n'],
            ],
            'logger' => 'stdioLogger',
            'formatter' => 'formatterDefault',
            'security' => 'Reaction\Base\Security',
            //Session handler
            'sessionHandler' => [
                'class' => 'Reaction\Web\Sessions\SessionHandlerInterface',
                'archive' => 'Reaction\Web\Sessions\SessionArchiveInterface',
            ],
            'fs' => 'fileSystemDefault',
            'db' => [
                'class' => 'Reaction\Db\Pgsql\Database',
                'username' => 'reaction',
                'password' => 'evmCXA2g6T5uGRMF',
                'host' => 'db',
                'port' => '5432',
                'database' => 'reaction',
            ],
        ],
    ],
    //Request application config
    'appRequest' => [
        'class' => 'Reaction\RequestApplicationInterface',
        'components' => [
            'errorHandler' => 'Reaction\Base\ErrorHandlerInterface',
            'reqHelper' => [
                'class' => 'Reaction\Web\RequestHelper',
                'cookieValidationKey' => 'dmyyHbvzRjd7RjXJ',
            ],
            'helpers' => [
                'class' => 'Reaction\Helpers\Request\HelpersGroup',
                'components' => [
                    'inflector' => 'Reaction\Helpers\Request\Inflector',
                    'string' => 'Reaction\Helpers\Request\StringHelper',
                    'array' => 'Reaction\Helpers\Request\ArrayHelper',
                    'json' => 'Reaction\Helpers\Request\JsonHelper',
                    'ip' => 'Reaction\Helpers\Request\IpHelper',
                    'html' => 'Reaction\Helpers\Request\HtmlHelper',
                    'htmlPurifier' => 'Reaction\Helpers\Request\HtmlPurifier',
                    'file' => 'Reaction\Helpers\Request\FileHelper',
                ],
            ],
            //Response builder config
            'response' => [
                'class' => 'Reaction\Web\ResponseBuilderInterface',
            ],
            'urlManager' => [
                'class' => 'Reaction\Web\UrlManager',
            ],
            'view' => [
                'class' => 'Reaction\Web\View',
            ],
            'assetManager' => [
                'class' => 'Reaction\Web\AssetManager',
            ],
            'i18n' => [
                'class' => 'Reaction\I18n\Request\I18nGroup',
                'components' => [
                    'formatter' => 'Reaction\I18n\Request\Formatter',
                ],
            ],
            'session' => [
                'class' => 'Reaction\Web\Sessions\Session',
            ],
        ],
    ],
    //DI definitions
    'container' => [
        'definitions' => [
            'React\Socket\ServerInterface' => 'React\Socket\Server',
            'Reaction\Web\ResponseBuilderInterface' => 'Reaction\Web\ResponseBuilder',
            'Reaction\Web\Sessions\SessionHandlerInterface' => [
                'class' => 'Reaction\Web\Sessions\CachedSessionHandler',
                'loop' => Instance::of('React\EventLoop\LoopInterface'),
            ],
            'Reaction\Web\Sessions\SessionArchiveInterface' => 'Reaction\Web\Sessions\SessionArchiveInFiles',
            'Reaction\Routes\RouteInterface' => 'Reaction\Routes\Route',
            'Reaction\Routes\UrlManagerInterface' => 'Reaction\Routes\UrlManager',
            'Reaction\Web\UserInterface' => 'Reaction\Web\User',
            'Reaction\RequestApplicationInterface' => 'Reaction\RequestApplication',
        ],
        'singletons' => [
            //React event loop
            'React\EventLoop\LoopInterface' => function() { return \React\EventLoop\Factory::create(); },
            'React\Filesystem\FilesystemInterface' => function(\Reaction\DI\Container $di) {
                /** @var React\EventLoop\LoopInterface $loop */
                $loop = $di->get('React\EventLoop\LoopInterface');
                return \React\Filesystem\Filesystem::createFromAdapter(new \React\Filesystem\Eio\Adapter($loop, []));
            },
            //Router
            'Reaction\Routes\RouterInterface' => 'Reaction\Routes\Router',
            //StdOut writable stream
            'stdoutWriteStream' => Definition::of('React\Stream\WritableResourceStream')
                ->withParams([STDOUT, Instance::of('React\EventLoop\LoopInterface')]),
            //StdIn readable stream
            'stdinReadStream' => Definition::of('React\Stream\ReadableResourceStream')
                ->withParams([STDIN, Instance::of('React\EventLoop\LoopInterface')]),
            //Stdio logger
            'stdioLogger' => Definition::of('Reaction\Base\Logger\StdioLogger')
                ->withParams([Instance::of('stdoutWriteStream'), Instance::of('React\EventLoop\LoopInterface')])
                ->withConfig(['withLineNum' => true]),
            //I18n Formatter
            'formatterDefault' => Definition::of(\Reaction\I18n\Formatter::class)->withConfig([
                'locale' => 'uk-UA',
                'dateFormat' => 'php:d F Y',
                'datetimeFormat' => 'php:d F Y - H:i:s',
                'timeFormat' => 'php:H:i:s',
                'timeZone' => 'Europe/Kiev',
                'decimalSeparator' => '.',
                'thousandSeparator' => ' ',
                'currencyCode' => 'UAH',
            ]),
            //Default array cache
            'arrayCacheDefault' => [
                'class' => 'Reaction\Cache\ArrayExpiringCache'
            ],
        ],
    ],

];