<?php

namespace Tourze\ScanDetectBundle\EventSubscriber;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * 对一些频繁返回500的IP，我们做下限制，防止被人一直扫描
 */
class ScanDetect404Subscriber
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 9999)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $maxTime = $this->getMaxTime();
        if ($maxTime <= 0) {
            return;
        }
        if ($this->checkSafeIP($event->getRequest()->getClientIp())) {
            return;
        }

        $accessDeniedKey = $this->getAccessDeniedKey($event->getRequest()->getClientIp());
        if (null !== $accessDeniedKey && $this->cache->get($accessDeniedKey) > 0) {
            $response = new Response('ScanForbidden', 403);
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION)]
    public function onKernelException(ExceptionEvent $event): void
    {
        if (!($event->getThrowable() instanceof NotFoundHttpException)) {
            return;
        }
        if ($this->checkSafeIP($event->getRequest()->getClientIp())) {
            return;
        }

        // 如果一个IP，在1分钟内发生了20次异常，那么就可能是有鬼的请求
        $maxTime = $this->getMaxTime();
        if ($maxTime <= 0) {
            return;
        }
        $scanDetectKey = $this->getScanDetectKey($event->getRequest()->getClientIp());
        if (null === $scanDetectKey) {
            return;
        }

        $v = intval($this->cache->get($scanDetectKey, 0));
        if ($v > $maxTime) {
            $accessDeniedKey = $this->getAccessDeniedKey($event->getRequest()->getClientIp());
            if (null !== $accessDeniedKey) {
                $this->cache->set($accessDeniedKey, time(), 60 * 5);
            }
        }

        ++$v;
        $this->cache->set($scanDetectKey, $v, 60);
    }

    protected function getMaxTime(): int
    {
        return intval($_ENV['SCAN_DETECT_404_FOUND_TIME'] ?? 20);
    }

    private function checkSafeIP(?string $ip): bool
    {
        return in_array($ip, [
            '127.0.0.1',
            '::1',
        ]);
    }

    private function getAccessDeniedKey(?string $ip): ?string
    {
        if (null === $ip) {
            return null;
        }
        $ip = str_replace(':', '.', $ip);

        return "ACCESS_DENIED_{$ip}";
    }

    private function getScanDetectKey(?string $ip): ?string
    {
        if (null === $ip) {
            return null;
        }
        $ip = str_replace(':', '.', $ip);

        return "scan_detect_404_{$ip}";
    }
}
