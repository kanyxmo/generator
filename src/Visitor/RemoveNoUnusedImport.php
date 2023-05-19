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

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Node\VarLikeIdentifier;
use PhpParser\NodeTraverser;

class RemoveNoUnusedImport extends AbstractVisitor
{
    public array $used = [];

    public array $usedImport = [];

    public function enterNode(Node $node): void
    {
        // var_dump('enterNode ' . $node::class);

        if ($node instanceof UseUse) {
            $alias = $node->name->getLast();
            if ($node->alias) {
                $alias = $node->alias->toString();
            }
            $this->option->setUse($node->name->toString(), $alias);
        }
        $parent = $node->getAttribute('parent');
        if ($node instanceof Name && $node->isUnqualified() && ! $parent instanceof UseUse) {
            // var_dump($node);
            $this->usedImport[] = $node->toString();
            // var_dump([$node->toString(), $parent::class]);
        }
        if (($node instanceof Identifier || $node instanceof VarLikeIdentifier) && ! $parent instanceof UseUse) {
            // var_dump($node);
            $this->usedImport[] = $node->toString();
            // var_dump([$node->toString(), $parent::class]);
        }
    }

    public function leaveNode(Node $node): int|Node
    {
        if ($node instanceof Use_) {
            return (int) NodeTraverser::REMOVE_NODE;
        }
        // var_dump('leaveNode ' . $node::class);
        if ($node instanceof Namespace_) {
            $used = [];
            assert($node instanceof Namespace_);
            foreach ($this->option->getUses()  as $class => $alias) {
                if (in_array($alias, array_unique($this->usedImport))) {
                    $used[$class] = $alias;
                }
            }
            $this->option->setUses($used);
            array_unshift($node->stmts, ...$this->getUseNodes());

            return $node;
        }

        return $node;
    }

    //  public function afterTraverse(array $nodes): void
    //  {
    //      $used = [];
    //      foreach ($this->option->getUses()  as $class => $alias) {
    //          if (in_array($alias, array_unique($this->usedImport))) {
    //              $used[$class] = $alias;
    //          }
    //      }
    //      $this->option->setUses($used);
    //      var_dump(count($this->option->getUses()));
    //      var_dump(count($used));
    //  }
}
