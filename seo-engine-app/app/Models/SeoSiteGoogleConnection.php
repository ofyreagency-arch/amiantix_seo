<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoSiteGoogleConnection extends Model
{
    protected $fillable = [
        'site_id',
        'connection_mode',
        'property_url',
        'property_label',
        'google_account_email',
        'refresh_token_encrypted',
        'access_token_expires_at',
        'credentials_path',
        'connection_status',
        'last_validated_at',
        'last_sync_at',
        'last_error',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'refresh_token_encrypted' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'last_validated_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(SeoSite::class, 'site_id', 'site_id');
    }
}
