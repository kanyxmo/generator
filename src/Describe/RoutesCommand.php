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

namespace Mine\Devtool\Describe;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\MiddlewareManager;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Handler;
use Hyperf\HttpServer\Router\RouteCollector;
use Hyperf\Rpc\Protocol;
use Hyperf\Rpc\ProtocolManager;
use Hyperf\Stringable\Str;
use Mine\JsonRpc\PathGenerator;
use Mine\RpcServer\Router\DispatcherFactory as RpcDispatcherFactory;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Hyperf\Support\env;
use function Hyperf\Support\make;

#[Command]
class RoutesCommand extends HyperfCommand
{

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ConfigInterface $config
    ) {
        parent::__construct('describe:routes');
    }

    public function handle(): void
    {
        $path = $this->input->getOption('path');
        $server = $this->input->getOption('server');
        $factory = $this->container->get(DispatcherFactory::class);
        $router = $factory->getRouter($server);
        $this->show($this->analyzeRouter($server, $router, $path), $this->output);
    }

    protected function configure(): void
    {
        $this->setDescription('Describe the routes information.')
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Get the detail of the specified route information by path'
            )
            ->addOption(
                'server',
                'S',
                InputOption::VALUE_OPTIONAL,
                'Which server you want to describe routes.',
                'http'
            );
    }

    protected function analyzeRouter(
        string $server,
        RouteCollector $router,
        ?string $path
    ) {
        $data = [];
        [$staticRouters, $variableRouters] = $router->getData();
        foreach ($staticRouters as $method => $items) {
            foreach ($items as $handler) {
                $this->analyzeHandler($data, $server, $method, $path, $handler);
            }
        }

        foreach ($variableRouters as $method => $items) {
            foreach ($items as $item) {
                if (is_array($item['routeMap'] ?? false)) {
                    foreach ($item['routeMap'] as $routeMap) {
                        $this->analyzeHandler($data, $server, $method, $path, $routeMap[0]);
                    }
                }
            }
        }

        return $data;
    }

    protected function analyzeHandler(
        array &$data,
        string $serverName,
        string $method,
        ?string $path,
        Handler $handler
    ): void {
        $uri = $handler->route;
        if ($path !== null && !Str::contains($uri, $path)) {
            return;
        }

        if (is_array($handler->callback)) {
            $action = $handler->callback[0] . '::' . $handler->callback[1];
        } elseif (is_string($handler->callback)) {
            $action = $handler->callback;
        } elseif (is_callable($handler->callback)) {
            $action = 'Closure';
        } else {
            $action = (string) $handler->callback;
        }

        $unique = sprintf('%s|%s|%s', $serverName, $uri, $action);
        if (isset($data[$unique])) {
            $data[$unique]['method'][] = $method;
        } else {
            // method,uri,name,action,middleware
            $registeredMiddlewares = MiddlewareManager::get($serverName, $uri, $method);
            $middlewares = $this->config->get('middlewares.' . $serverName, []);

            $middlewares = array_merge($middlewares, $registeredMiddlewares);
            $data[$unique] = [
                'server' => $serverName,
                'method' => [$method],
                'uri' => $uri,
                'action' => $action,
                'middleware' => implode(PHP_EOL, array_unique($middlewares)),
            ];
        }
    }

    private function show(array $data, ?SymfonyStyle $output): void
    {
        $rows = [];
        foreach ($data as $route) {
            $route['method'] = implode('|', $route['method']);
            $rows[] = $route;
            $rows[] = new TableSeparator();
        }

        $rows = array_slice($rows, 0, count($rows) - 1);
        $table = new Table($output);
        $table
            ->setHeaders(['Server', 'Method', 'URI', 'Action', 'Middleware'])
            ->setRows($rows);
        $table->render();
    }
}
