<?php

declare(strict_types=1);
namespace Sypets\Brofix\Util;

interface Arrayable
{
    /**
     * Get the instance as an array.
     *
     * @return array<mixed>
     */
    public function toArray();

    /**
     * @param array<mixed> $values
     * @return mixed
     */
    public static function getInstanceFromArray(array $values);
}
