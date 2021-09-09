<?php

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Tests\Fixture\MessageFixture;
use App\Core\Message;

class MessageTest extends TestCase
{
    private $message;
    private $path;
    private $verb ;
    private $body;
    private $headers;
    private $queryParams;
    private $pathParams;
    private $status;

    protected function setUp() : void
    {
        $this->path = '/company/users';
        $this->verb = 'GET';
        $this->body = '{"userId":34,"name":"pepito"}';
        $this->headers = [ 'X-CUSTOM-1' => 'a' , 'X-CUSTOM-2' => 'b' , 'CONTENT-LENGTH' => (string)strlen($this->body)];
        $this->queryParams = [ 'queryParam1' => 'a' , 'queryParam2' => 'b' ];
        $this->pathParams = [ 'pathParam1' => 'a' , 'pathParam2' => 'b' ];
        $this->status = 200;
        $this->message = MessageFixture::get([
            'path' => $this->path,
            'verb' => $this->verb,
            'body' => $this->body,
            'headers' => $this->headers,
            'queryParams' => $this->queryParams,
            'pathParams' => $this->pathParams,
            'status' => $this->status
        ]);
    }

    public function testGetHeaders() : void
    {
        $this->assertSame($this->headers,$this->message->getHeaders());
    }

    public function testDeleteHeaders() : void
    {
        $this->message->deleteHeaders();
        $this->assertSame([],$this->message->getHeaders());
    }

    public function testSetHeaderAndGetHeaderAndDeleteHeader() : void
    {
        $this->message->setHeader('X-Custom-Id','58');
        $this->assertSame('58',$this->message->getHeader('x-custom-id'));

        $this->message->deleteHeader('X-Custom-Id');
        $this->assertNull($this->message->getHeader('x-custom-id'));
    }

    public function testGetQueryParams() : void
    {
        $this->assertSame($this->queryParams,$this->message->getQueryParams());
    }

    public function testDeleteQueryParams() : void
    {
        $this->message->deleteQueryParams();
        $this->assertSame([],$this->message->getQueryParams());
    }

    public function testSetQueryParamsAndGetQueryParamsAndDeleteQueryParams() : void
    {
        $this->message->setQueryParam('CustomId','58');
        $this->assertSame('58',$this->message->getQueryParam('CustomId'));

        $this->message->deleteQueryParam('CustomId');
        $this->assertNull($this->message->getQueryParam('CustomId'));
    }

    public function testGetQueryParamsAsString() : void
    {
        $this->message->deleteQueryParams();
        
        $this->message->setQueryParam('CustomId1','customid1');
        $this->message->setQueryParam('CustomId2','customid2');
        
        $this->assertSame('?CustomId1=customid1&CustomId2=customid2',
                $this->message->getQueryParamAsString());
    }

    public function testGetQueryParamsAsVoidString() : void
    {
        $this->message->deleteQueryParams();
        $this->assertSame('',$this->message->getQueryParamAsString());
    }


    public function testGetPathParams() : void
    {
        $this->assertSame($this->pathParams,$this->message->getPathParams());
    }

    public function testDeletePathParams() : void
    {
        $this->message->deletePathParams();
        $this->assertSame([],$this->message->getPathParams());
    }

    public function testSetPathParamsAndGetPathParamsAndDeletePathParams() : void
    {
        $this->message->setPathParam('CustomId','58');
        $this->assertSame('58',$this->message->getPathParam('CustomId'));

        $this->message->deletePathParam('CustomId');
        $this->assertNull($this->message->getPathParam('CustomId'));
    }

    public function testSetBodyGetBody() : void
    {
        $this->assertSame($this->body,$this->message->getBody());

        $this->message->setBody('OtherBody');
        $this->assertSame('OtherBody',$this->message->getBody());
        $this->assertSame((string)strlen($this->message->getBody()),$this->message->getHeader('content-length'));
    }

    public function testGetBodyAsArray() : void
    {
        $this->message->setBody('{"name":"isidoro"}');
        $this->assertSame(['name'=>'isidoro'],$this->message->getBodyAsArray());

        $this->message->setBody('"name":"isidoro"}');
        $this->assertNull($this->message->getBodyAsArray());
    }

    public function testSetBodyAsArray() : void
    {
        $this->message->setBodyAsArray(['name'=>'isidoro']);
        $this->assertSame('{"name":"isidoro"}',$this->message->getBody());
    }

    public function testSetPathAndGetPath() : void
    {
        $this->assertSame($this->path,$this->message->getPath());
        $this->message->setPath('/home/isidoro');
        $this->assertSame('/home/isidoro',$this->message->getPath());
    }

    public function testSetPathWithQueryParam() : void
    {
        $this->message->setPath('/home/isidoro?CustomId=1&CustomId2=2');
        $this->assertSame('1',$this->message->getQueryParam('CustomId'));
        $this->assertSame('2',$this->message->getQueryParam('CustomId2'));
        $this->assertSame('/home/isidoro',$this->message->getPath());
    }

    public function testSetVerbAndGetVerb() : void 
    {
        $this->assertSame($this->verb,$this->message->getVerb());
        $this->message->setVerb('put');
        $this->assertSame('PUT',$this->message->getVerb());
    }

    public function testSetVerbWithInvalidMethod() : void
    {
        $this->expectException(\Exception::class);
        $this->message->setVerb('CustomId');
    }

    public function testSetStatusAndGetStatus() : void 
    {
        $this->assertSame($this->status,$this->message->getStatus());
        $this->message->setStatus(404);
        $this->assertSame(404,$this->message->getStatus());
    }
    
}
