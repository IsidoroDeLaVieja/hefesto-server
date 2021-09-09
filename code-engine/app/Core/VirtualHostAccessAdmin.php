<?php

declare(strict_types=1);

namespace App\Core;
use Exception;

class VirtualHostAccessAdmin {

    private $virtualHostStorage;

    public function __construct(VirtualHostStorage $virtualHostStorage) 
    {
        $this->virtualHostStorage = $virtualHostStorage;
    }

    public function get(
        string $hostAdmin, 
        string $hostPublic, 
        string $key,
        bool $checkKey
    ) : array {
        $admin = $this->virtualHostStorage->getAdmin($hostAdmin);
        if (!$admin) {
            throw new Exception('admin does not exist');
        }
        $public = $this->virtualHostStorage->getPublic($hostPublic);
        if (!$public) {
            throw new Exception('public does not exist');
        }
        if ($checkKey && $public['KEY'] !== $key) {
            throw new Exception('key is not correct');
        }
        return [
            'ORG' => $hostPublic,
            'TYPE' => 'ADMIN',
            'ENV' => $public['ENV'] ,
            'PATH' => $public['PATH']
        ];
    }
}