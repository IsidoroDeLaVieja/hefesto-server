<?php

declare(strict_types=1);

namespace App\Adapters;

use Illuminate\Support\Facades\Storage;
use Exception;
use App\Adapters\Contracts\DeployDirectivesInterface;

class DeployDirectives implements DeployDirectivesInterface
{
    private const DIRECTIVES_DIR = '/Directives';
    private const REQUIRED_HEADER = '<?php /*dlv-code-engine***/';
    private const HEADER_LENGTH = 27;

    public function execute(
        string $sourceFolder,
        string $targetFolder,
        string $release
    ): void {
        self::staticExecute($sourceFolder, $targetFolder, $release);
    }

    public static function staticExecute(
        string $sourceFolder,
        string $targetFolder,
        string $release
    ): void {
        $files = self::collectFiles($sourceFolder . self::DIRECTIVES_DIR);
        self::validateList($files);
        self::compileList($files, $release, $targetFolder, config('app.API_NAMESPACE'));

        $compiledFiles = self::collectFiles($targetFolder . self::DIRECTIVES_DIR);
        self::validateList($compiledFiles);
    }

    private static function collectFiles(string $directory): array
    {
        return Storage::files($directory);
    }

    private static function validateList(array $files): void
    {
        if (count($files) < 1) {
            throw new Exception('0 directives in your api', 400);
        }

        foreach ($files as $file) {
            self::validateHeader($file);
            self::validateExtension($file);
            self::validateSyntax($file);
        }
    }

    private static function validateHeader(string $file): void
    {
        $code = Storage::get($file);
        $header = substr($code, 0, self::HEADER_LENGTH);

        if ($header !== self::REQUIRED_HEADER) {
            throw new Exception(
                'Directive ' . basename($file) . ' should have ' . self::REQUIRED_HEADER,
                400
            );
        }
    }

    private static function validateExtension(string $file): void
    {
        if (!str_ends_with($file, '.php')) {
            throw new Exception(
                'your directive ' . basename($file) . ' has no php extension',
                400
            );
        }
    }

    private static function validateSyntax(string $file): void
    {
        $checkSyntax = trim(exec('php -l ' . Storage::path($file)));

        if (!str_starts_with($checkSyntax, 'No syntax errors detected')) {
            throw new Exception($checkSyntax, 400);
        }
    }

    private static function compileList(
        array $files,
        string $release,
        string $targetFolder,
        string $apiNamespace
    ): void {
        foreach ($files as $file) {
            $code = Storage::get($file);
            $code = str_replace(self::REQUIRED_HEADER, '', $code);
            $directive = basename($file, '.php');
            $template = self::template($release, $directive, $code, $apiNamespace);
            Storage::put($targetFolder . self::DIRECTIVES_DIR . '/' . basename($file), $template);
        }
    }

    private static function template(
        string $release,
        string $directive,
        string $code,
        string $apiNamespace
    ): string {
        $template = '<?php /*dlv-code-engine***/
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

        return str_replace(
            ['#apiNamespace#', '#release#', '#directive#', '#code#'],
            [$apiNamespace, $release, $directive, $code],
            $template
        );
    }
}