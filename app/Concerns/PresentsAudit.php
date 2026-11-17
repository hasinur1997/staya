<?php

namespace App\Concerns;

use App\Enums\AuditAction;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * How an activity-log row is sent to the frontend. Times are ISO 8601 UTC
 * (the page shows them in Asia/Dhaka); the actor is the causer's name, or
 * null for events with no signed-in user such as a failed login.
 */
trait PresentsAudit
{
    /**
     * @return array<string, mixed>
     */
    protected function auditRow(Activity $activity): array
    {
        $changes = $this->changesOf($activity);

        return [
            'id' => $activity->id,
            'created_at' => $activity->created_at?->toIso8601String(),
            'log_name' => $activity->log_name,
            'action' => $activity->description,
            'action_label' => AuditAction::labelFor($activity->description),
            'actor_id' => $activity->causer_id,
            'actor_name' => $activity->causer instanceof User ? $activity->causer->name : null,
            'subject_type' => $activity->subject_type,
            'subject_type_label' => $this->subjectLabel($activity->subject_type),
            'subject_id' => $activity->subject_id,
            'ip_address' => $activity->ip_address,
            'email_tried' => $activity->properties['email_tried'] ?? null,
            'has_changes' => $changes !== [],
            'self_approved' => ($changes['attributes']['is_self_approved'] ?? false) === true && $activity->description === 'deposit_approved',
        ];
    }

    /**
     * Field-by-field before and after values.
     *
     * @return list<array{field: string, old: string|null, new: string|null}>
     */
    protected function auditDiff(Activity $activity): array
    {
        $changes = $this->changesOf($activity);
        $old = is_array($changes['old'] ?? null) ? $changes['old'] : [];
        $new = is_array($changes['attributes'] ?? null) ? $changes['attributes'] : [];
        $diff = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            $diff[] = [
                'field' => (string) $field,
                'old' => array_key_exists($field, $old) ? $this->display($old[$field]) : null,
                'new' => array_key_exists($field, $new) ? $this->display($new[$field]) : null,
            ];
        }

        return $diff;
    }

    /**
     * The history panel on a detail page: null unless the viewer holds
     * `audit.view`, so the panel is simply absent for everyone else.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function auditHistory(Model $subject, User $viewer): ?array
    {
        if (! $viewer->can('viewAny', Activity::class)) {
            return null;
        }

        return Activity::query()
            ->with('causer:id,name')
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Activity $activity): array => $this->auditRow($activity))
            ->all();
    }

    protected function subjectLabel(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return match ($type) {
            'user' => __('User'),
            'role' => __('Role'),
            'permission' => __('Permission'),
            'mess_setting' => __('Settings'),
            'fund' => __('Fund'),
            'ledger_entry' => __('Ledger entry'),
            'month_closure' => __('Month'),
            'member' => __('Member'),
            'member_stay' => __('Stay'),
            'member_invitation' => __('Invitation'),
            'deposit' => __('Deposit'),
            'grocery_purchase' => __('Grocery purchase'),
            'buyer_payable' => __('Payable'),
            default => Str::headline($type),
        };
    }

    /**
     * The page a subject lives on, when it has one the viewer may open.
     */
    protected function subjectUrl(?string $type, ?int $id, User $viewer): ?string
    {
        if ($type === null || $id === null) {
            return null;
        }

        $class = Relation::getMorphedModel($type);
        $subject = $class !== null ? $class::query()->find($id) : null;

        return match (true) {
            $subject === null => null,
            $type === 'grocery_purchase' && $viewer->can('view', $subject) => route('grocery.show', $subject),
            $type === 'deposit' && $viewer->can('view', $subject) => route('deposits.show', $subject),
            $type === 'member' && $viewer->can('view', $subject) => route('members.show', $subject),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function changesOf(Activity $activity): array
    {
        return collect($activity->attribute_changes)->toArray();
    }

    private function display(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null,
        };
    }
}
