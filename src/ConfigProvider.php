<?php

declare(strict_types=1);

/**
 * #logic 做事不讲究逻辑，再努力也只是重复犯错
 * ## 何为相思：不删不聊不打扰，可否具体点：曾爱过。何为遗憾：你来我往皆过客，可否具体点：再无你。
 * ## 摔倒一次可以怪路不平鞋不正，在同一个地方摔倒两次，只能怪自己和自己和解，无不是一个不错的选择。
 * @version 1.0.0
 * @author @小小只^v^ <littlezov@qq.com>  littlezov@qq.com
 * @link     https://github.com/littlezo
 * @document https://github.com/littlezo/wiki
 * @license  https://github.com/littlezo/MozillaPublicLicense/blob/main/LICENSE
 *
 */

namespace Mine\Devtool;

use ArrayObject;
use Error;
use Event;
use Exception;
use Hyperf\Database\Commands\CommandCollector;
use Mine\Devtool\Command\FixCommand;

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));
class ConfigProvider
{
    /**
     * @return array{annotations: array{scan: array{paths: string[]}}, littler: array{package: array{php: string, littler/framework: string}, package-dev: ArrayObject<never, never>}, devtool: array<string, array<string, array{namespace: class-string<Exception>|class-string<Event>|string, inheritance?: string}|array{namespace: string}|array{namespace: string, inheritance: string, suffix: string}|array{namespace: string, inheritance: string}|array{namespace: string, inheritance: string, implements: string}|array{namespace: string, suffix: class-string<Error>|string}>>, commands: mixed[], publish: array<int, array{id: string, description: string, source: string, destination: string}>}
     */
    public function __invoke()
    {
        return [
            'annotations' => [
                'scan' => [
                    'paths' => [__DIR__],
                ],
            ],
            // 关系 Controller => Logic  => Contract,Factory=>Service => Contract,Mapper => Model
            // 关系 Request => Controller => Resource => Response
            'mine' => [
                'package' => [
                    'php' => '>=8.1',
                    'mine/core' => '~1.0',
                ],
                'package-dev' => [],
            ],
            'devtool' => [
                'generator' => [
                    'logic' => [
                        'namespace' => 'Logic',
                    ],
                    'request' => [
                        'namespace' => 'Request',
                    ],
                    'response' => [
                        'namespace' => 'Response',
                    ],
                    'controller' => [
                        'namespace' => 'Controller',
                        'inheritance' => 'Logic',
                    ],
                    'resource' => [
                        'namespace' => 'Resource',
                    ],
                    'viewObject' => [
                        'namespace' => 'ViewObject',
                    ],
                    'contract' => [
                        'namespace' => 'Contract',
                        'inheritance' => 'Service',
                        'suffix' => 'Interface',
                    ],
                    'factory' => [
                        'namespace' => 'Factory',
                        'inheritance' => 'service',
                    ],
                    'service' => [
                        'namespace' => 'Service',
                        'inheritance' => 'Mapper',
                        'implements' => 'Contract',
                    ],
                    'mapper' => [
                        'namespace' => 'Mapper',
                        'inheritance' => 'Model',
                    ],
                    'model' => [
                        'namespace' => 'Model',
                    ],
                    'annotation' => [
                        'namespace' => 'Annotation',
                    ],
                    'aspect' => [
                        'namespace' => 'Aspect',
                    ],
                    'command' => [
                        'namespace' => 'Command',
                    ],
                    'job' => [
                        'namespace' => 'Jobs',
                    ],
                    'emun' => [
                        'namespace' => 'Constant',
                        'suffix' => 'Emun',
                    ],
                    'type' => [
                        'namespace' => 'Constant',
                        'suffix' => 'Type',
                    ],
                    'status' => [
                        'namespace' => 'Constant',
                        'suffix' => 'Status',
                    ],
                    'error' => [
                        'namespace' => 'Constant',
                        'suffix' => 'Error',
                    ],
                    'exception' => [
                        'namespace' => 'Exception',
                    ],
                    'event' => [
                        'namespace' => 'Event',
                    ],
                    'listener' => [
                        'namespace' => 'Listener',
                    ],
                    'task' => [
                        'namespace' => 'Task',
                    ],
                    'process' => [
                        'namespace' => 'Process',
                    ],
                ],
            ],
            'commands' => [
                // ...$this->getDatabaseCommands(),
                // FixCommand::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for devtool.',
                    'source' => dirname(__DIR__, 1) . '/publish/devtool.php',
                    'destination' => BASE_PATH . '/config/autoload/devtool.php',
                ],
            ],
        ];
    }

    private function getDatabaseCommands(): array
    {
        if (! class_exists(CommandCollector::class)) {
            return [];
        }

        return CommandCollector::getAllCommands();
    }
}
