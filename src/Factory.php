<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Naixiaoxin\HyperfWechat;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\CoroutineHandler;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class Factory.
 *
 * @method \EasyWeChat\OfficialAccount\Application  officialAccount(string $name = "default", array $config = [])
 * @method \EasyWeChat\Work\Application  work(string $name = "default", array $config = [])
 * @method \EasyWeChat\MiniProgram\Application  miniProgram(string $name = "default", array $config = [])
 * @method \EasyWeChat\Payment\Application  payment(string $name = "default", array $config = [])
 * @method \EasyWeChat\OpenPayment\Application  openPlatform(string $name = "default", array $config = [])
 * @method \EasyWeChat\OpenWork\Application  openWork(string $name = "default", array $config = [])
 * @method \EasyWeChat\MicroMerchant\Application  microMerchant(string $name = "default", array $config = [])
 */
class Factory
{
    protected $configMap
        = [
            'officialAccount' => 'official_account',
            'work' => 'work',
            'miniProgram' => 'mini_program',
            'payment' => 'payment',
            'openPlatform' => 'open_platform',
            'openWork' => 'open_work',
            'microMerchant' => 'micro_merchant',
        ];

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var CacheInterface
     */
    protected $cache;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->config = $container->get(ConfigInterface::class);
        $this->cache = $container->get(CacheInterface::class);
    }

    public function __call($functionName, $args)
    {
        $accountName = $args[0] ?? 'default';
        $accountConfig = $args[1] ?? [];
        if (! isset($this->configMap[$functionName])) {
            throw new \Exception('方法不存在');
        }
        $configName = $this->configMap[$functionName];
        $config = $this->getConfig(sprintf('wechat.%s.%s', $configName, $accountName), $accountConfig);
        $app = \EasyWeChat\Factory::$functionName($config);
        $app->rebind('cache', $this->cache);
        $app['guzzle_handler'] = CoroutineHandler::class;
        $app->rebind('request', $this->getRequest());
        return $app;
    }

    /**
     * 获得配置.
     */
    private function getConfig(string $name, array $config = []): array
    {
        $defaultConfig = $this->config->get('wechat.defaults', []);
        $moduleConfig = $this->config->get($name, []);
        return array_merge($moduleConfig, $defaultConfig, $config);
    }

    /**
     * 获取Request请求
     */
    private function getRequest(): Request
    {
        $request = $this->container->get(RequestInterface::class);
        //return $this->container->get(RequestInterface::class);
        $uploadFiles = $request->getUploadedFiles() ?? [];
        $files = [];
        foreach ($uploadFiles as $k => $v) {
            $files[$k] = $v->toArray();
        }
        return new Request(
            $request->getQueryParams(),
            $request->getParsedBody(),
            [],
            $request->getCookieParams(),
            $files,
            $request->getServerParams(),
//             is_array($_SERVER) ? $_SERVER : $_SERVER->toArray(),
            $request->getBody()->getContents()
        );
    }
}
