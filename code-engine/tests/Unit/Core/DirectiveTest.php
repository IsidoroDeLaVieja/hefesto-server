<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Tests\Fixture\StateFixture;
use Tests\Fixture\DirectiveFixture;
use App\Core\State;
use App\Core\Message;
use App\Core\Groups;
use App\Adapters\Log;

class DirectiveTest extends TestCase 
{
    private $directive;
    private $state;
    
    protected function setUp() : void
    {
        $this->directive = new DirectiveFixture();
        $this->state = StateFixture::get([]);
        $this->state->enableDirectiveDebug();
        $this->state->groups()->enable(Groups::NORMAL_FLOW);
    }

    public function testDirectiveModifyState() : void
    {
        $this->assertNull($this->state->message()->getHeader('x-header'));
        
        $this->directive->call($this->state, [
            'key-header' => 'x-header',
            'value-header' => 'isi-header'
        ],[Groups::NORMAL_FLOW],1);

        $this->assertSame('isi-header',$this->state->message()->getHeader('x-header'));
    }

    public function testDirectiveNotRunWithGroupDisabled() : void
    {
        $this->assertNull($this->state->message()->getHeader('x-header'));
        
        $this->state->groups()->disable(Groups::NORMAL_FLOW);
        $this->directive->call($this->state, [
            'key-header' => 'x-header',
            'value-header' => 'isi-header'
        ],[Groups::NORMAL_FLOW],1);

        $this->assertNull($this->state->message()->getHeader('x-header'));
    }

    public function testDirectiveThrowsErrorAndTriggersErrorFlow() : void
    {
        $this->assertTrue($this->state->groups()->isEnabled(Groups::NORMAL_FLOW));
        $this->assertFalse($this->state->groups()->isEnabled(Groups::ERROR_FLOW));

        $this->directive->call($this->state, [
            'key-header' => 'x-header-error',
            'value-header' => 'ok'
        ],[Groups::NORMAL_FLOW],1);

        $this->assertFalse($this->state->groups()->isEnabled(Groups::NORMAL_FLOW));
        $this->assertTrue($this->state->groups()->isEnabled(Groups::ERROR_FLOW));
    }

    public function testDirectiveDebugDurationAndPathAndHeaders() : void 
    {
        $from = $this->state->message()->getPath();
        $to = '/targetpath';

        $this->directive->call($this->state, [
            'key-header' => 'x-header',
            'value-header' => 'isi-header',
            'path' => $to
        ],[Groups::NORMAL_FLOW],34);

        $this->assertEquals($this->state->getDebug(),[[
            'id' => $this->state->id(),
            'directive' => 'Tests\Fixture\DirectiveFixture',
            'order' => 34,
            'duration' => 0,
            'error' => false,
            'path' => [ 'from' => $from , 'to' => $to ],
            'headers' => [ 'from' => ['CONTENT-LENGTH'=>'0'] , 'to' => ['CONTENT-LENGTH'=>'0','X-HEADER'=>'isi-header'] ]
        ]]);
    }

    public function testDirectiveError() : void 
    {
        $this->directive->call($this->state, [
            'key-header' => 'x-header-error',
            'value-header' => 'ok',
        ],[Groups::NORMAL_FLOW],2);

        $this->assertFalse($this->state->groups()->isEnabled(Groups::NORMAL_FLOW));
        $this->assertTrue($this->state->groups()->isEnabled(Groups::ERROR_FLOW));
        $this->assertEquals($this->state->getDebug(),[[
            'id' => $this->state->id(),
            'directive' => 'Tests\Fixture\DirectiveFixture',
            'order' => 2,
            'duration' => 0,
            'error' => 'An error',
            'groups' => [ 'from' => [Groups::NORMAL_FLOW] , 'to' => [Groups::ERROR_FLOW] ],
            'headers' => [ 'from' => ['CONTENT-LENGTH'=>'0'] , 'to' => ['CONTENT-LENGTH'=>'0','X-HEADER-ERROR'=>'ok'] ]
        ]]);
    }

    public function testDirectiveBodyStatusQueryParams() : void 
    {
        $fromBody = $this->state->message()->getBody();
        $toBody = 'anybody';

        $fromStatus = $this->state->message()->getStatus();
        $toStatus = 401;

        $this->directive->call($this->state, [
            'body' => $toBody,
            'status' => $toStatus,
            'key-query-param' => 'any-query-param',
            'value-query-param' => 'ok',
            'key-memory' => 'potato',
            'value-memory' => 'patata'
        ],[Groups::NORMAL_FLOW],3);

        $this->assertEquals($this->state->getDebug(),[[
            'id' => $this->state->id(),
            'directive' => 'Tests\Fixture\DirectiveFixture',
            'order' => 3,
            'duration' => 0,
            'error' => false,
            'headers' => [ 'from' => ['CONTENT-LENGTH'=>'0'], 'to' => ['CONTENT-LENGTH'=>'7']],
            'body' => [ 'from' => $fromBody , 'to' => $toBody ],
            'status' => [ 'from' => $fromStatus , 'to' => $toStatus ],
            'queryParams' => [ 'from' => [] , 'to' => ['any-query-param'=>'ok'] ],
            'memory' => [ 
                'from' => $this->getGlobalVariables() , 
                'to' => array_merge($this->getGlobalVariables(),['POTATO'=>'patata'])
            ]
        ]]);
    }

    public function testDirectiveAlias() : void 
    {
        $this->state->message()->setStatus(200);
        $key = 'my-key';
        $value = 'my-value';
        $this->state->message()->setHeader( $key , $value );

        $this->directive->call($this->state, [
            'key-query-param' => $key,
            'value-query-param' => '$.message.header.'.$key
        ],[Groups::NORMAL_FLOW],3);

        $this->assertSame($value,$this->state->message()->getQueryParam($key));
    }

    public function testDirectiveNoDebug() : void 
    {
        $this->state = StateFixture::get([]);
        $fromBody = $this->state->message()->getBody();
        $toBody = 'anybody';

        $fromStatus = $this->state->message()->getStatus();
        $toStatus = 401;

        $this->directive->call($this->state, [
            'body' => $toBody,
            'status' => $toStatus,
            'key-query-param' => 'any-query-param',
            'value-query-param' => 'ok',
            'key-memory' => 'potato',
            'value-memory' => 'patata'
        ],[Groups::NORMAL_FLOW],3);

        $this->assertEquals($this->state->getDebug(),[]);
    }

    private function getGlobalVariables() : array 
    {
        return [
            'HEFESTO-ORG' => 'isi-org',
            'HEFESTO-ENV' => 'isi-env',
            'HEFESTO-API' => 'test',
            'HEFESTO-LOCALHOST' => 'http://hefesto_nginx_1',
            'HEFESTO-PATHCODE' => '',
            'HEFESTO-PATHSTORAGE' => '',
            'HEFESTO-DEFINITIONPATH' => '',
            'HEFESTO-DEFINITIONVERB' => ''
        ];
    }

}