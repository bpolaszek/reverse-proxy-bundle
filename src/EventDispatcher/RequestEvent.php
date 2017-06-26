<?php

namespace BenTools\ReverseProxyBundle\EventDispatcher;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\Event;

class RequestEvent extends Event
{
    const ON_REQUEST_READY = 'reverse_proxy.on_request_ready';
    const ON_RESPONSE_READY = 'reverse_proxy.on_response_ready';

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * RequestEvent constructor.
     * @param RequestInterface       $request
     * @param ResponseInterface|null $response
     */
    public function __construct(RequestInterface $request, ResponseInterface $response = null)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * @param RequestInterface $request
     */
    public function setRequest(RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * @return ResponseInterface
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * @param ResponseInterface $response
     */
    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * @return bool
     */
    public function hasResponse()
    {
        return null !== $this->response;
    }
}