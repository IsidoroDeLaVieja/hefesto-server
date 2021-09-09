<?php

declare(strict_types=1);

namespace Tests\Fixture;
use App\Core\Message;

class MessageFixture {

    public static function get(array $config) : Message 
    {
        return new Message(
            isset($config['verb']) ? $config['verb'] : 'POST',
            isset($config['path']) ? $config['path'] : '/company/users/2',
            isset($config['headers']) ? $config['headers'] : [],
            isset($config['body']) ? $config['body'] : '',
            isset($config['queryParams']) ? $config['queryParams'] : [],
            isset($config['pathParams']) ? $config['pathParams'] : [],
            isset($config['status']) ? $config['status'] : 200
        );
    }

}