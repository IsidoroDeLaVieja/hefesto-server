<?php

declare(strict_types=1);

namespace App\Core;

class Message
{
    public const ALLOWED_VERBS = ['GET', 'POST', 'OPTIONS', 'DELETE', 'PATCH', 'PUT', 'HEAD'];

    private string $path;
    private string $verb;
    private string $body;
    private int $status;
    private array $headers = [];
    private array $queryParams;
    private array $pathParams;

    public function __construct(
        string $verb,
        string $path,
        array $headers,
        string $body,
        array $queryParams,
        array $pathParams,
        int $status,
    ) {
        $this->setVerb($verb);
        $this->setPath($path);

        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value);
        }

        $this->setBody($body);
        $this->queryParams = $queryParams;
        $this->pathParams = $pathParams;
        $this->status = $status;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function deleteHeaders(): void
    {
        $this->headers = [];
    }

    public function deleteHeader(string $key): void
    {
        unset($this->headers[strtoupper($key)]);
    }

    public function setHeader(string $key, string $value): void
    {
        $this->headers[strtoupper($key)] = $value;
    }

    public function getHeader(string $key): ?string
    {
        return $this->headers[strtoupper($key)] ?? null;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function deleteQueryParam(string $key): void
    {
        unset($this->queryParams[$key]);
    }

    public function deleteQueryParams(): void
    {
        $this->queryParams = [];
    }

    public function setQueryParam(string $key, string $value): void
    {
        $this->queryParams[$key] = $value;
    }

    public function getQueryParam(string $key): ?string
    {
        return $this->queryParams[$key] ?? null;
    }

    public function getQueryParamAsString(): string
    {
        if ($this->queryParams === []) {
            return '';
        }

        return '?' . http_build_query($this->queryParams);
    }

    public function getPathParams(): array
    {
        return $this->pathParams;
    }

    public function deletePathParam(string $key): void
    {
        unset($this->pathParams[$key]);
    }

    public function deletePathParams(): void
    {
        $this->pathParams = [];
    }

    public function setPathParam(string $key, string $value): void
    {
        $this->pathParams[$key] = $value;
    }

    public function getPathParam(string $key): ?string
    {
        return $this->pathParams[$key] ?? null;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): void
    {
        $this->setHeader('Content-Length', (string) strlen($body));
        $this->body = $body;
    }

    public function getBodyAsArray(): ?array
    {
        $data = json_decode($this->body, true);

        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }

    public function setBodyAsArray(array $body): void
    {
        $this->setBody(json_encode($body));
    }

    public function setPath(string $path): void
    {
        $parts = explode('?', $path);
        $count = count($parts);

        if ($count > 2) {
            throw new \InvalidArgumentException("path {$path} is not correct");
        }

        if ($count === 2) {
            $this->deleteQueryParams();
            parse_str($parts[1], $queryParams);

            foreach ($queryParams as $key => $value) {
                $this->setQueryParam($key, $value);
            }
        }

        $this->path = $parts[0];
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setVerb(string $verb): void
    {
        $upperVerb = strtoupper($verb);

        if (!in_array($upperVerb, self::ALLOWED_VERBS, true)) {
            throw new \InvalidArgumentException("verb {$verb} is not allowed");
        }

        $this->verb = $upperVerb;
    }

    public function getVerb(): string
    {
        return $this->verb;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}