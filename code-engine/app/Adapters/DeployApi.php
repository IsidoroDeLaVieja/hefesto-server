<?php

declare(strict_types=1);

namespace App\Adapters;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use SplDoublyLinkedList;
use App\Core\DirectiveRequest;
use Exception;
use Throwable;

class DeployApi {

    private const ALLOWED_VERBS = ['ALL','GET','POST','PUT','DELETE','PATCH','OPTIONS','HEADER'];

    public static function execute(        
        string $sourceFolder,
        string $targetFolder,
        string $org,
        string $env,
        string $release
    ) : string {
        $api = self::apiAsArray($sourceFolder);
        self::validate($api);
        $file = self::compile(
            $targetFolder,
            $org,
            $env,
            $release,
            isset($api['before']) ? $api['before'] : [],
            $api['endpoints'],
            isset($api['after']) ? $api['after'] : [],
            config('app.API_NAMESPACE')
        );
        self::validateSyntax($file);
        return $api['key'];
    }

    private static function apiAsArray(string $sourceFolder) : array 
    {
        $content = Storage::get($sourceFolder.'/api.yaml');
        if ( !$content ) {
            throw new Exception('api.yaml is not exist', 400);
        }
        try {
            $array = Yaml::parse($content);
        } catch ( Throwable $e ) {
            throw new Exception('api.yaml is not correct', 400);
        }
        return $array;
    }

    private static function validate(array $api) : void 
    {
        if ( ! Arr::exists($api, 'key') ) {
            throw new Exception('key not found in yaml', 400);
        }
        if ( !is_string($api['key']) ) {
            throw new Exception('key should be string in yaml', 400);
        }
        if ( ! Arr::exists($api, 'endpoints') ) {
            throw new Exception('endpoints not found in yaml', 400);
        }
        if ( !is_array($api['endpoints']) ) {
            throw new Exception('key should be string in yaml', 400);
        }
        if ( isset($api['before']) ) {
            self::validateDirectives($api['before']);
        }
        if ( isset($api['after']) ) {
            self::validateDirectives($api['after']);
        }
        foreach ($api['endpoints'] as $action => $endpoint) {
            list( $verb , $path ) = self::verbAndPathFromAction($action);
            if ( ! in_array($verb,self::ALLOWED_VERBS) ) {
                throw new Exception($verb.' not allowed in yaml', 400);
            }
            if ( ! Str::startsWith($path, '/') ) {
                throw new Exception($path.' should start with / in yaml', 400);
            }
            self::validateDirectives($endpoint);
        }
    }

    private static function compile(    
        string $targetFolder,    
        string $org,
        string $env,
        string $release,
        array $before,
        array $endpoints,
        array $after,
        string $apiNamespace
    ) : string {
        $directives = [];
        $actions = [];
        foreach ($endpoints as $action => $endpoint) {
            list( $verb , $path ) = self::verbAndPathFromAction($action);
            $directives[$verb.' '.$path] = new SplDoublyLinkedList();
            $actions[] = [$verb , $path];
            self::addDirectivesToList($before,$directives[$verb.' '.$path],$release,$apiNamespace);
            self::addDirectivesToList($endpoint,$directives[$verb.' '.$path],$release,$apiNamespace);
            self::addDirectivesToList($after,$directives[$verb.' '.$path],$release,$apiNamespace);
        }
        $template = self::template($release,$actions,$directives,$apiNamespace);
        $file = $targetFolder.'/'.$release.'.php';
        Storage::put($file,$template);
        return $file;
    }

    private static function validateSyntax($file) : void 
    {
        $checkSyntax = trim(exec('php -l '.Storage::path($file)));
        if ( ! Str::startsWith($checkSyntax, 'No syntax errors detected') ) {
            throw new Exception($checkSyntax,400);
        }
    }

    private static function directiveRequest(
        string $release,
        string $directiveId,
        array $config,
        string $apiNamespace
    ) : DirectiveRequest {
        $directiveName = $config['directive'];
        $groups = $config['groups'] ?? null;
        unset($config['directive']);
        unset($config['groups']);
        return new DirectiveRequest(
            $directiveId,
            $apiNamespace.$release.'\Directives\\'.$directiveName,
            $config,
            $groups
        );
    }

    private static function template(
        string $release,
        array $actions,
        array $directives,
        string $apiNamespace
    ) : string {
        $template = '<?php
        declare(strict_types=1);

        namespace #apiNamespace##release#;
        use App\Core\Api;
        use SplDoublyLinkedList;

        class #release# implements Api {

            private $actionsSerialized = \'#actions#\';
            private $directivesSerialized = \'#directives#\';

            public function actions() : array
            {
                return unserialize($this->actionsSerialized);
            }

            public function getDirectives(string $verb,string $definitionPath) : SplDoublyLinkedList
            {
                $directives = unserialize($this->directivesSerialized);
                return $directives[$verb.\' \'.$definitionPath];
            }
        }';
        $template = str_replace('#apiNamespace#',$apiNamespace,$template);
        $template = str_replace('#release#',$release,$template);
        $template = str_replace('#actions#',serialize($actions),$template);
        return str_replace('#directives#',serialize($directives),$template);
    }

    private static function verbAndPathFromAction(string $action) : array 
    {
        list( $verb , $path ) = explode(' ',$action);
        $verb = strtoupper($verb);
        return [$verb , $path];
    }

    private static function validateDirectives(array $directives) : void 
    {
        foreach ($directives as $keyDirective => $directive) {
            if ( !is_string($keyDirective) ) {
                throw new Exception('directive without key in yaml', 400);
            }
            if ( !is_array($directive) ) {
                throw new Exception($keyDirective.' is malformed', 400);
            }
            if ( !Arr::exists($directive, 'directive') ) {
                throw new Exception($keyDirective.' without directive tag', 400);
            }
            if ( !is_string($directive['directive']) ) {
                throw new Exception($keyDirective.' with malformed directive', 400);
            }
        }
    }

    private static function addDirectivesToList(
        array $directives,
        SplDoublyLinkedList $list,
        string $release,
        string $apiNamespace
    ) : void {
        foreach ($directives as $keyDirective => $directive) {
            $list->push(self::directiveRequest(
                $release,
                $keyDirective,
                $directive,
                $apiNamespace
            ));
        }
    }
}