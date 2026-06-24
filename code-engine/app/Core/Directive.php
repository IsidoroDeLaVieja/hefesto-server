<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

abstract class Directive
{
    private ?State $originalState = null;
    private ?float $timeInit = null;

    final public function onInit(State $state): void
    {
        $this->originalState = unserialize(serialize($state));
        $this->timeInit = microtime(true);
    }

    final public function call(State $state, array $config, array $groups, int $order): void
    {
        $log = $state->isDirectiveDebug();

        if ($log) {
            $this->onInit($state);
        }

        $error = $this->executeWithGuard($state, $config, $groups);

        if ($log || $error !== false) {
            $this->onFinish($state, $order, $error);
        }
    }

    final public function onFinish(State $state, int $order, string|false $error): void
    {
        $output = [
            'id' => $state->id(),
            'directive' => $this->directiveName(),
            'order' => $order,
            'error' => $error,
        ];

        if ($state->isDirectiveDebug()) {
            $output['duration'] = round((microtime(true) - $this->timeInit) * 1000);

            $this->collectDebugDiffs($output, $state);
        }

        $state->addDebug($output);
    }

    final public function addDiffStateToOutput(
        array &$output,
        State $state,
        string $stateMethod,
        string $getMethod,
        string $name,
    ): void {
        $originalValue = $this->originalState?->{$stateMethod}()->{$getMethod}();
        $currentValue = $state->{$stateMethod}()->{$getMethod}();

        if ($originalValue !== $currentValue) {
            $output[$name] = [
                'from' => $originalValue,
                'to' => $currentValue,
            ];
        }
    }

    final public static function run(State $state, array $config): void
    {
        $class = get_called_class();
        (new $class)->execute($state, $config);
    }

    abstract protected function execute(State $state, array $config): void;

    private function executeWithGuard(State $state, array $config, array $groups): string|false
    {
        if (!$state->groups()->isAnyKeyEnabled($groups)) {
            return false;
        }

        try {
            $resolvedConfig = $this->resolveAliases($state, $config);
            $this->execute($state, $resolvedConfig);

            return false;
        } catch (Throwable $e) {
            $state->groups()->enable(Groups::ERROR_FLOW);

            return $e->getMessage();
        }
    }

    private function resolveAliases(State $state, array $config): array
    {
        foreach ($config as $key => $value) {
            $config[$key] = $state->alias($value);
        }

        return $config;
    }

    private function directiveName(): string
    {
        return static::class;
    }

    private function collectDebugDiffs(array &$output, State $state): void
    {
        $fields = [
            ['stateMethod' => 'groups', 'getMethod' => 'read', 'name' => 'groups'],
            ['stateMethod' => 'message', 'getMethod' => 'getPath', 'name' => 'path'],
            ['stateMethod' => 'message', 'getMethod' => 'getHeaders', 'name' => 'headers'],
            ['stateMethod' => 'message', 'getMethod' => 'getBody', 'name' => 'body'],
            ['stateMethod' => 'message', 'getMethod' => 'getStatus', 'name' => 'status'],
            ['stateMethod' => 'message', 'getMethod' => 'getQueryParams', 'name' => 'queryParams'],
            ['stateMethod' => 'memory', 'getMethod' => 'read', 'name' => 'memory'],
        ];

        foreach ($fields as $field) {
            $this->addDiffStateToOutput(
                $output,
                $state,
                $field['stateMethod'],
                $field['getMethod'],
                $field['name'],
            );
        }
    }
}