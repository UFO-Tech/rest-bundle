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

use function str_replace;

class RestRequestParser implements RestRequestParserInterface
{
    public function __construct(
        public RestServiceMap $serviceMap,
        protected RequestCarrier $requestCarrier,
        protected IRpcSecurity $rpcSecurity,
        protected RpcMainConfig $rpcConfig,

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
        $ver = $request->attributes->get('ver');
        try {
            $routeName = str_replace('_' . $ver, '', $routeName);
            $route = $this->serviceMap->getRoute($routeName, $ver);
        } catch (RestResourceNotFoundException $e) {
            throw new RestResourceNotFoundException('Route "'.$request->getPathInfo().'" is not found in REST Service Map');
        }
        if (!in_array($request->getMethod(), $route->getMethods())) {
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
        $this->rpcSecurity->setTokenHolder($holder);
        $this->requestCarrier->setCarrier($carrier);
        $this->rpcSecurity->isValidApiRequest();
    }
}