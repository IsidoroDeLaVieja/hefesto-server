<?php

declare(strict_types=1);

namespace App\Core;
use SplDoublyLinkedList;

interface Api {

    public function actions() : array;

    public function getDirectives(string $verb,string $definitionPath) : SplDoublyLinkedList;
}