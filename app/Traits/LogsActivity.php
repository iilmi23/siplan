<?php

namespace App\Traits;

use App\Models\HistoryLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait LogsActivity
{
    /**
     * When true, all model event logging is suppressed globally.
     * Use withoutLogging() to wrap bulk operations (e.g. SIREP sync).
     */
    protected static bool $suppressLogging = false;

    /**
     * Run a callback without recording any activity logs.
     * Automatically restores logging state after the callback, even on exception.
     *
     * Usage:
     *   LogsActivity::withoutLogging(fn () => $service->syncAll());
     */
    public static function withoutLogging(callable $callback): mixed
    {
        $previous = static::$suppressLogging;
        static::$suppressLogging = true;

        try {
            return $callback();
        } finally {
            static::$suppressLogging = $previous;
        }
    }

    protected static function bootLogsActivity(): void
    {
        // NOTE: static::registerModelEvent() is a concrete method on Eloquent\Model.
        // Intelephense reports these as "undefined" because it cannot resolve static::
        // calls inside traits. These warnings are false positives — the code is correct.
        // See: @mixin \Illuminate\Database\Eloquent\Model on this trait.

        static::registerModelEvent('created', static function (Model $model): void { // @phpstan-ignore-line
            if (static::$suppressLogging) return;
            static::logModelActivity($model, 'created');
        });

        static::registerModelEvent('updated', static function (Model $model): void { // @phpstan-ignore-line
            if (static::$suppressLogging) return;
            static::logModelActivity($model, 'updated');
        });

        static::registerModelEvent('deleted', static function (Model $model): void { // @phpstan-ignore-line
            if (static::$suppressLogging) return;
            static::logModelActivity($model, 'deleted');
        });
    }

    protected static function logModelActivity(Model $model, string $event)
    {
        try {
            $modelName = class_basename($model);

            // Format dynamic titles and activity types
            // E.g., SRMappingTemplate -> SR Mapping Template
            $spacedName = preg_replace('/(?<!^)[A-Z]/', ' $0', $modelName);
            $title = "{$spacedName} " . ucfirst($event);

            // Snake case activity type
            $activityType = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modelName)) . "_{$event}";

            $changes = null;
            if ($event === 'updated') {
                $dirty = $model->getDirty();
                $changes = [];
                foreach ($dirty as $key => $value) {
                    if (in_array($key, ['updated_at', 'password', 'remember_token'])) continue;
                    $changes[$key] = [
                        'old' => $model->getOriginal($key),
                        'new' => $value,
                    ];
                }
                if (empty($changes)) {
                    return; // No meaningful changes, skip logging
                }
            } elseif ($event === 'created') {
                $changes = $model->attributesToArray();
                unset($changes['created_at'], $changes['updated_at'], $changes['password'], $changes['remember_token']);
            } elseif ($event === 'deleted') {
                $changes = $model->attributesToArray();
                unset($changes['created_at'], $changes['updated_at'], $changes['password'], $changes['remember_token']);
            }

            // Identify a descriptive label/identifier for the model
            $identifier = '-';
            if (isset($model->name)) {
                $identifier = $model->name;
            } elseif (isset($model->code)) {
                $identifier = $model->code;
            } elseif (isset($model->username)) {
                $identifier = $model->username;
            } elseif (isset($model->assy_number)) {
                $identifier = $model->assy_number;
            } elseif (isset($model->email)) {
                $identifier = $model->email;
            } elseif ($modelName === 'ProductionWeek') {
                $identifier = "Week " . ($model->week_no ?? '-') . " (" . ($model->year ?? '-') . ")";
            } elseif ($modelName === 'EtdMapping') {
                $dateVal = $model->etd_date;
                $dateStr = $dateVal instanceof \DateTimeInterface
                    ? $dateVal->format('Y-m-d')
                    : (is_string($dateVal) ? $dateVal : json_encode($dateVal));
                $identifier = "ETD " . $dateStr;
            } elseif (isset($model->id)) {
                $identifier = "#" . $model->id;
            }

            // Resolve customer code if applicable
            $customerCode = null;
            if (isset($model->customer_code)) {
                $customerCode = $model->customer_code;
            } else {
                try {
                    if (method_exists($model, 'customer') && $model->customer) {
                        $customerCode = $model->customer->code ?? null;
                    }
                } catch (\Throwable $e) {
                    // Safe fall-through
                }
            }

            // Resolve port name if applicable
            $portName = null;
            if (isset($model->port_name)) {
                $portName = $model->port_name;
            } else {
                try {
                    if (method_exists($model, 'port') && $model->port) {
                        $portName = $model->port->name ?? null;
                    }
                } catch (\Throwable $e) {
                    // Safe fall-through
                }
            }

            $actorName = Auth::check() ? Auth::user()->name : 'System';
            $notes = "{$spacedName} '{$identifier}' was {$event} by {$actorName}";

            HistoryLog::create([
                'user_id'       => Auth::id(),
                'activity_type' => $activityType,
                'title'         => $title,
                'status'        => 'completed',
                'customer_code' => $customerCode,
                'port_name'     => $portName,
                'notes'         => $notes,
                'details'       => ['changes' => $changes],
                'related_id'    => $model->getKey(),
            ]);
        } catch (\Throwable $e) {
            // Avoid failing the model operation if logging fails, but log it to Laravel logs
            Log::error("Failed to log activity for model in LogsActivity trait: " . $e->getMessage());
        }
    }
}
