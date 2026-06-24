<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

class ApiStorage
{
    private const MAX_RELEASES = 7;

    public function __construct(
        private readonly Memory $memory
    ) {}

    public function set(
        string $org,
        string $env,
        string $keyApi,
        string $release,
        bool $active,
        bool $public
    ): array {
        $now = new DateTimeImmutable();
        $createdAt = $now->format(DateTimeImmutable::ATOM);

        $existingApi = $this->find($org, $env, $keyApi);

        $previousReleases = $this->resolvePreviousReleases($existingApi, $release);

        if ($existingApi !== null) {
            $createdAt = $existingApi['created_at'];
        }

        $cleanedReleases = $this->cleanReleases($previousReleases);

        $this->memory->set(
            "org-$org-env-$env-key-$keyApi",
            [
                'key' => $keyApi,
                'release' => $release,
                'active' => $active,
                'public' => $public,
                'created_at' => $createdAt,
                'updated_at' => $now->format(DateTimeImmutable::ATOM),
                'releases' => $cleanedReleases,
            ]
        );

        return array_diff($previousReleases, $cleanedReleases);
    }

    public function find(string $org, string $env, string $keyApi): ?array
    {
        return $this->memory->get("org-$org-env-$env-key-$keyApi");
    }

    public function findAll(string $org, string $env): array
    {
        $prefix = "org-$org-env-$env-key-";
        $result = [];

        foreach ($this->memory->read() as $path) {
            $key = basename((string) $path);
            if (str_starts_with($key, $prefix)) {
                $result[] = substr($key, strlen($prefix));
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|null $existingApi
     * @return string[]
     */
    private function resolvePreviousReleases(?array $existingApi, string $newRelease): array
    {
        if ($existingApi === null || $existingApi['release'] === $newRelease) {
            return [];
        }

        $releases = array_diff($existingApi['releases'], [$newRelease]);
        $releases[] = $existingApi['release'];

        return array_values($releases);
    }

    /**
     * @param string[] $releases
     * @return string[]
     */
    private function cleanReleases(array $releases): array
    {
        if (count($releases) <= self::MAX_RELEASES) {
            return $releases;
        }

        return array_slice($releases, -self::MAX_RELEASES);
    }
}