<?php

declare(strict_types=1);

namespace App\Adapters;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class DeployDirectives {

    public static function execute(        
        string $sourceFolder,
        string $targetFolder,
        string $release
    ) : void {
        $files = Storage::files($sourceFolder.'/Directives');
        self::validateList($files);
        self::compileList($files,$release,$targetFolder,config('app.API_NAMESPACE'));

        $compiledFiles = Storage::files($targetFolder.'/Directives');
        self::validateList($compiledFiles);
    }

    private static function validateList(array $files) : void 
    {
        if (count($files) < 1) {
            throw new Exception('0 directives in your api', 400);
        }
        foreach ($files as $file) {
            $code = Storage::get($file);
            $header = substr($code, 0, 27);
            if ($header !== '<?php /*dlv-code-engine***/') {
                throw new Exception('Directive '.basename($file).' should have <?php /*dlv-code-engine***/', 400);
            }
            $extension =  substr($file, -4);
            if ($extension !== '.php') {
                throw new Exception('your directive '.basename($file).' has no php extension', 400);
            }
            $checkSyntax = trim(exec('php -l '.Storage::path($file)));
            if ( ! Str::startsWith($checkSyntax, 'No syntax errors detected') ) {
                throw new Exception($checkSyntax,400);
            }
        }
    }

    private static function compileList(
        array $files,
        string $release,
        string $targetFolder,
        string $apiNamespace
    ) : void {
        foreach ($files as $file) {
            $code = Storage::get($file);
            $code = Str::replaceFirst(
                '<?php /*dlv-code-engine***/', 
                '', 
                $code
            );
            $directive = Str::replaceLast(
                '.php', 
                '', 
                basename($file)
            );
            $template = self::template($release,$directive,$code,$apiNamespace);
            Storage::put($targetFolder.'/Directives/'.basename($file),$template );
        }
    }

    private static function template(
        string $release,
        string $directive,
        string $code,
        string $apiNamespace
    ) : string {
        $template =  '<?php /*dlv-code-engine***/
        declare(strict_types=1);

        namespace #apiNamespace##release#\Directives;
        use App\Core\Directive;
        use App\Core\State;
        use Exception;

        class #directive# extends Directive {

            protected function execute(State $state, array $config) : void
            {
                #code#
            }
        }
        ';
        $template = str_replace('#apiNamespace#',$apiNamespace,$template);
        $template = str_replace('#release#',$release,$template);
        $template = str_replace('#directive#',$directive,$template);
        return str_replace('#code#',$code,$template);
    }
}