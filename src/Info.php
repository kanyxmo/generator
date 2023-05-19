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

use Mine\Devtool\Adapter\AbstractAdapter;
use Psr\Container\ContainerInterface;

class Info
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    public function get(string $key): ?AbstractAdapter
    {
        if (! $this->has($key)) {
            return null;
        }

        $class = __NAMESPACE__ . '\\Adapter\\' . ucfirst($key);

        return $this->container->get($class);
    }

    public function has(string $key): bool
    {
        return class_exists(__NAMESPACE__ . '\\Adapter\\' . ucfirst($key));
    }
}
