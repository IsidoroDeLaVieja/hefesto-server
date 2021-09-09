<?php

declare(strict_types=1);

namespace App\Adapters;
use Illuminate\Support\Facades\Storage;
use Exception;
use Throwable;

class DeployMaps {

    public static function execute(        
        string $sourceFolder,
        string $targetFolder,
        string $env
    ) : void {
        $files = array_merge(
            Storage::files($sourceFolder.'/Maps'),
            Storage::files($sourceFolder.'/Maps/'.$env)
        );
        self::validateList($files);
        self::copyList($files,$targetFolder);
    }

    private static function validateList(array $files) : void 
    {
        foreach ($files as $file) {
            $extension =  substr($file, -5);
            if ($extension !== '.json') {
                throw new Exception('your map '.basename($file).' is not json', 400);
            }
            $map = json_decode(Storage::get($file),true);
            if ( !is_array($map) ) {
                throw new Exception('your map '.basename($file).' is badly formed', 400);
            }
        }
    }

    private static function copyList(array $files,string $targetFolder) : void 
    {
        foreach ($files as $file) {
            Storage::copy($file, $targetFolder.'/Maps/'.basename($file));
        }
    }
}