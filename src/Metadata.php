<?php

declare(strict_types=1);

/**
 * This file is part of MaxPHP.
 *
 * @link     https://github.com/marxphp
 * @license  https://github.com/marxphp/max/blob/master/LICENSE
 */

namespace Max\Aop;

class Metadata
{
    /**
     * @param string $className      类名
     * @param bool   $hasConstructor 是否有构造函数
     */
    public function __construct(
        public string $className,
        public bool $hasConstructor = false
    ) {
    }
}
