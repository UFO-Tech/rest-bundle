<?php

namespace Ufo\RestBundle\Services;

use Symfony\Component\HttpFoundation\Request;
use Ufo\JsonRpcBundle\Server\RequestPrepare\IRpcHttpCarrier;
use Ufo\JsonRpcBundle\Server\ServiceMap\Service;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcObject\RpcBatchRequest;
use Ufo\RpcObject\RpcRequest;
use Ufo\RpcObject\RPC\Info;
use Ufo\RestBundle\Exceptions\RestBadRequestException;

use function json_decode;
use function uniqid;

class RpcFromRest implements IRpcHttpCarrier
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
            $this->service->name,
            [
                ...$this->castStringParams([
                    ...$routeParams,
                    ...$this->request->query->all(),
                    ...$this->request->request->all(),
                ]),
                ...$body,
            ],
            apiVersion: $this->request->attributes->get('ver', Info::DEFAULT_VERSION),
        );
    }

    /**
     * Path, query and form values are always strings; the RPC core does not convert them.
     * A string is cast to the declared scalar type of the method parameter when it is an exact representation.
     */
    protected function castStringParams(array $params): array
    {
        $definitions = $this->service->getParams();
        foreach ($params as $name => $value) {
            if (is_string($value) && isset($definitions[$name])) {
                $params[$name] = static::castString($value, $definitions[$name]->getRealType());
            }
        }
        return $params;
    }

    /**
     * Rules: a type accepting strings (string, mixed) keeps the value; "" is null for a nullable type;
     * an integer literal is int, a numeric string is float, "true"/"1" and "false"/"0" are bool.
     * Anything else is left as is, so the RPC core reports the type error.
     */
    public static function castString(string $value, string|array $realType): mixed
    {
        $types = array_map(fn(string $type) => strtolower(ltrim($type, '?')), (array) $realType);
        if (is_string($realType) && str_starts_with($realType, '?')) {
            $types[] = 'null';
        }
        if (array_intersect($types, ['string', 'mixed'])) {
            return $value;
        }
        if ($value === '' && in_array('null', $types, true)) {
            return null;
        }
        if (in_array('int', $types, true)
            && preg_match('/^-?(0|[1-9]\d*)$/', $value)
            && ($int = filter_var($value, FILTER_VALIDATE_INT)) !== false
        ) {
            return $int;
        }
        if (in_array('float', $types, true) && is_numeric($value)) {
            return (float) $value;
        }
        if (in_array('bool', $types, true)) {
            $bool = match (strtolower($value)) {
                'true', '1' => true,
                'false', '0' => false,
                default => null,
            };
            if ($bool !== null) {
                return $bool;
            }
        }
        return $value;
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
