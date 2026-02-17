<?php

namespace Ufo\RestBundle\DocAdapters\Outputs\Postman\Blocks;

use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Header;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Server;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Method as RpcMethod;

use function array_merge;

class Method extends RpcMethod
{
    /**
     * @param string $name
     * @param string $httpMethod
     * @param string $description
     * @param Header[] $headers
     * @param Server $url
     */
    public function __construct(
        string $name,
        readonly public string $httpMethod,
        string $description,
        array $headers,
        Server $url,
    )
    {
        parent::__construct($name, $description, $headers, $url);
    }

    protected function getRpcSignature(): array
    {
        $params = [];
        foreach ($this->params as $param) {
            $params = array_merge($params, $this->paramConvert($param));
        }
        return $params;
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['request']['method'] = $this->httpMethod;
        $path = preg_replace('/\{([^}]+)\}/', ':$1', $this->name);
        $data['request']['url'] .= $path;
        return $data;
    }

}
