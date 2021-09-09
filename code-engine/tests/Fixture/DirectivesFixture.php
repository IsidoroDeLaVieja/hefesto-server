<?php

declare(strict_types=1);

namespace Tests\Fixture;
use SplDoublyLinkedList;
use App\Core\DirectiveRequest;

class DirectivesFixture 
{
    public static function get(array $config) : SplDoublyLinkedList
    {
        $directives = new SplDoublyLinkedList();

        $number = 1;
        foreach($config as $directiveConfig) {
            $groups = isset($directiveConfig['groups']) ? $directiveConfig['groups'] : null;
            $directives->push(new DirectiveRequest(
                (string)$number,
                DirectiveFixture::class,
                $directiveConfig,
                $groups
            ));
            $number++;
        }

        return $directives;
    }
}