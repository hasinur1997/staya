<?php

namespace App\Enums;

/**
 * Where a member stands (`members.status`). Pending and Rejected are
 * applicants with no access; Inactive members left and are read-only.
 */
enum MemberStatus: string
{
    case Pending = 'pending';
    case Rejected = 'rejected';
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Rejected => __('Rejected'),
            self::Active => __('Active'),
            self::Inactive => __('Inactive'),
        };
    }

    /**
     * Whether this is an applicant whose registration was never approved.
     */
    public function isApplicant(): bool
    {
        return $this === self::Pending || $this === self::Rejected;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
