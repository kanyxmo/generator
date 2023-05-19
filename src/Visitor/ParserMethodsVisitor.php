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

namespace Mine\Devtool\Visitor;

use Hyperf\Stringable\Str;
use Mine\ComposerManager;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use ReflectionClass;

use function Hyperf\Support\make;

class ParserMethodsVisitor
{
    protected ReflectionClass $reflectionClass;

    protected array $methods = [];

    protected array $uses = [];

    protected Parser $parser;

    public function __construct(
        protected string $class,
        protected bool $notStmts = false,
        $paarent = false
    ) {
        if (class_exists($class) || trait_exists($class) || interface_exists($class)) {
            $parserFactory = new ParserFactory();
            $this->parser = $parserFactory->create(ParserFactory::ONLY_PHP7);
            $file = ComposerManager::findFile($class);
            if (! $file) {
                $this->reflectionClass = new ReflectionClass(make($class));
                $file = $this->reflectionClass->getFileName();
            }

            $code = file_get_contents($file);
            $this->getAllMethods($this->parser->parse($code));
            if ($paarent) {
                foreach ((new ReflectionClass(make($class)))->getParentClass() ?: [] as $parentClass) {
                    $parentClassReflectionClass = (new ReflectionClass($parentClass));
                    $code = file_get_contents($parentClassReflectionClass->getFileName());
                    $this->getAllMethods($this->parser->parse($code));
                    foreach ($parentClassReflectionClass->getTraitNames() as $trait) {
                        if (Str::startsWith($trait, 'Hyperf\Di')) {
                            continue;
                        }

                        $traitReflectionClass = (new ReflectionClass($trait));
                        $code = file_get_contents($traitReflectionClass->getFileName());
                        $this->getAllMethods($this->parser->parse($code));
                    }
                }
            }
        }
    }

    public function getAllNodesMethods(): ?array
    {
        return $this->methods;
    }

    public function getAllNodesUses(): ?array
    {
        return $this->uses;
    }

    protected function getAllMethods(array $nodes): void
    {
        foreach ($nodes as $namespace) {
            if (! $namespace instanceof Namespace_) {
                continue;
            }

            foreach ($namespace->stmts as $class) {
                switch ($class) {
                    case $class instanceof Class_ || $class instanceof Trait_ || $class instanceof Interface_:
                        assert($class instanceof ClassLike);
                        foreach ($class->stmts as $method) {
                            if ($method instanceof ClassConst) {
                                if (! $method->isPublic()) {
                                    continue;
                                }

                                $this->methods[] = $method;
                            }

                            if ($method instanceof ClassMethod) {
                                if (! in_array(
                                    $method->name->name,
                                    ['__closure']
                                ) && (! $method->isPublic() || Str::startsWith($method->name->name, '__'))) {
                                    continue;
                                }

                                $this->notStmts && $method->stmts = null;
                                $this->methods[$method->name->name] = $method;
                            }
                        }

                        break;

                    case $class instanceof Use_:
                        assert($class instanceof Use_);
                        foreach ($class->uses as $use) {
                            if ($use->type == 0) {
                                $this->uses[$use->name->toString()] = $use->alias;
                            }
                        }

                        break;
                    default:
                        break;
                }
            }
        }
    }
}
