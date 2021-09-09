<?php

declare(strict_types=1);

namespace App\Core;
use DateTime;

class ApiStorage {

    private const MAX_RELEASES = 7;
    private $memory;

    public function __construct(Memory $memory) {
        $this->memory = $memory;
    }

    public function set(
        string $org,
        string $env, 
        string $keyApi, 
        string $release, 
        bool $active, 
        bool $public
    ) : array 
    {
        $releases = [];
        $createdAt = date(DateTime::ISO8601);

        $api = $this->find($org,$env,$keyApi);
        if ($api && $api['release'] !== $release) {
            $releases = array_diff($api['releases'],[$release]);
            $releases[] = $api['release'];
        }
        if ($api) {
            $createdAt = $api['created_at'];
        }

        $oldReleases = $releases;
        $releases = $this->cleanReleases($releases);

        $api = [
            'key' => $keyApi,
            'release' => $release,
            'active' => $active,
            'public' => $public,
            'created_at' => $createdAt,
            'updated_at' => date(DateTime::ISO8601),
            'releases' => $releases
        ];

        $this->memory->set(
            "org-$org-env-$env-key-$keyApi",
            $api
        );

        return array_diff($oldReleases,$releases);
    }

    public function find(string $org,string $env, string $keyApi) : ?array 
    {
        return $this->memory->get("org-$org-env-$env-key-$keyApi");
    }

    public function findAll(string $org,string $env) : array 
    {
        $allApis = $this->memory->read();
        $filter = [];
        foreach ($allApis as $api) {
            if (strpos(basename($api), "org-$org-env-$env-key-") === 0) {
                $filter[] = str_replace("org-$org-env-$env-key-",'',basename($api));
            }
        }
        return $filter;
    }

    private function cleanReleases(array $releases) : array 
    {
        if ( count($releases) <= self::MAX_RELEASES) {
            return $releases;
        }
        return array_slice($releases, -self::MAX_RELEASES);
    }
    
}