<?php

declare(strict_types=1);

namespace Tourze\ScanDetectBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Tourze\ScanDetectBundle\Service\ScanDetectService;

/**
 * 对一些频繁返回404的IP，我们做下限制，防止被人一直扫描
 */
class ScanDetect404Subscriber
{
    public function __construct(
        private readonly ScanDetectService $scanDetectService,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 9999)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $ipAddress = $request->getClientIp();

        if (null === $ipAddress) {
            return;
        }

        if ($this->scanDetectService->isIPBlocked($ipAddress)) {
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

        $request = $event->getRequest();
        $ipAddress = $request->getClientIp();

        if (null === $ipAddress) {
            return;
        }

        // 记录404扫描尝试
        $this->scanDetectService->recordScanAttempt($request, 404);

        // 检查是否需要封禁IP
        $this->scanDetectService->checkAndBlockIP($ipAddress);
    }
}
