<?php

declare(strict_types=1);

namespace App\Core;
use Exception;

class VirtualHostStorage {

    private const KEY_VIRTUAL_HOSTS = 'HEFESTO_VIRTUAL_HOSTS';
    private const ID_PUBLIC = 'PUBLIC';
    private const ID_ADMIN = 'ADMIN';
    private $memory;
    private $virtualHosts;

    public function __construct(Memory $memory) 
    {
        $this->memory = $memory;
        
        $virtualHosts = $this->memory->get(self::KEY_VIRTUAL_HOSTS);
        $this->virtualHosts = $virtualHosts ? $virtualHosts : [];
    }

    public function setAdmin(string $host) : void 
    {
        if ( $this->getPublic($host) ) {
            throw new Exception("can't add host, it's public");
        }
        $this->set($host,[
            'ORG' => $host,
            'TYPE' => self::ID_ADMIN
        ]);
    }

    public function setPublic(
        string $host,string $key,string $env,string $path
    ) : void {
        if ( $this->getAdmin($host) ) {
            throw new Exception("can't add host, it's admin");
        }
        $this->set($host,[
            'ORG' => $host,
            'TYPE' => self::ID_PUBLIC,
            'KEY' => $key,
            'ENV' => $env,
            'PATH' => $path
        ]);
    }

    public function delete(string $host) : void 
    {
        if (isset($this->virtualHosts[$host])) {
            unset($this->virtualHosts[$host]);
        }
        $this->memory->set(self::KEY_VIRTUAL_HOSTS,$this->virtualHosts);
    }

    public function getAdmin(string $host) : ?array 
    {
        return $this->get($host,self::ID_ADMIN);
    }

    public function getPublic(string $host) : ?array 
    {
        return $this->get($host,self::ID_PUBLIC);
    }

    public function read() : array 
    {
        return array_keys($this->virtualHosts);
    }

    private function set(string $key,array $value) : void 
    {
        $this->virtualHosts[$key] = $value;
        $this->memory->set(self::KEY_VIRTUAL_HOSTS,$this->virtualHosts);
    }

    private function get(string $host,string $type) : ?array 
    {
        if ( isset($this->virtualHosts[$host]) 
            && $this->virtualHosts[$host]['TYPE'] === $type
        ) {
            return $this->virtualHosts[$host];
        }
        return null;
    }
    
}