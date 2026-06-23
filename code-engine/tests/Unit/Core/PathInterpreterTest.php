<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Core\PathInterpreter;

class PathInterpreterTest extends TestCase
{
    private $interpreter;
    private $actions;

    protected function setUp() : void
    {
        $this->interpreter = new PathInterpreter();
        $this->actions = [
                [   'GET','/api/engine' ]
            ,   [   'GET','/api/engine/{idEngine}' ]
            ,   [   'GET','/api/{idTest}/mark/{idMark}' ]
            ,   [   'PUT','/api/{idTest}/mark' ]
        ];
    }

    public static function foundProvider()
    {
        return [
            [
                'GET','/api/engine','GET','/api/engine', []
            ],
            [
                'GET','/api/engine/56','GET','/api/engine/{idEngine}',['idEngine'=>'56']
            ],
            [
                'GET','/api/54/mark/35','GET','/api/{idTest}/mark/{idMark}',['idTest'=>'54','idMark'=>'35']
            ],
            [
                'PUT','/api/54/mark','PUT','/api/{idTest}/mark',['idTest'=>'54']
            ]
        ];
    }

    public static function notFoundProvider()
    {
        return [
            [
                'PUT','/api/engine'
            ],
            [
                'POST','/api/engine/56',
            ],
            [
                'GET','/api/54/mark/35/api',
            ],
            [
                'POST','/api/54/mark',
            ]
        ];
    }

    public static function middlewareProvider()
    {
        return [
            'any verb and path' => ['GET','/api/engine'],
            'post verb' => ['POST','/api/54/mark'],
            'different path' => ['PUT','/some/random/path'],
            'root' => ['GET','/'],
        ];
    }

    #[DataProvider('foundProvider')]
    public function testPathFound(
        string $requestVerb,
        string $requestPath,
        string $definitionVerb,
        string $definitionPath,
        array $requestPathParams
    ) : void {
        $pathInfo = $this->interpreter->execute($requestVerb,$requestPath,$this->actions);
        $this->assertSame($definitionVerb,$pathInfo['DEFINITION_VERB']);
        $this->assertSame($definitionPath,$pathInfo['DEFINITION_PATH']);
        $this->assertSame($requestPathParams,$pathInfo['PATH_PARAMS']);
    }

    #[DataProvider('notFoundProvider')]
    public function testPathNotFound(
        string $requestVerb,
        string $requestPath
    ) : void {
        $pathInfo = $this->interpreter->execute($requestVerb,$requestPath,$this->actions);
        $this->assertNull($pathInfo);
    }

    #[DataProvider('middlewareProvider')]
    public function testMiddlewareFirstInList(
        string $requestVerb,
        string $requestPath
    ) : void {
        $pathInfo = $this->interpreter->execute($requestVerb,$requestPath,[
            ['ALL', '/requests']
        ]);
        $this->assertSame('ALL',$pathInfo['DEFINITION_VERB']);
        $this->assertSame('/requests',$pathInfo['DEFINITION_PATH']);
        $this->assertSame([],$pathInfo['PATH_PARAMS']);
    }

    public function testMiddlewareNotFirstInList() : void
    {
        $pathInfo = $this->interpreter->execute('DELETE','/api/engine',[
            ['GET', '/api/engine'],
            ['ALL', '/requests']
        ]);
        $this->assertSame('ALL',$pathInfo['DEFINITION_VERB']);
        $this->assertSame('/requests',$pathInfo['DEFINITION_PATH']);
        $this->assertSame([],$pathInfo['PATH_PARAMS']);
    }

    public function testEmptyActionsReturnsNull() : void
    {
        $pathInfo = $this->interpreter->execute('GET','/api/engine',[]);
        $this->assertNull($pathInfo);
    }

    public function testRootPathMatches() : void
    {
        $pathInfo = $this->interpreter->execute('GET','/',[
            ['GET', '/']
        ]);
        $this->assertSame('GET',$pathInfo['DEFINITION_VERB']);
        $this->assertSame('/',$pathInfo['DEFINITION_PATH']);
        $this->assertSame([],$pathInfo['PATH_PARAMS']);
    }

    public function testAllVerbMiddlewareOnlyMatchesRequestsPath() : void
    {
        $pathInfo = $this->interpreter->execute('GET','/api/engine',[
            ['ALL', '/users']
        ]);
        $this->assertNull($pathInfo);
    }

    public function testSameSegmentCountButStaticMismatch() : void
    {
        $pathInfo = $this->interpreter->execute('GET','/api/engine',[
            ['GET', '/api/test']
        ]);
        $this->assertNull($pathInfo);
    }

    public function testSameSegmentCountMismatchThenMatch() : void
    {
        $pathInfo = $this->interpreter->execute('GET','/api/engine/56',[
            ['GET', '/api/engine/{id}'],
            ['GET', '/api/test/56']
        ]);
        $this->assertSame('GET',$pathInfo['DEFINITION_VERB']);
        $this->assertSame('/api/engine/{id}',$pathInfo['DEFINITION_PATH']);
        $this->assertSame(['id'=>'56'],$pathInfo['PATH_PARAMS']);
    }

    public function testExplodeEmptyPathReturnsEmptyArray() : void
    {
        $pathInfo = $this->interpreter->execute('GET','',[
            ['GET', '/api/engine']
        ]);
        $this->assertNull($pathInfo);
    }
}