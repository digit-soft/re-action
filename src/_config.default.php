<?php

use Reaction\DI\Definition;
use Reaction\DI\Instance;

/** Default config */
return [
    //Application config
    'app' => [
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
            '@reaction' => dirname(__FILE__),
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
            ],
            'logger' => 'stdioLogger',
            'formatter' => 'formatterDefault',
            'security' => 'securityDefault',
            'sessionHandler' => 'sessionHandlerDefault',
            'fs' => 'fileSystemDefault',
        ],
    ],
    //Request config
    'request' => [
        'cookieValidationKey' => 'dmyyHbvzRjd7RjXJ',
        'components' => [
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
            'i18n' => [
                'class' => 'Reaction\I18n\Request\I18nGroup',
                'components' => [
                    'formatter' => 'Reaction\I18n\Request\Formatter',
                ],
            ],
            //Response builder config
            'response' => [
                'class' => 'Reaction\Web\ResponseBuilderInterface',
            ],
            'session' => [
                'class' => 'Reaction\Web\Sessions\Session',
            ],
            'assetManager' => [
                'class' => 'Reaction\Web\AssetManager',
            ],
            'view' => [
                'class' => 'Reaction\Web\View',
            ],
            'urlManager' => [
                'class' => 'Reaction\Web\UrlManager',
            ],
        ],
    ],
    //DI definitions
    'container' => [
        'definitions' => [
            'Reaction\Web\AppRequestInterface' => 'Reaction\Web\Request',
            'Reaction\Web\RequestServiceInterface' => 'Reaction\Web\RequestService',
            'Reaction\Web\ResponseBuilderInterface' => 'Reaction\Web\ResponseBuilder',
            'Reaction\Web\Sessions\SessionHandlerInterface' => [
                'class' => 'Reaction\Web\Sessions\CachedSessionHandler',
                'loop' => Instance::of('React\EventLoop\LoopInterface'),
            ],
            'Reaction\Routes\RouteInterface' => 'Reaction\Routes\Route',
            'Reaction\Routes\UrlManagerInterface' => 'Reaction\Routes\UrlManager',
        ],
        'singletons' => [
            //React event loop
            'React\EventLoop\LoopInterface' => function() { return \React\EventLoop\Factory::create(); },
            'React\Filesystem\FilesystemInterface' => function (\Reaction\DI\Container $di) {
                $loop = $di->get('React\EventLoop\LoopInterface');
                return \React\Filesystem\Filesystem::create($loop);
            },
            //React socket server
            'React\Socket\ServerInterface' => [
                ['class' => \React\Socket\Server::class],
                ['0.0.0.0:4000', Instance::of(\React\EventLoop\LoopInterface::class)],
            ],
            //React http server
            \React\Http\Server::class => [
                'class' => \React\Http\Server::class,
            ],
            //Application
            'Reaction\BaseApplicationInterface' => \Reaction\BaseApplication::class,
            //'Reaction\BaseApplication' => \Reaction\BaseApplication::class,
            //Router
            'Reaction\Routes\RouterInterface' => \Reaction\Routes\Router::class,
            //'Reaction\Routes\Router' => \DI\create()->scope(\DI\Scope::SINGLETON),
            //Stdout writable stream
            'stdoutWriteStream' => Definition::of(\React\Stream\WritableResourceStream::class)
                ->withParams([STDOUT, Instance::of(\React\EventLoop\LoopInterface::class)]),
            //Stdio logger
            'stdioLogger' => Definition::of(\Reaction\Base\Logger\StdioLogger::class)
                ->withParams([Instance::of('stdoutWriteStream'), Instance::of(\React\EventLoop\LoopInterface::class)])
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
            //Security component
            'securityDefault' => [
                'class' => 'Reaction\Base\Security',
            ],
            //Default array cache
            'arrayCacheDefault' => [
                'class' => 'Reaction\Cache\ArrayCache'
            ],
            //Session handler
            'sessionHandlerDefault' => [
                'class' => 'Reaction\Web\Sessions\SessionHandlerInterface',
            ],
            //Session handler
            'fileSystemDefault' => [
                'class' => 'React\Filesystem\FilesystemInterface',
            ],
        ],
    ],

];