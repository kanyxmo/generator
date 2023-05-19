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
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use ReflectionClass;

use function Hyperf\Support\make;

class RewriteMethodsCommitVisitor extends NodeVisitorAbstract
{
    /**
     * @var string[]
     */
    protected array $methods = [];

    private Parser $parser;

    private ReflectionClass $reflectionClass;

    public function __construct(
        protected string $class,
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

            $code && $this->getAllMethods($this->parser->parse($code));
            if ($paarent) {
                foreach ((new ReflectionClass(container()->get($class)))->getParentClass() ?: [] as $parentClass) {
                    $parentClassReflectionClass = (new ReflectionClass($parentClass));
                    $code = file_get_contents($parentClassReflectionClass->getFileName());
                    $code && $this->getAllMethods($this->parser->parse($code));
                    foreach ($parentClassReflectionClass->getTraitNames() as $trait) {
                        if (Str::startsWith($trait, 'Hyperf\Di')) {
                            continue;
                        }

                        $traitReflectionClass = (new ReflectionClass($trait));
                        $code = file_get_contents($traitReflectionClass->getFileName());
                        $code && $this->getAllMethods($this->parser->parse($code));
                    }
                }
            }
        }
    }

    public function enterNode(Node $node)
    {
        if ($node == $node instanceof Class_) {
            $node->setDocComment(new Doc($this->parse()));

            return $node;
        }

        return $node;
    }

    protected function parse(): string
    {
        $doc = '/**' . PHP_EOL;
        $doc = $this->parseMethods($doc);
        $doc .= ' */';

        return $doc;
    }

    protected function parseMethods(string $doc): string
    {
        foreach ($this->methods as $method) {
            [$func,$type] = $method;
            // $doc .= str_replace('static', $this->class, sprintf(' * @method  %s ', sprintf($func, $type))) . PHP_EOL;
            $doc .= sprintf(' * @method  %s ', sprintf($func, $type)) . PHP_EOL;
        }

        return $doc;
    }

    protected function getAllMethods(array $nodes): void
    {
        $printer = new Standard();

        foreach ($nodes as $namespace) {
            if (! $namespace instanceof Namespace_) {
                continue;
            }

            foreach ($namespace->stmts as $class) {
                if ($class == ($class instanceof Class_ || $class instanceof Trait_ || $class instanceof Interface_)) {
                    assert($class instanceof ClassLike);
                    foreach ($class->stmts as $method) {
                        if (! ($method instanceof ClassMethod)) {
                            continue;
                        }

                        $method->stmts = null;
                        $method->attrGroups = [];
                        $method->setAttribute('comments', []);
                        if (! in_array($method->name->name, ['__closure']) && (! $method->isPublic() || Str::startsWith(
                            $method->name->name,
                            '__'
                        ))) {
                            continue;
                        }

                        if ($method instanceof ClassMethod) {
                            $method->flags = Class_::MODIFIER_PUBLIC;
                            $func = str_replace(
                                ['public', 'function', ';'],
                                ['static', '%s', ''],
                                $printer->prettyPrint([$method])
                            );
                            $func = explode(':', $func);
                            $this->methods[] = [$func[0], $func[1] ?? 'mixed'];
                        }
                    }
                }
            }
        }
    }
}
