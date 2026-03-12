<?php

declare(strict_types=1);

namespace App\Models;

use App\Service\Oid4vp\SdJwt\SdJwtParser;
use App\Service\Oid4vp\SdJwt\SdJwtToken;
use Database\Factories\SdJwtCredentialFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SdJwtCredential extends Model
{
    /** @use HasFactory<SdJwtCredentialFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'raw_sd_jwt',
        'issuer_claims',
        'disclosed_claims',
        'issuer',
        'vct',
    ];

    protected function casts(): array
    {
        return [
            'issuer_claims' => 'array',
            'disclosed_claims' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parseSdJwt(): SdJwtToken
    {
        return (new SdJwtParser)->parse($this->raw_sd_jwt);
    }
}
