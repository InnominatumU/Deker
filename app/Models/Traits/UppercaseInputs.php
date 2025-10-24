<?php

namespace App\Models\Traits;

trait UppercaseInputs
{
    protected array $uppercaseExcept = []; // ex.: ['email']

    protected function toUpper(?string $v): ?string
    {
        return is_string($v) ? mb_strtoupper($v, 'UTF-8') : $v;
    }

    public function fill(array $attributes)
    {
        foreach ($attributes as $k => $v) {
            if (is_string($v) && !in_array($k, $this->uppercaseExcept ?? [], true)) {
                $attributes[$k] = $this->toUpper($v);
            }
        }
        return parent::fill($attributes);
    }
}
