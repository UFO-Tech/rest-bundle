<?php

namespace Ufo\RestBundle\Services;

use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\ConfigService\RpcMainConfig;
use Ufo\JsonRpcBundle\Exceptions\ServiceNotFoundException;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcSecurity;
use Ufo\JsonRpcBundle\Security\Interfaces\IRpcTokenHolder;
use Ufo\JsonRpcBundle\Security\TokenHolders\HttpTokenHolder;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcRequestCarrier;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcError\RpcTokenNotSentException;
use Ufo\RpcError\WrongWayException;
use Ufo\RestBundle\Exceptions\RestResourceNotAllowedException;
use Ufo\RestBundle\Exceptions\RestResourceNotFoundException;
use Ufo\RestBundle\Interfaces\RestRequestParserInterface;
use Ufo\RpcObject\RPC\Info;
use Ufo\JsonRpcBundle\Server\ApiGroupContext;
use Ufo\RpcObject\RPC\APIGroups;
use Ufo\JsonRpcBundle\EventDrivenModel\RpcEventFactory;


class RestRequestParser implements RestRequestParserInterface
{
    public function __construct(
        public RestServiceMap $serviceMap,
        protected RequestCarrier $requestCarrier,
        protected IRpcSecurity $rpcSecurity,
        protected RpcMainConfig $rpcConfig,
        protected ApiGroupContext $apiGroupContext,
        protected RpcEventFactory $eventFactory,
    ) {}

    /**
     * @throws RpcRuntimeException
     * @throws WrongWayException
     * @throws RpcAsyncRequestException
     * @throws RestResourceNotFoundException
     * @throws RestResourceNotAllowedException
     * @throws AbstractRpcErrorException
     * @throws ServiceNotFoundException
     * @throws RpcMethodNotFoundExceptionRpc
     */
    public function parse(string $routeName, Request $request): Service
    {
        $this->apiGroupContext->setGroup($request->attributes->get('api_group', APIGroups::DEFAULT_GROUP));
        $ver = $request->attributes->get('ver', Info::DEFAULT_VERSION);
        try {
            if (!$request->attributes->has('_rest_route') && str_ends_with($routeName, '_' . $ver)) {
                $routeName = substr($routeName, 0, -strlen('_' . $ver));
            }
            $route = $this->serviceMap->getRoute($routeName, $ver);
        } catch (RestResourceNotFoundException $e) {
            throw new RestResourceNotFoundException('Route "'.$request->getPathInfo().'" is not found in REST Service Map');
        }
        $method = $request->getMethod();
        if ($method === 'HEAD' && in_array('GET', $route->getMethods(), true)) {
            $method = 'GET';
        }
        if (!in_array($method, $route->getMethods(), true)) {
            throw new RestResourceNotAllowedException();
        }
        $service = $this->serviceMap->getService($routeName, $ver);

        $this->initRequest(
            new HttpTokenHolder($this->rpcConfig, $request),
            new RpcFromRest($service, $request)
        );
        return $service;
    }

    /**
     * @throws RpcTokenNotSentException
     * @throws RpcInvalidTokenException
     */
    protected function initRequest(IRpcTokenHolder $holder, IRpcRequestCarrier $carrier): void
    {
        $this->eventFactory->reset();
        $this->rpcSecurity->setTokenHolder($holder);
        $this->requestCarrier->setCarrier($carrier);
        $this->rpcSecurity->isValidApiRequest();
    }
}
