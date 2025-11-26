<?php

namespace Devhammed\Photoshop;

use DateTime;
use ReflectionClass;
use Devhammed\Photoshop\Attributes\Setter;
use Devhammed\Photoshop\Attributes\Getter;
use Devhammed\Photoshop\Contracts\Rawable;
use Devhammed\Photoshop\Exceptions\ApplicationException;

abstract class Model implements Rawable
{
    protected Application $app;

    protected string $ref;

    protected array $attributes = [];

    protected array $methods = [];

    protected array $casts = [];

    protected array $fillable = [];

    protected array $getterMap = [];

    protected array $setterMap = [];

    public function __construct(Application $app, string $ref)
    {
        $this->app = $app;

        $this->ref = $ref;

        $this->initializeAttributes();
    }

    public function __get(string $name): mixed
    {
        if ( ! in_array($name, $this->attributes)) {
            throw new ApplicationException("Unknown property $name.");
        }

        if (isset($this->getterMap[$name])) {
            return $this->{$this->getterMap[$name]}();
        }

        $value = $this->app->execute("{$this->ref}.{$name}");

        return $this->deserializeValue($value, $name);
    }

    public function __set(string $name, mixed $value): void
    {
        if ( ! in_array($name, $this->attributes)) {
            throw new ApplicationException("Unknown property $name.");
        }

        if ( ! in_array($name, $this->fillable)) {
            throw new ApplicationException("Cannot set a readonly property $name.");
        }

        if (isset($this->setterMap[$name])) {
            $this->{$this->setterMap[$name]}($value);
        } else {
            $value = $this->serializeValue($value, $name);

            $this->app->execute("{$this->ref}.{$name} = $value");
        }
    }

    public function __call(string $name, array $args): mixed
    {
        if ( ! in_array($name, $this->methods)) {
            throw new ApplicationException("Unknown method $name.");
        }

        $argList = implode(', ', array_map(fn($a) => $this->serializeValue($a), $args));

        return $this->app->execute("{$this->ref}.{$name}($argList)");
    }

    public function __toString(): string
    {
        return $this->ref;
    }

    public function toRaw(): string
    {
        return $this->ref;
    }

    protected function initializeAttributes(): void
    {
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getMethods() as $method) {
            if ( ! $method->isProtected()) {
                continue;
            }

            foreach ($method->getAttributes(Getter::class) as $attr) {
                $instance = $attr->newInstance();
                $this->getterMap[$instance->name] = $method->getName();
                $this->attributes[] = $instance->name;
            }

            foreach ($method->getAttributes(Setter::class) as $attr) {
                $instance = $attr->newInstance();
                $this->setterMap[$instance->name] = $method->getName();
                $this->attributes[] = $instance->name;
                $this->fillable[] = $instance->name;
            }
        }

        $this->attributes = array_unique($this->attributes);

        $this->fillable = array_unique($this->fillable);
    }

    protected function serializeValue(mixed $value, ?string $name = null): string
    {
        if ($value instanceof Rawable) {
            return $value->toRaw();
        }

        if ($value instanceof DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($name !== null) {
            $type = $this->casts[$name] ?? null;
        } else {
            $type = gettype($value);
        }

        return match ($type) {
            'array', 'object', 'NULL', 'null' => json_encode($value),
            'boolean', 'bool' => $value ? 'true' : 'false',
            'double', 'float', 'integer', 'int', 'string' => (string) $value,
            default => 'null',
        };
    }

    protected function deserializeValue(string $value, ?string $name = null): mixed
    {
        if ($name !== null) {
            $type = $this->casts[$name] ?? null;
        } else {
            $type = null;
        }

        return match ($type) {
            'array' => json_decode($value, true),
            'object' => json_decode($value),
            'datetime' => new DateTime($value),
            'double', 'float' => (float) $value,
            'int', 'integer' => (int) $value,
            'boolean', 'bool' => in_array($value, [1, '1', 'true', true, 'on', 'yes']),
            default => $value,
        };
    }
}
