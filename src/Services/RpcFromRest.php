<?php

namespace Ufo\RestBundle\Services;

use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RPC\Info;
use Ufo\RestBundle\Exceptions\RestBadRequestException;

use function json_decode;
use function uniqid;

class RpcFromRest implements IRpcRequestCarrier
{

    protected ?RpcRequest $requestObject = null;

    /**
     * @throws AbstractRpcErrorException
     */
    public function __construct(
        protected Service $service,
        protected Request $request
    )
    {
        $this->prepare();
    }

    /**
     * @throws AbstractRpcErrorException
     */
    protected function prepare(): void
    {
        $content = $this->request->getContent();
        if ($this->request->getContentTypeFormat() === 'form') {
            $content = '';
        }
        try {
            $body = $content === '' ? [] : json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RestBadRequestException('Invalid JSON request body', previous: $e);
        }
        if (!is_array($body)) {
            throw new RestBadRequestException('JSON request body must be an object or array');
        }
        $routeParams = $this->request->attributes->get('_route_params', []);
        foreach (array_keys($routeParams) as $key) {
            if (in_array($key, ['api_group', 'ver', 'rpcRoute', '_rest_route', '_rest_name_prefix', '_rest_doc', '_ver', '_controller', '_format', '_locale', '_stateless'], true)) {
                unset($routeParams[$key]);
            }
        }
        $this->requestObject = new RpcRequest(
            uniqid(),
            $this->service->getName(),
            [
                ...$routeParams,
                ...$this->request->query->all(),
                ...$this->request->request->all(),
                ...$body,
            ],
            apiVersion: $this->request->attributes->get('ver', Info::DEFAULT_VERSION),
        );
    }

    public function getRequestObject(): RpcRequest
    {
        return $this->requestObject ?? throw new WrongWayException();
    }

    public function getBatchRequestObject(): RpcBatchRequest
    {
        throw new WrongWayException();
    }

    public function getHttpRequest(): Request
    {
        return $this->request;
    }
}
