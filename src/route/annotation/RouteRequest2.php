<?php

declare(strict_types=1);

namespace shiyun\route\annotation;

use shiyun\annotation\AbstractAnnotation;
use Attribute;

/**
 * @Annotation
 * Class RequestMapping
 * @package Sunsgne\Annotations\Annotations\Mapping
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class RouteRequest2 extends AbstractAnnotation
{
    /** @var array|false|string[]  */
    public $methods;

    /** @var mixed  */
    public $path;

    /** @var array|string[]  */
    public array $normal = ["GET", "POST", "PUT", "PATCH", "DELETE", "HEADER", "OPTIONS"];

    /**
     * @param ...$value
     */
    public function __construct(...$value)
    {
        $formattedValue = $this->formatParams($value);
        $this->path    = $formattedValue["path"];
        if (isset($formattedValue['methods'])) {
            if (is_string($formattedValue['methods'])) {
                // Explode a string to a array
                $this->methods = explode(',', mb_strtoupper(str_replace(' ', '', $formattedValue['methods']), 'UTF-8'));
            } else {
                $methods = [];
                foreach ($formattedValue['methods'] as $method) {
                    $methods[] = mb_strtoupper(str_replace(' ', '', $method), 'UTF-8');
                }
                $this->methods = $methods;
            }
        }
    }
    protected function formatParams($value): array
    {
        if (isset($value[0])) {
            $value = $value[0];
        }
        if (!is_array($value)) {
            $value = ['value' => $value];
        }
        return $value;
    }
    /**
     * @return array
     * @datetime 2022/7/4 13:50
     * @author zhulianyou
     */
    public function setMethods(): array
    {
        $normalMethods = [];
        foreach ($this->methods as $method) {
            if (in_array($method, $this->normal)) {
                $normalMethods[] = $method;
            }
        }
        return $normalMethods;
    }
}
