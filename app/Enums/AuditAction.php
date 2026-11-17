<?php

namespace App\Enums;

/**
 * Every action written to the activity log (its `description`). Each case
 * must be logged somewhere in `app/` and every logged action must be a
 * case; the audit completeness test enforces both.
 */
enum AuditAction: string
{
    case AdjustmentPosted = 'adjustment_posted';
    case DepositSubmitted = 'deposit_submitted';
    case DepositRecorded = 'deposit_recorded';
    case DepositApproved = 'deposit_approved';
    case DepositRejected = 'deposit_rejected';
    case DepositCancelled = 'deposit_cancelled';
    case DepositReversed = 'deposit_reversed';
    case Lockout = 'lockout';
    case Login = 'login';
    case LoginBlocked = 'login_blocked';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case InvitationSent = 'invitation_sent';
    case InvitationRevoked = 'invitation_revoked';
    case InvitationAccepted = 'invitation_accepted';
    case MealsRecorded = 'meals_recorded';
    case MealTypeCreated = 'meal_type_created';
    case MealTypeUpdated = 'meal_type_updated';
    case MemberCreated = 'member_created';
    case MemberUpdated = 'member_updated';
    case MemberDeactivated = 'member_deactivated';
    case MemberReactivated = 'member_reactivated';
    case MemberDeleted = 'member_deleted';
    case MonthClosed = 'month_closed';
    case MonthReopened = 'month_reopened';
    case NidViewed = 'nid_viewed';
    case NidDownloaded = 'nid_downloaded';
    case OpeningBalancePosted = 'opening_balance_posted';
    case PayableReimbursed = 'payable_reimbursed';
    case PurchaseCreated = 'purchase_created';
    case PurchaseUpdated = 'purchase_updated';
    case PurchaseVoided = 'purchase_voided';
    case RegistrationSubmitted = 'registration_submitted';
    case RegistrationApproved = 'registration_approved';
    case RegistrationRejected = 'registration_rejected';
    case RoleCreated = 'role_created';
    case RoleUpdated = 'role_updated';
    case RoleDeleted = 'role_deleted';
    case SettingsUpdated = 'settings_updated';
    case UserCreated = 'user_created';
    case UserUpdated = 'user_updated';
    case UserDeactivated = 'user_deactivated';
    case UserReactivated = 'user_reactivated';

    public function label(): string
    {
        return match ($this) {
            self::AdjustmentPosted => __('Manual adjustment posted'),
            self::DepositSubmitted => __('Deposit submitted'),
            self::DepositRecorded => __('Deposit recorded for a member'),
            self::DepositApproved => __('Deposit approved'),
            self::DepositRejected => __('Deposit rejected'),
            self::DepositCancelled => __('Deposit cancelled'),
            self::DepositReversed => __('Deposit reversed'),
            self::Lockout => __('Login locked out'),
            self::Login => __('Signed in'),
            self::LoginBlocked => __('Sign-in blocked'),
            self::LoginFailed => __('Sign-in failed'),
            self::Logout => __('Signed out'),
            self::InvitationSent => __('Invitation sent'),
            self::InvitationRevoked => __('Invitation cancelled'),
            self::InvitationAccepted => __('Invitation accepted'),
            self::MealsRecorded => __('Meals recorded'),
            self::MealTypeCreated => __('Meal type created'),
            self::MealTypeUpdated => __('Meal type updated'),
            self::MemberCreated => __('Member created'),
            self::MemberUpdated => __('Member updated'),
            self::MemberDeactivated => __('Member deactivated'),
            self::MemberReactivated => __('Member reactivated'),
            self::MemberDeleted => __('Member deleted'),
            self::MonthClosed => __('Month closed'),
            self::MonthReopened => __('Month reopened'),
            self::NidViewed => __('NID viewed'),
            self::NidDownloaded => __('NID downloaded'),
            self::OpeningBalancePosted => __('Opening balance posted'),
            self::PayableReimbursed => __('Payable reimbursed'),
            self::PurchaseCreated => __('Purchase recorded'),
            self::PurchaseUpdated => __('Purchase edited'),
            self::PurchaseVoided => __('Purchase deleted (voided)'),
            self::RegistrationSubmitted => __('Registration submitted'),
            self::RegistrationApproved => __('Registration approved'),
            self::RegistrationRejected => __('Registration rejected'),
            self::RoleCreated => __('Role created'),
            self::RoleUpdated => __('Role updated'),
            self::RoleDeleted => __('Role deleted'),
            self::SettingsUpdated => __('Settings changed'),
            self::UserCreated => __('User created'),
            self::UserUpdated => __('User updated'),
            self::UserDeactivated => __('User deactivated'),
            self::UserReactivated => __('User reactivated'),
        };
    }

    /**
     * A readable name for a stored description, including ones this
     * version no longer knows.
     */
    public static function labelFor(string $description): string
    {
        return self::tryFrom($description)?->label() ?? str($description)->replace('_', ' ')->ucfirst()->toString();
    }
}
