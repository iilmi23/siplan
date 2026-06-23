<?php

namespace App\Services\Utilities;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class PlanningCacheService
{
    private const VERSION_KEYS = [
        'summary' => 'planning:summary:version',
        'dashboard' => 'planning:dashboard:version',
    ];

    public function remember(string $scope, array $filters, \Closure $callback, int $minutes = 10): mixed
    {
        return Cache::remember($this->key($scope, $filters), now()->addMinutes($minutes), $callback);
    }

    public function invalidate(?string $scope = null): void
    {
        $scopes = $scope ? [$scope] : array_keys(self::VERSION_KEYS);

        foreach ($scopes as $cacheScope) {
            $key = self::VERSION_KEYS[$cacheScope] ?? null;

            if (! $key) {
                continue;
            }

            if (! Cache::has($key)) {
                Cache::forever($key, 1);
            }

            Cache::increment($key);
        }
    }

    private function key(string $scope, array $filters): string
    {
        $versionKey = self::VERSION_KEYS[$scope] ?? "planning:{$scope}:version";
        $version = Cache::get($versionKey, 1);
        $userId = Auth::id() ?: 'guest';

        return "planning:{$scope}:{$version}:{$userId}:".md5(json_encode($filters));
    }
}
