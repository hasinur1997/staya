<?php

namespace App\Providers;

use App\Actions\Reports\MonthlyReport;
use App\Models\BuyerPayable;
use App\Models\Deposit;
use App\Models\Fund;
use App\Models\GroceryPurchase;
use App\Models\LedgerEntry;
use App\Models\MailSetting;
use App\Models\MealType;
use App\Models\Member;
use App\Models\MemberInvitation;
use App\Models\MemberStay;
use App\Models\MessSetting;
use App\Models\MonthClosure;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\ReportPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureAuthMail();

        // An Admin's email settings (Settings → Email) override .env.
        MailSetting::applyToConfig();

        // Monthly figures are not a model; the policy is keyed by their class.
        Gate::policy(MonthlyReport::class, ReportPolicy::class);
    }

    /**
     * Password reset and verification emails use the same branded layout as
     * the invitation. They are written in the notifiable's preferred locale.
     */
    protected function configureAuthMail(): void
    {
        ResetPassword::toMailUsing(function (User $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject(__('Reset your password'))
                ->markdown('mail.reset-password', [
                    'url' => $url,
                    'expireMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                ]);
        });

        VerifyEmail::toMailUsing(fn (User $notifiable, string $url): MailMessage => (new MailMessage)
            ->subject(__('Verify your email address'))
            ->markdown('mail.verify-email', ['url' => $url]));
    }

    /**
     * Keep class names out of morph columns (`subject_type`, `causer_type`,
     * `model_type`, …). Every model used in a morph relation must be listed.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Role::class,
            'permission' => Permission::class,
            'mess_setting' => MessSetting::class,
            'mail_setting' => MailSetting::class,
            'fund' => Fund::class,
            'ledger_entry' => LedgerEntry::class,
            'month_closure' => MonthClosure::class,
            'member' => Member::class,
            'member_stay' => MemberStay::class,
            'meal_type' => MealType::class,
            'member_invitation' => MemberInvitation::class,
            'deposit' => Deposit::class,
            'grocery_purchase' => GroceryPurchase::class,
            'buyer_payable' => BuyerPayable::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
