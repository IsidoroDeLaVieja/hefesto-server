<?php

declare(strict_types=1);

namespace App\Adapters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;
use Throwable;
use PharData;

class Deploy {

    public static function execute(string $org, string $env, Request $request) : array 
    {
        $release = 'release'.uniqid();
        $sourceFolder = 'deploytemps/'.uniqid();
        $targetFolder = 'deploytemps/'.$release;
        
        try {
            self::validateFile($request);
            self::fileToSourceFolder($request,$sourceFolder);
            DeployMaps::execute($sourceFolder,$targetFolder,$env);
            DeployDirectives::execute($sourceFolder,$targetFolder,$release);
            self::deployAssets($sourceFolder,$targetFolder);
            $key = DeployApi::execute($sourceFolder,$targetFolder,$org,$env,$release);
            self::deploy($org,$env,$key,$release,$targetFolder,config('app.CODE_PATH'));
            Storage::deleteDirectory($sourceFolder);
            self::createStorage($org,$env,$key,config('app.STORAGE_PATH'));
            return [$release,$key];
        } catch (Throwable $e) {
            Storage::deleteDirectory($sourceFolder);
            Storage::deleteDirectory($targetFolder);
            throw $e;
        }
    }

    public static function cleanReleases(array $releases) : void 
    {
        exec('cd '.config('app.CODE_PATH').' && rm -R '.implode(' ',$releases));
    }

    private static function createStorage(string $organization,string $environment,string $key,string $storagePath) : void 
    {
        $path = $storagePath.$organization.'/'.$environment.'/'.$key.'/';
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }        
    }

    private static function deployAssets(string $sourceFolder,string $targetFolder) : void 
    {  
	    $sourceFolder = Storage::path($sourceFolder);    
	    $targetFolder = Storage::path($targetFolder);
	    exec("mv $sourceFolder/Assets $targetFolder");
    }

    private static function validateFile(Request $request) : void {
        if ( ! $request->hasFile('file') ) {
            throw new Exception('file is mandatory', 400);
        }
        
        if ( ! $request->file('file')->isValid() ) {
            throw new Exception('file is not valid', 400);
        }

        if ( $request->file->extension() !== 'gz' ) {
            throw new Exception('file should be gz', 400);
        }
    }

    private static function fileToSourceFolder(
        Request $request,
        string $sourceFolder
    ) : void {
        $nameFile = 'filename.tar';
        $fileGz = $request->file->storeAs($sourceFolder,$nameFile.'.gz');
        $pathTempFolder = Storage::path($sourceFolder);

        $p = new PharData($pathTempFolder.'/'.$nameFile.'.gz');
        $p->decompress(); 
        $phar = new PharData($pathTempFolder.'/'.$nameFile);
        $phar->extractTo($pathTempFolder.'/');
    }

    private static function deploy(
        string $org,
        string $env,
        string $key,
        string $release,
        string $source,
        string $target
    ) : void {
        $source = Storage::path($source);
        exec("mv $source $target");
        if ( !file_exists($target.'/'.$release.'/'.$release.'.php') ) {
            throw new Exception('moving release error', 500);
        }
    }
}
