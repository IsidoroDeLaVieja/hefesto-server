<?php

declare(strict_types=1);

namespace App\Core;

class PathInterpreter
{
    private const VERB_MIDDLEWARE = 'ALL';
    private const PATH_MIDDLEWARE = '/requests';

    public function execute(string $requestVerb, string $requestPath, array $definitionActions): ?array
    {
        if ($requestPath === '') {
            return null;
        }

        foreach ($definitionActions as $action) {
            $definitionVerb = $action[0];
            $definitionPath = $action[1];

            if ($this->matchesMiddleware($definitionVerb, $definitionPath)) {
                return $this->buildResponse(self::VERB_MIDDLEWARE, self::PATH_MIDDLEWARE, []);
            }

            if ($requestVerb !== $definitionVerb) {
                continue;
            }

            if ($requestPath === $definitionPath) {
                return $this->buildResponse($definitionVerb, $definitionPath, []);
            }

            $definitionParts = explode('/', $definitionPath);
            $requestParts = explode('/', $requestPath);

            if (count($definitionParts) !== count($requestParts)) {
                continue;
            }

            $pathParams = $this->extractPathParams($definitionParts, $requestParts);

            if ($pathParams !== []) {
                return $this->buildResponse($definitionVerb, $definitionPath, $pathParams);
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function extractPathParams(array $definitionParts, array $requestParts): array
    {
        $params = [];

        foreach ($definitionParts as $i => $definitionPart) {
            if ($definitionPart === $requestParts[$i]) {
                continue;
            }

            if (!$this->isParam($definitionPart)) {
                return [];
            }

            $params[trim($definitionPart, '{}')] = $requestParts[$i];
        }

        return $params;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponse(string $definitionVerb, string $definitionPath, array $pathParams): array
    {
        return [
            'DEFINITION_VERB' => $definitionVerb,
            'DEFINITION_PATH' => $definitionPath,
            'PATH_PARAMS' => $pathParams,
        ];
    }

    private function matchesMiddleware(string $verb, string $path): bool
    {
        return $verb === self::VERB_MIDDLEWARE && $path === self::PATH_MIDDLEWARE;
    }

    private function isParam(string $definitionPathParam): bool
    {
        return $definitionPathParam !== ''
            && $definitionPathParam[0] === '{'
            && $definitionPathParam[-1] === '}';
    }
}