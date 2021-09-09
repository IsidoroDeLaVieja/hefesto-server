<?php

declare(strict_types=1);

namespace App\Core;

class Message {

    private $path;
    private $verb ;
    private $body;
    private $headers = [];
    private $queryParams;
    private $pathParams;
    private $status;
    public const ALLOWED_VERBS = ['GET','POST','OPTIONS','DELETE','PATCH','PUT','HEAD'];

    public function __construct(
        string $verb, 
        string $path,
        array $headers,
        string $body,
        array $queryParams,
        array $pathParams,
        int $status
    ) {
        $this->setVerb($verb);
        $this->setPath($path);
        foreach ($headers as $key => $value) {
            $this->setHeader($key,$value);
        }
        $this->setBody($body);
        $this->queryParams = $queryParams;
        $this->pathParams = $pathParams;
        $this->status = $status;
    }

    public function getHeaders() : array
    {
        return $this->headers;
    }

    public function deleteHeaders() : void
    {
        $this->headers = [];
    }

    public function deleteHeader(string $key) : void
    {
        if ($this->getHeader($key)) {
            unset($this->headers[strtoupper($key)]);
        }
    }

    public function setHeader(string $key,string $value) : void
    {
        $this->headers[strtoupper($key)] = $value;
    }

    public function getHeader(string $key) : ?string
    {
        if (!isset($this->headers[strtoupper($key)])) {
            return null;
        }
        return $this->headers[strtoupper($key)];
    }

    public function getQueryParams() : array
    {
        return $this->queryParams;
    }


    public function deleteQueryParam(string $key) : void
    {
        if ($this->getQueryParam($key)) {
            unset($this->queryParams[$key]);
        }
    }

    public function deleteQueryParams() : void
    {
        $this->queryParams = [];
    }

    public function setQueryParam(string $key,string $value) : void
    {
        $this->queryParams[$key] = $value;
    }

    public function getQueryParam(string $key) : ?string
    {
        if (!isset($this->queryParams[$key])) {
            return null;
        }
        return $this->queryParams[$key];
    }

    public function getQueryParamAsString() : string
    {
	if (empty($this->queryParams)) {
	    return '';
	}    
	return '?'.http_build_query($this->queryParams);    
    }

    public function getPathParams() : array
    {
        return $this->pathParams;
    }


    public function deletePathParam(string $key) : void
    {
        if ($this->getPathParam($key)) {
            unset($this->pathParams[$key]);
        }
    }

    public function deletePathParams() : void
    {
        $this->pathParams = [];
    }

    public function setPathParam(string $key,string $value) : void
    {
        $this->pathParams[$key] = $value;
    }

    public function getPathParam(string $key) : ?string
    {
        if (!isset($this->pathParams[$key])) {
            return null;
        }
        return $this->pathParams[$key];
    }

    public function getBody() : string 
    {
        return $this->body;
    }

    public function setBody(string $body) : void  
    {
        $this->setHeader('Content-Length',(string)strlen($body));
        $this->body = $body;
    }

    public function getBodyAsArray() : ?array 
    {
        try {
            return json_decode($this->body, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setBodyAsArray(array $body) : void 
    {
        $this->setBody(json_encode($body));
    }

    public function setPath(string $path) : void 
    {
        $countDelimiter = count(explode('?',$path));
        if ($countDelimiter > 2) {
            throw new \Exception('path '.$path.' is not correct');
        }
        if ($countDelimiter === 2) {
            $this->deleteQueryParams();
            $parts = explode('?',$path);
            $path = $parts[0];
            parse_str($parts[1],$queryParams);
            foreach($queryParams as $key => $value) {
                $this->setQueryParam($key,$value);
            }
        }
        $this->path = $path;
    }

    public function getPath() : string 
    {
        return $this->path;
    }

    public function setVerb(string $verb) : void 
    {
        $verb = strtoupper($verb);
        if ( !in_array($verb, self::ALLOWED_VERBS) ) {
            throw new \Exception('verb '.$verb.' is not allowed');
        }
        $this->verb = $verb;
    }

    public function getVerb() : string 
    {
        return $this->verb;
    }

    public function setStatus(int $status) : void 
    {
        $this->status = $status;
    }

    public function getStatus() : int 
    {
        return $this->status;
    }
}
