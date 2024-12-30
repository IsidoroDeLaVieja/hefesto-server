<?php

declare(strict_types=1);

namespace App\Core;

class PathInterpreter {

    private const VERB_MIDDLEWARE = 'ALL';
    private const PATH_MIDDLEWARE = '/requests';

    public function execute(string $requestVerb, string $requestPath, array $definitionActions) : ?array 
    {
        $requestPathParams = explode('/',$requestPath);
        $countRequestPathParams = count($requestPathParams);
        if ($countRequestPathParams === 0) {
            return null;
        }

        foreach($definitionActions as $action) {

            $definitionVerb = $action[0];
            $definitionPath = $action[1];

            if (self::VERB_MIDDLEWARE === $definitionVerb && self::PATH_MIDDLEWARE === $definitionPath) {
                return $this->buildResponse(self::VERB_MIDDLEWARE,self::PATH_MIDDLEWARE,[]);
            }
            if ($requestVerb !== $definitionVerb) {
                continue;
            }
            if ($requestPath === $definitionPath) {
                return $this->buildResponse($definitionVerb,$definitionPath,[]);
            }
            $definitionPathParams = explode('/',$definitionPath);
            if ($countRequestPathParams !== count($definitionPathParams)) {
                continue;
            }
            $pathParams = $this->getPathParams(
                $definitionPathParams,
                $requestPathParams
            );
            if (empty($pathParams)) {
                continue;
            }
            return $this->buildResponse($definitionVerb,$definitionPath,$pathParams);
        }
        return null;
    }

    private function buildResponse(string $definitionVerb, string $definitionPath, array $pathParams) : array 
    {
        return [
            'DEFINITION_VERB' => $definitionVerb,
            'DEFINITION_PATH' => $definitionPath,
            'PATH_PARAMS' => $pathParams,
        ];
    }

    private function getPathParams(
        array $definitionPathParams,
        array $requestPathParams
    ) : array {
        $pathParams = [];
        foreach($definitionPathParams as $i => $definitionPathParam) {
            if ($definitionPathParam === $requestPathParams[$i]) {
                continue;
            }
            if ( !$this->isParam($definitionPathParam) ) {
                return [];
            }
            $pathParams[trim($definitionPathParam,'{}')] = $requestPathParams[$i];
        }
        return $pathParams;
    }

    private function isParam(string $definitionPathParam) : bool 
    {
        if (empty($definitionPathParam)) {
            return false;
        }
        $chars = str_split($definitionPathParam);
        return  $chars[0] === '{' && $chars[count($chars) - 1] === '}';
    }
}