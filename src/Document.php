<?php

namespace Devhammed\Photoshop;

class Document extends Model
{
    protected array $attributes = [
        'name',
    ];

    protected array $methods = [
        'close',
    ];
}
