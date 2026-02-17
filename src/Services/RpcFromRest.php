<?php

namespace Ufo\RestBundle\Services;

use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;

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
        $this->requestObject = new RpcRequest(
            uniqid(),
            $this->service->getName(),
            [
                ...$this->request->attributes->get('_route_params'),
                ...$this->request->query->all(),
                ...$this->request->request->all(),
                ...json_decode($this->request->getContent(), true) ?? []
            ]
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
