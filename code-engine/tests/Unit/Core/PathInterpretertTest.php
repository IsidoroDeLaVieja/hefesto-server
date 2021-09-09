<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
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

    public function foundProvider()
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

    public function notFoundProvider()
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

    /**
     * @dataProvider foundProvider
     */
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

    /**
     * @dataProvider notFoundProvider
     */
    public function testPathNotFound(
        string $requestVerb,
        string $requestPath
    ) : void {
        $pathInfo = $this->interpreter->execute($requestVerb,$requestPath,$this->actions);
        $this->assertNull($pathInfo);
    }

    /**
     * @dataProvider notFoundProvider
     */
    public function testMiddleware(
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
}