<?php

namespace Ufo\RestBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;
use Ufo\RestBundle\DocAdapters\Outputs\PostmanAdapter;
use Ufo\JsonRpcBundle\Server\RequestPrepare\RequestCarrier;
use Ufo\JsonRpcBundle\Server\RpcRequestHandler;
use Ufo\RpcObject\RPC\Info;
use Ufo\RestBundle\DocAdapters\Outputs\OpenApiAdapter;
use Ufo\RestBundle\Exceptions\RestErrorInterface;
use Ufo\RestBundle\Exceptions\RestErrorNormalizer;
use Ufo\RestBundle\Interfaces\RestRequestParserInterface;
use Ufo\RestBundle\Services\RestServiceMap;
use Ufo\RpcObject\RpcError;

use function is_array;
use function json_encode;

use const JSON_PRETTY_PRINT;

class RestEntryPointController  extends AbstractController
{
    const string REST_API_ENTRYPOINT = RestServiceMap::ROUTE_PREFIX . 'entrypoint';

    const string REST_API_DOC = 'ufo_rest_doc';
    const string REST_API_DOC_VER = self::REST_API_DOC . '_ver';

    const string POSTMAN_ROUTE = 'ufo_rest_doc_postman';
    const string POSTMAN_ROUTE_VER = self::POSTMAN_ROUTE . '_ver';

    #[Route(
        '/{path}',
        name: self::REST_API_ENTRYPOINT,
        requirements: ['path' => '.+'],
        defaults: ['ver' => Info::DEFAULT_VERSION],
        methods: ['GET','POST','PUT','PATCH','DELETE','OPTIONS'],
        priority: -100
    )]
    #[Route(
        '/{ver}/{path}',
        name: self::REST_API_ENTRYPOINT . '_ver',
        requirements: ['path' => '.+', 'ver' => 'v\d+'],
        methods: ['GET','POST','PUT','PATCH','DELETE','OPTIONS'],
        priority: -90
    )]
    public function rest(
        string $rpcRoute,
        RestRequestParserInterface $parser,
        RequestCarrier $requestCarrier,
        RpcRequestHandler $requestHandler,
        Request $request,
    ): JsonResponse
    {
        try {
            $service = $parser->parse($rpcRoute, $request);
            $requestHandler->handle();

            $responseObj = $requestCarrier->getRequestObject()->getResponseObject();
            $responseObj->throwError();

            return new JsonResponse($responseObj->getResult(), 200);
        } catch (\Throwable $e) {
            return $this->responseError($request->getPathInfo(), $e, $responseObj?->getError() ?? null);
        }
    }

    protected function responseError(
        string $path,
        Throwable $error,
        ?RpcError $rpcError,
        bool $normalized = false
    ): JsonResponse
    {
        if ($error instanceof RestErrorInterface) {
            return new JsonResponse(
                [
                    '_error_object_standard'=> 'RFC 9457',
                    'status' => $error->getCode(),
                    'type'=> 'about:blank',
                    'instance'=> $path,
                    'title'=> $error::class,
                    'detail'=> $error->getMessage(),
                    ...(is_array($rpcError?->getData()) ? ['detail-data' => $rpcError->getData()] : [])
                ],
                $error->getCode()
            );
        }
        if (!$normalized) {
            return $this->responseError($path, RestErrorNormalizer::normalizeError($error), $rpcError, normalized: true);
        }
        throw $error;
    }


    #[Route(name: self::REST_API_DOC, defaults: ['ver' => Info::DEFAULT_VERSION], methods: ["GET"], priority: 40, format: 'json')]
    #[Route('/{ver<v\d+>}', name: self::REST_API_DOC_VER, methods: ["GET"], priority: 30, format: 'json')]
    public function openapiAction(OpenApiAdapter $openApiAdapter, Request $request): Response
    {
        $result = $openApiAdapter->adapt(version: $request->attributes->get('ver'));
        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), json: true);
    }

    #[Route('/{ver<v\d+>}/postman', name: self::POSTMAN_ROUTE_VER, methods: ["GET"], format: 'json')]
    #[Route('/postman', name: self::POSTMAN_ROUTE, defaults: ['ver' => Info::DEFAULT_VERSION], methods: ["GET"], format: 'json')]
    public function postmanAction(PostmanAdapter $postmanAdapter, Request $request): Response
    {
        $result = $postmanAdapter->adapt($request->attributes->get('ver'));
        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), json: true);
    }

}
