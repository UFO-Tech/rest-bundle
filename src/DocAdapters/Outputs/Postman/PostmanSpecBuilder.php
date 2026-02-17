<?php

namespace Ufo\RestBundle\DocAdapters\Outputs\Postman;

use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\Blocks\Header;
use Ufo\JsonRpcBundle\DocAdapters\Outputs\Postman\PostmanSpecBuilder as RPCPostmanSpecBuilder;
use Ufo\RestBundle\DocAdapters\Outputs\Postman\Blocks\Method;

class PostmanSpecBuilder extends RPCPostmanSpecBuilder
{

    public function buildMethod(string $name, string $description, array $headers = [], string $folder = '', string $httpMethod = 'GET'): Method
    {
        $headers = array_map(fn(array $header) => new Header($header['key'], $header['value']), $headers);
        $method = new Method(
            $name,
            $httpMethod,
            $description,
            $headers,
            $this->server
        );

        if ($folder) {
            $this->postmanSpecFiller->addToFolder($folder, $method);
        } else {
            $this->postmanSpecFiller->addMethod($method);
        }

        return $method;
    }
}
