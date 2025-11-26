<?php

namespace Devhammed\Photoshop\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Getter
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
