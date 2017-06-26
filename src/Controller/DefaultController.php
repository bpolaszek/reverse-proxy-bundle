<?php

namespace BenTools\ReverseProxyBundle\Controller;

use BenTools\ReverseProxyBundle\EventDispatcher\RequestEvent;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DefaultController extends Controller
{
    /**
     * @var DiactorosFactory
     */
    private $psr7Factory;

    /**
     * @var HttpFoundationFactory
     */
    private $httpFoundationFactory;

    /**
     * @var ClientInterface
     */
    private $guzzle;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * DefaultController constructor.
     * @param DiactorosFactory         $psr7Factory
     * @param HttpFoundationFactory    $httpFoundationFactory
     * @param ClientInterface          $guzzle
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        DiactorosFactory $psr7Factory,
        HttpFoundationFactory $httpFoundationFactory,
        ClientInterface $guzzle,
        EventDispatcherInterface $eventDispatcher
    ) {
    
        $this->psr7Factory = $psr7Factory;
        $this->httpFoundationFactory = $httpFoundationFactory;
        $this->guzzle = $guzzle;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param Request $sfRequest
     * @return Response
     * @throws BadRequestHttpException
     */
    public function __invoke(Request $sfRequest): Response
    {
        $customHeaders = new HeaderBag(array_filter($sfRequest->headers->all(), function ($key) {
            return 0 === stripos($key, 'X-Reverse-Proxy-');
        }, ARRAY_FILTER_USE_KEY));

        if (!$customHeaders->has('X-Reverse-Proxy-Host')) {
            throw new BadRequestHttpException("X-Reverse-Proxy-Host header must be provided.");
        }

        /** @var RequestInterface $psr7Request */
        $psr7Request = $this->psr7Factory->createRequest($sfRequest);

        foreach ($customHeaders as $key => $value) {
            $psr7Request = $psr7Request->withoutHeader($key);
        }

        $uri = $psr7Request->getUri()->withHost($customHeaders->get('X-Reverse-Proxy-Host'))->withPath($sfRequest->attributes->get('path'));

        if ($customHeaders->has('X-Reverse-Proxy-Scheme')) {
            $uri = $uri->withScheme($customHeaders->get('X-Reverse-Proxy-Scheme', $uri->getScheme()));
        }
        if ($customHeaders->has('X-Reverse-Proxy-Port')) {
            $uri = $uri->withPort($customHeaders->get('X-Reverse-Proxy-Port', $uri->getPort()));
        }
        if ($customHeaders->has('X-Reverse-Proxy-Path')) {
            $uri = $uri->withPath($customHeaders->get('X-Reverse-Proxy-Path', $uri->getPath()));
        }
        if ($customHeaders->has('X-Reverse-Proxy-Query')) {
            $uri = $uri->withQuery($customHeaders->get('X-Reverse-Proxy-Query', $uri->getQuery()));
        }
        if ($customHeaders->has('X-Reverse-Proxy-Fragment')) {
            $uri = $uri->withFragment($customHeaders->get('X-Reverse-Proxy-Fragment', $uri->getFragment()));
        }

        $psr7Request = $psr7Request->withUri($uri);

        $event = new RequestEvent($psr7Request);
        $this->eventDispatcher->dispatch(RequestEvent::ON_REQUEST_READY, $event);
        $psr7Response = $event->getResponse() ?? $this->guzzle->send($event->getRequest());

        $event = new RequestEvent($psr7Request, $psr7Response);
        $this->eventDispatcher->dispatch(RequestEvent::ON_RESPONSE_READY, $event);

        $sfResponse = $this->httpFoundationFactory->createResponse($event->getResponse());
        return $sfResponse;
    }
}
