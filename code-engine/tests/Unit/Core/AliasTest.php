<?php

declare(strict_types=1);

namespace Tests\Unit\Core;
use PHPUnit\Framework\TestCase;
use App\Core\State;
use App\Core\Alias;
use Tests\Fixture\StateFixture;

class AliasTest extends TestCase 
{
    public function testGetKey() : void
    {
        $key = '$message.path';

        $alias = $this->getAlias( );

        $this->assertSame($key,$alias->find($key));
    }

    public function testGetPath() : void
    {
        $path = '/potato/89';

        $alias = $this->getAlias( [ 'path' => $path ] );

        $this->assertSame($path,$alias->find('$.message.path'));
    }

    public function testGetStatus() : void
    {
        $status = 401;

        $alias = $this->getAlias( [ 'status' => $status ] );

        $this->assertSame($status,$alias->find('$.message.status'));
    }

    public function testGetHeaders() : void
    {
        $headers = [ 'X-TEST' => 'testing','CONTENT-LENGTH' => '0' ];

        $alias = $this->getAlias( [ 'headers' => $headers ] );

        $this->assertSame($headers,$alias->find('$.message.headers'));
    }

    public function testGetQueryParams() : void
    {
        $key = 'user';
        $value = '56';
        $queryParams = [ $key => '56' ];

        $alias = $this->getAlias( [ 'queryParams'=> $queryParams ] );

        $this->assertSame($value,$alias->find('$.message.queryParam.'.$key));
    }

    public function testGetMemoryValue() : void
    {
        $key = 'user';
        $value = '56';

        $alias = $this->getAlias( [] , $key , $value);

        $this->assertSame($value,$alias->find('$.memory.'.$key));
    }

    public function testGetMemoryValueInArray() : void
    {
        $value = ['name' => 'yoli', 'phones' => [ 
            'main' => 123456,
            'others' => [654321,123]
        ]];

        $alias = $this->getAlias( [] , 'user' , $value);

        $this->assertSame(123456,$alias->find('$.memory.user.phones.main'));
        $this->assertSame([654321,123],$alias->find('$.memory.user.phones.others'));
        $this->assertSame(123,$alias->find('$.memory.user.phones.others.1'));
    }

    public function testMapGetValue() : void
    {
        $key = 'name';
        $value = 'yolanda';

        $alias = $this->getAlias( []);

        $this->assertSame($value,$alias->find('$.map.map-fixture.'.$key));
    }

    public function testMapRead() : void
    {
        $alias = $this->getAlias( []);

        $this->assertSame(['name' => 'yolanda', 'age' => 32],$alias->find('$.map.map-fixture'));
    }

    public function testMapGetValueInArray() : void
    {
        $alias = $this->getAlias( []);

        $this->assertSame(1989,$alias->find('$.map.map-recursive-fixture.age.number'));
        $this->assertSame("URSS",$alias->find('$.map.map-recursive-fixture.age.countries.0'));
        $this->assertSame(["main" => "Felipe"],
            $alias->find('$.map.map-recursive-fixture.age.president'));
        $this->assertSame("Felipe",
            $alias->find('$.map.map-recursive-fixture.age.president.main'));
    }

    public function testLocalhost() : void
    {
        $localhost = 'http://hefesto_nginx_1';
        $alias = $this->getAlias(['localhost' => $localhost]);

        $this->assertSame(
            $localhost,
            $alias->find('$.memory.hefesto-localhost')
        );
    }

    private function getAlias(
        array $config = [],
        string $memoryKey = '',
        $memoryValue = ''
    ) : Alias {
        $config['codePath'] = __DIR__.'/../../Fixture/';
        $state = StateFixture::get($config);
        $state->memory()->set($memoryKey,$memoryValue);
        return new Alias($state);
    }
}