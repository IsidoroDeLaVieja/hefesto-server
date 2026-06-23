<?php

declare(strict_types=1);

namespace Tests\Fixture;
use App\Core\State;
use App\Core\Groups;
use App\Core\ExecutionTimeMemory;
use Tests\Fixture\InMemoryMapRepository;

class StateFixture {

    public static function get(array $config) : State 
    {
        $groups = new Groups();
        $memory = new ExecutionTimeMemory();
        $mapRepository = new InMemoryMapRepository(
            isset($config['codePath']) ? $config['codePath'] : ''
        );

        $state = new State(
            MessageFixture::get($config),
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
            ],
            $groups,
            $memory,
            $mapRepository
        );

        $alias = new \App\Core\Alias($state);
        $state->setAlias($alias);

        return $state;
    }

}
