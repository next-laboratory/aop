<?php

declare(strict_types=1);

/**
 * This file is part of nextphp.
 *
 * @link     https://github.com/marxphp
 * @license  https://github.com/next-laboratory/next/blob/master/LICENSE
 */

namespace Next\Aop\Contract;

interface PropertyAttribute
{
    public function handle(object $object, string $property): void;
}
