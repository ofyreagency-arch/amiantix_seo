<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemoteInstallation extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONNECTING = 'connecting';
    public const STATUS_DETECTING = 'detecting_environment';
    public const STATUS_INSTALLING = 'installing';
    public const STATUS_CONFIGURING = 'configuring';
    public const STATUS_ACTIVATING = 'activating';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'site_id',
        'status',
        'current_step',
        'progress',
        'hosting_provider',
        'connection_type',
        'encrypted_credentials',
        'connection_metadata',
        'detected_framework',
        'detected_php_version',
        'detected_composer',
        'logs_json',
        'error_message',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_credentials' => 'encrypted:array',
            'connection_metadata' => 'array',
            'logs_json' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(SeoSite::class, 'site_id', 'site_id');
    }

    public function markProgress(string $status, string $step, int $progress, ?string $message = null): void
    {
        $logs = $this->logs_json ?? [];
        $logs[] = [
            'at' => now()->toIso8601String(),
            'level' => $status === self::STATUS_FAILED ? 'error' : 'info',
            'step' => $step,
            'message' => $message ?? $step,
        ];

        $attributes = [
            'status' => $status,
            'current_step' => $step,
            'progress' => max(0, min(100, $progress)),
            'logs_json' => array_slice($logs, -60),
        ];

        if ($status !== self::STATUS_PENDING && $this->started_at === null) {
            $attributes['started_at'] = now();
        }

        if ($status === self::STATUS_COMPLETED) {
            $attributes['completed_at'] = now();
            $attributes['failed_at'] = null;
            $attributes['error_message'] = null;
        }

        if ($status === self::STATUS_FAILED) {
            $attributes['failed_at'] = now();
            $attributes['completed_at'] = null;
            $attributes['error_message'] = $message;
        }

        $this->forceFill($attributes)->save();
    }

    public function safeLogs(): array
    {
        return collect($this->logs_json ?? [])
            ->map(fn (mixed $entry): array => [
                'at' => (string) data_get($entry, 'at', ''),
                'level' => (string) data_get($entry, 'level', 'info'),
                'step' => (string) data_get($entry, 'step', ''),
                'message' => (string) data_get($entry, 'message', ''),
            ])
            ->values()
            ->all();
    }
}
