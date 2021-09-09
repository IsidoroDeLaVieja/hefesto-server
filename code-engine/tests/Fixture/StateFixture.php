<?php

declare(strict_types=1);

namespace Tests\Fixture;
use App\Core\State;
use App\Core\ExecutionTimeMemory;

class StateFixture {

    public static function get(array $config) : State 
    {
        return new State(MessageFixture::get($config),       
            [
                'organization' => 'isi-org',
                'environment' => 'isi-env',
                'id' => isset($config['id']) 
                    ? $config['id'] : 'test',
                'apiMemory' => new ExecutionTimeMemory(),
                'keyApi' => 'test',
                'codePath' => isset($config['codePath']) 
                    ? $config['codePath'] : '',
                'storagePath' => '',
                'localhost' => isset($config['localhost']) 
                    ? $config['localhost'] : 'http://hefesto_nginx_1',
                'definitionPath' => isset($config['definitionPath']) 
                    ? $config['definitionPath'] : '',
                'definitionVerb' => isset($config['verb']) 
                    ? $config['verb'] : ''
            ]
        );
    }

}