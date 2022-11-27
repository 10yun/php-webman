<?php

declare(strict_types=1);

namespace shiyun\route;

use shiyun\annotation\IntfAnnotationHandle;
use shiyun\route\annotation\RouteGroup;
use shiyun\route\annotation\Middleware;
use shiyun\route\annotation\Route;
use shiyun\validate\annotation\Validate;
use shiyun\validate\annotation\ValidateMiddleware;
use Webman\Route as WebManRoute;
use Webman\Route\Route as RouteObject;

abstract class RouteAnnotationHandle implements IntfAnnotationHandle
{
    /**
     * 控制器注解
     * @var array
     */
    protected static array $controllers = [];

    /**
     * 控制器中间件
     * @var array
     */
    protected static array $middlewares = [];

    /**
     * 验证器
     * @var array
     */
    protected static array $validates = [];

    /**
     * 保存的路由
     * @var array
     */
    protected static array $routes = [];

    /**
     * 处理路由注解
     * @access public
     * @param array $item
     * @return void
     */
    public static function handle(array $item): void
    {
        if ($item['type'] === 'class') {
            self::handleClassAnnotation($item, $item['class']);
        } else if ($item['type'] === 'method') {
            self::handleMethodAnnotation($item, $item['class']);
        }
    }

    /**
     * 处理类注解
     * @access public
     * @param array $item
     * @param string $className
     * @return void
     */
    public static function handleClassAnnotation(array $item, string $className)
    {
        $annotation = $item['annotation'];
        $parameters = $item['parameters'];

        switch ($annotation) {
                // 控制器注解
            case  RouteGroup::class:
                static::$controllers[$className] ??= [];
                static::$controllers[$className][] = $parameters;
                break;

                // 控制器中间件注解
            case Middleware::class:
                static::$middlewares[$className] ??= [];
                static::$middlewares[$className][] = [
                    'middlewares' => (array)$parameters['middlewares'],
                    'only' => $parameters['only'],
                    'except' => $parameters['except'],
                ];
                break;

                // 验证器注解
            case Validate::class:
                // 转发到验证器注解处理
                ValidateAnnotationHandle::handle($item);
                break;
        }
    }

    /**
     * 处理方法注解
     * @access public
     * @param array $item
     * @param string $className
     * @return void
     */
    public static function handleMethodAnnotation(array $item, string $className)
    {
        $method = $item['method'];
        $annotation = $item['annotation'];
        $parameters = $item['parameters'];

        switch ($annotation) {
                // 路由注解
            case Route::class:
                static::$routes[] = $item;
                break;

                // 方法中间件注解
            case Middleware::class:
                $middlewares = static::$middlewares[$className . ':' . $method] ??= [];
                static::$middlewares[$className . ':' . $method] = array_merge($middlewares, (array)$parameters['middlewares']);
                break;

                // 验证器注解
            case Validate::class:
                // 转发到验证器注解处理
                ValidateAnnotationHandle::handle($item);
                break;
        }
    }

    /**
     * 创建路由
     * @access public
     * @param bool $isClear 是否清除路由
     * @return void
     */
    public static function createRoute(bool $isClear = true)
    {
        $useDefaultMethod = config('plugin.linfly.annotation.annotation.route.use_default_method', true);

        foreach (self::$routes as $item) {
            $parameters = $item['parameters'];

            if (!isset($item['arguments']['path']) && $useDefaultMethod) {
                $parameters['path'] = $item['method'];
            }

            // 忽略控制器注解的path参数
            if (str_starts_with($parameters['path'], '/')) {
                // 添加路由
                self::addRoute($parameters['path'], $item);
                continue;
            }

            foreach (self::$controllers[$item['class']] ?? [['prefix' => '']] as $controller) {
                // 控制器注解的path参数
                $controllerPath = trim($controller['prefix'] ?? '', '/');
                // 路由地址
                $path = ($controllerPath ? '/' . $controllerPath : '') . ($parameters['path'] ? '/' . $parameters['path'] : '');
                // 添加路由
                self::addRoute($path, $item);
            }
        }

        if ($isClear) {
            // 资源回收
            self::recovery();
        }
    }

    /**
     * 添加路由
     * @access public
     * @param string $path
     * @param array $item
     * @return void
     */
    protected static function addRoute(string $path, array $item)
    {
        $parameters = $item['parameters'];

        // 添加路由
        $route = WebManRoute::add($parameters['methods'], ($path ?: '/'), [$item['class'], $item['method']]);
        // 路由参数
        $parameters['params'] && $route->setParams($parameters['params']);
        // 路由名称
        $parameters['name'] && $route->name($parameters['name']);
        // 路由中间件
        self::addMiddleware($route, $item['class'], $item['method']);
    }

    /**
     * 添加中间件
     * @param RouteObject $route
     * @param string $class
     * @param string $method
     * @return void
     */
    protected static function addMiddleware(RouteObject $route, string $class, string $method)
    {
        // 类中间件
        $classMiddlewares = self::$middlewares[$class] ?? [];
        // 方法中间件
        $methodMiddlewares = self::$middlewares[$class . ':' . $method] ?? [];

        // 添加类中间件
        foreach ($classMiddlewares as $item) {
            // 填写了only参数且不在only参数中则跳过
            if ($item['only'] && !in_array($method, self::toLowerArray($item['only']))) {
                continue;
            } // 填写了except参数且在except参数中则跳过
            else if ($item['except'] && in_array($method, self::toLowerArray($item['except']))) {
                continue;
            }
            $route->middleware($item['middlewares']);
        }

        // 添加方法中间件
        $route->middleware($methodMiddlewares);

        // 如果有验证器注解则添加验证器中间件
        if (ValidateAnnotationHandle::isExistValidate($class, $method)) {
            $route->middleware(ValidateMiddleware::class);
        }
    }

    protected static function toLowerArray(array $data)
    {
        return array_map(fn ($item) => strtolower($item), $data);
    }

    /**
     * 资源回收
     * @access public
     * @return void
     */
    protected static function recovery()
    {
        // 清空控制器注解
        self::$controllers = [];
        // 清空控制器中间件注解
        self::$middlewares = [];
    }
}
