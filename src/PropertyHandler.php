<?php

declare(strict_types=1);

/**
 * This file is part of MaxPHP.
 *
 * @link     https://github.com/marxphp
 * @license  https://github.com/marxphp/max/blob/master/LICENSE
 */

namespace Max\Aop;

use Max\Aop\Collector\PropertyAnnotationCollector;

trait PropertyHandler
{
    protected bool $__propertyHandled = false;

    protected function __handleProperties(): void
    {
        if (! $this->__propertyHandled) {
            foreach (PropertyAnnotationCollector::getByClass(self::class) as $property => $attributes) {
                foreach ($attributes as $attribute) {
                    $attribute->handle($this, $property);
                }
            }
            $this->__propertyHandled = true;
        }
    }
}
