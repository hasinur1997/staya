<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MemberInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An emailed link that lets one person fill in the member form. Only the
 * SHA-256 hash of the token is stored, so a database leak cannot be used to
 * accept an invitation. A link works once, until `expires_at`.
 *
 * @property int $id
 * @property string $email
 * @property string $token_hash
 * @property int $invited_by
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable|null $revoked_at
 * @property int|null $member_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['email', 'token_hash', 'invited_by', 'expires_at'])]
#[Hidden(['token_hash'])]
class MemberInvitation extends Model
{
    /** @use HasFactory<MemberInvitationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /**
     * A new random token and its stored hash.
     *
     * @return array{token: string, hash: string}
     */
    public static function newToken(): array
    {
        $token = Str::random(48);

        return ['token' => $token, 'hash' => self::hashToken($token)];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        return self::query()->where('token_hash', self::hashToken($token))->first();
    }

    public function status(): InvitationStatus
    {
        return match (true) {
            $this->used_at !== null => InvitationStatus::Used,
            $this->revoked_at !== null => InvitationStatus::Revoked,
            $this->expires_at->isPast() => InvitationStatus::Expired,
            default => InvitationStatus::Pending,
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    /**
     * What goes into the audit log: never the token or its hash.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'email' => $this->email,
            'expires_at' => $this->expires_at->toIso8601String(),
            'status' => $this->status()->value,
        ];
    }
}
