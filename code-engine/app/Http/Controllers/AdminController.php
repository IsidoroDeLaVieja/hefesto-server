<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Adapters\ApiMemoryFactory;
use App\Adapters\Deploy;
use App\Core\ApiStorage;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class AdminController extends Controller
{
    private const VALID_ERROR_CODES = [400, 401, 403, 404];

    public function __construct(
        private readonly ApiStorage $apiStorage,
        private readonly ApiMemoryFactory $apiMemoryFactory,
        private readonly Deploy $deploy = new Deploy(
            new \App\Adapters\DeployMaps(),
            new \App\Adapters\DeployDirectives(),
            new \App\Adapters\DeployApi(),
        ),
    ) {}

    public function postApi(Request $request): JsonResponse|Response
    {
        try {
            [$release, $key] = $this->deploy->instanceExecute(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $request,
            );

            $api = $this->apiStorage->find(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $key,
            );

            $cleanedReleases = $this->apiStorage->set(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $key,
                $release,
                true,
                $api['public'] ?? false,
            );

            $this->clearApiMemory(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $key,
            );

            $this->deploy->instanceCleanReleases($cleanedReleases);

            return response()->json([
                'key' => $key,
                'release' => $release,
            ]);
        } catch (Throwable $e) {
            return response($e->getMessage(), $this->resolveCode($e));
        }
    }

    public function getApi(Request $request): JsonResponse
    {
        try {
            $api = $this->apiStorage->find(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $request->key,
            );

            if ($api === null) {
                throw new Exception('api not found', 404);
            }

            return response()->json($api);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function getApis(Request $request): JsonResponse
    {
        try {
            $apis = $this->apiStorage->findAll(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
            );

            return response()->json($apis);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function putApi(Request $request): JsonResponse|Response
    {
        try {
            $this->validatePutRequest($request);

            $api = $this->apiStorage->find(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $request->key,
            );

            if ($api === null) {
                throw new Exception('key not found', 404);
            }

            $release = $request->input('release');

            if ($release !== $api['release'] && !in_array($release, $api['releases'], true)) {
                throw new Exception('release not found', 404);
            }

            $this->apiStorage->set(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $request->key,
                $release,
                $request->input('active'),
                $request->input('public'),
            );

            $this->clearApiMemory(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $request->key,
            );

            return response(null, 204);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    private function handleException(Throwable $e): JsonResponse
    {
        return response()->json(
            ['message' => $e->getMessage()],
            $this->resolveCode($e),
        );
    }

    private function resolveCode(Throwable $e): int
    {
        $code = (int) $e->getCode();

        return in_array($code, self::VALID_ERROR_CODES, true) ? $code : 500;
    }

    private function clearApiMemory(string $org, string $env, string $key): void
    {
        $apiMemory = $this->apiMemoryFactory->make($org, $env, $key);
        $apiMemory->set('api-maps', null);
    }

    private function validatePutRequest(Request $request): void
    {
        $active = $request->input('active');
        $release = $request->input('release');
        $public = $request->input('public');

        if (!is_bool($active)) {
            throw new Exception('active is mandatory, it should be bool', 400);
        }

        if (!is_string($release)) {
            throw new Exception('release is mandatory, it should be string', 400);
        }

        if (!is_bool($public)) {
            throw new Exception('public is mandatory, it should be bool', 400);
        }
    }
}