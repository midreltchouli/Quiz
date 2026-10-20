<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quiz_questions_answered' => 'integer',
            'quiz_upgraded_at' => 'datetime',
            'quiz_checkout_amount' => 'integer',
            'quiz_notifications' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            /* @chisel-2fa */
            'two_factor_confirmed_at' => 'datetime',
            /* @end-chisel-2fa */
        ];
    }

    public function hasReachedFreeQuizLimit(): bool
    {
        return $this->quiz_upgraded_at === null
            && $this->quiz_questions_answered >= config('billing.free_question_limit');
    }

    public function interfaceFont(): string
    {
        return match ($this->font_family) {
            'arial' => 'Arial, sans-serif',
            'georgia' => 'Georgia, serif',
            'monospace' => 'monospace',
            default => 'system-ui, sans-serif',
        };
    }

    public function interfaceTextSize(): string
    {
        return match ($this->text_size) {
            'small' => '14px',
            'large' => '18px',
            default => '16px',
        };
    }
}
