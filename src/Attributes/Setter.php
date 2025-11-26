<?php

namespace Devhammed\Photoshop\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Setter
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
