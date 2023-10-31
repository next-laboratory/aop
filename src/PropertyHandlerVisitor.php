<?php

declare(strict_types=1);

/**
 * This file is part of MaxPHP.
 *
 * @link     https://github.com/marxphp
 * @license  https://github.com/next-laboratory/next/blob/master/LICENSE
 */

namespace Next\Aop;

use Next\Di\Reflection;
use Next\Utils\Composer;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use ReflectionException;
use ReflectionNamedType;
use ReflectionUnionType;

class PropertyHandlerVisitor extends NodeVisitorAbstract
{
    public function __construct(
        protected Metadata $metadata
    ) {
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof ClassMethod && $node->name->toString() === '__construct') {
            $this->metadata->hasConstructor = true;
        }
    }

    /**
     * @throws ReflectionException
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Class_) {
            $params          = [];
            $reflectionClass = Reflection::class($this->metadata->className);
            if ($reflectionConstructor = $reflectionClass->getConstructor()) {
                $declaringClass = $reflectionConstructor->getDeclaringClass()->getName();
                if ($classPath = Composer::getClassLoader()->findFile($declaringClass)) {
                    $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
                    $ast    = $parser->parse(file_get_contents($classPath));
                    foreach ($ast as $stmt) {
                        if ($stmt instanceof Node\Stmt\Namespace_) {
                            foreach ($stmt->stmts as $subStmt) {
                                if ($subStmt instanceof Class_) {
                                    foreach ($subStmt->stmts as $internal) {
                                        if ($internal instanceof ClassMethod && $internal->name->toString() === '__construct') {
                                            $params = $internal->getParams();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                foreach ($reflectionConstructor->getParameters() as $key => $reflectionParameter) {
                    $type = $reflectionParameter->getType();
                    if (is_null($type)
                        || ($type instanceof ReflectionNamedType && ($type->isBuiltin() || $type->getName() === 'Closure'))
                    ) {
                        continue;
                    }
                    if ($type instanceof ReflectionUnionType) {
                        $unionType = [];
                        foreach ($type->getTypes() as $reflectionNamedType) {
                            $unionType[] = ($reflectionNamedType->isBuiltin() ? '' : '\\') . $reflectionNamedType->getName();
                        }
                        $params[$key]->type = new Name(implode('|', $unionType));
                        continue;
                    }
                    $allowsNull         = $reflectionParameter->allowsNull() ? '?' : '';
                    $params[$key]->type = new Name($allowsNull . '\\' . $type->getName());
                }
            }
            $c = [];
            if (! $this->metadata->hasConstructor) {
                $constructor        = new ClassMethod('__construct', [
                    'params' => $params,
                ]);
                $constructor->flags = 1;
                if ($node->extends) {
                    $constructor->stmts[] = new If_(new FuncCall(new Name('method_exists'), [
                        new ClassConstFetch(new Name('parent'), 'class'),
                        new String_('__construct'),
                    ]), [
                        'stmts' => [
                            new Expression(new StaticCall(
                                new ConstFetch(new Name('parent')),
                                '__construct',
                                [new Arg(new FuncCall(new Name('func_get_args')), unpack: true)]
                            )),
                        ],
                    ]);
                }
                $constructor->stmts[] = new Expression(new MethodCall(
                    new Variable(new Name('this')),
                    '__handleProperties'
                ));
                $c                    = [$constructor];
            }

            $node->stmts = array_merge([new TraitUse([new Name('\Next\Aop\PropertyHandler')])], $c, $node->stmts);
        }
        if ($node instanceof ClassMethod && $node->name->toString() === '__construct') {
            array_unshift(
                $node->stmts,
                new Expression(new MethodCall(new Variable(new Name('this')), '__handleProperties'))
            );
        }
    }
}
