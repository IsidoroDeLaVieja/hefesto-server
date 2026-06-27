<?php

declare(strict_types=1);

namespace App\Adapters;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;
use Throwable;
use PharData;
use App\Adapters\Contracts\DeployMapsInterface;
use App\Adapters\Contracts\DeployDirectivesInterface;
use App\Adapters\Contracts\DeployApiInterface;

class Deploy
{
    private readonly DeployMapsInterface $deployMaps;
    private readonly DeployDirectivesInterface $deployDirectives;
    private readonly DeployApiInterface $deployApi;

    public function __construct(
        DeployMapsInterface $deployMaps,
        DeployDirectivesInterface $deployDirectives,
        DeployApiInterface $deployApi
    ) {
        $this->deployMaps = $deployMaps;
        $this->deployDirectives = $deployDirectives;
        $this->deployApi = $deployApi;
    }

    public static function execute(string $org, string $env, Request $request): array
    {
        return (new self(
            new DeployMaps(),
            new DeployDirectives(),
            new DeployApi()
        ))->instanceExecute($org, $env, $request);
    }

    public function instanceExecute(string $org, string $env, Request $request): array
    {
        $release = 'release' . uniqid();
        $sourceFolder = 'deploytemps/' . uniqid();
        $targetFolder = 'deploytemps/' . $release;

        try {
            self::validateFile($request);
            self::fileToSourceFolder($request, $sourceFolder);
            $this->deployMaps->execute($sourceFolder, $targetFolder, $env);
            $this->deployDirectives->execute($sourceFolder, $targetFolder, $release);
            self::deployAssets($sourceFolder, $targetFolder);
            $key = $this->deployApi->execute($sourceFolder, $targetFolder, $org, $env, $release);
            self::deployFromStorage($targetFolder, config('app.CODE_PATH'));
            Storage::deleteDirectory($sourceFolder);
            self::createStorage($org, $env, $key, config('app.STORAGE_PATH'));

            return [$release, $key];
        } catch (Throwable $e) {
            Storage::deleteDirectory($sourceFolder);
            Storage::deleteDirectory($targetFolder);

            throw $e;
        }
    }

    public static function cleanReleases(array $releases): void
    {
        $escaped = array_map(escapeshellarg(...), $releases);
        exec('cd ' . config('app.CODE_PATH') . ' && rm -R ' . implode(' ', $escaped));
    }

    public function instanceCleanReleases(array $releases): void
    {
        self::cleanReleases($releases);
    }

    private static function createStorage(string $organization, string $environment, string $key, string $storagePath): void
    {
        $path = $storagePath . $organization . '/' . $environment . '/' . $key . '/';

        if (!file_exists($path)) {
            mkdir($path, 0o755, true);
        }
    }

    private static function deployAssets(string $sourceFolder, string $targetFolder): void
    {
        $resolvedSource = Storage::path($sourceFolder);
        $resolvedTarget = Storage::path($targetFolder);

        exec("mv {$resolvedSource}/Assets {$resolvedTarget}");
    }

    private static function validateFile(Request $request): void
    {
        if (!$request->hasFile('file')) {
            throw new Exception('file is mandatory', 400);
        }

        if (!$request->file('file')->isValid()) {
            throw new Exception('file is not valid', 400);
        }

        if ($request->file->extension() !== 'gz') {
            throw new Exception('file should be gz', 400);
        }
    }

    private static function fileToSourceFolder(Request $request, string $sourceFolder): void
    {
        $nameFile = 'filename.tar';
        $request->file->storeAs($sourceFolder, $nameFile . '.gz');
        $pathTempFolder = Storage::path($sourceFolder);

        $pharData = new PharData($pathTempFolder . '/' . $nameFile . '.gz');
        $pharData->decompress();

        $pharArchive = new PharData($pathTempFolder . '/' . $nameFile);
        $pharArchive->extractTo($pathTempFolder . '/');
    }

    private static function deployFromStorage(string $folderFromStorage, string $codePath): void
    {
        $resolvedSource = Storage::path($folderFromStorage);
        $releaseName = basename($folderFromStorage);

        exec("mv {$resolvedSource} {$codePath}");

        if (!file_exists($codePath . '/' . $releaseName . '/' . $releaseName . '.php')) {
            throw new Exception('moving release error', 500);
        }
    }
}