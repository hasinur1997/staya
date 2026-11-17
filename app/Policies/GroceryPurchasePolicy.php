<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\GroceryPurchase;
use App\Models\Member;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GroceryPurchasePolicy
{
    /**
     * Determine whether the user can browse grocery purchases.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::GroceryView->value);
    }

    /**
     * Determine whether the user can view a purchase. Every member sees
     * every purchase.
     */
    public function view(User $actor, GroceryPurchase $purchase): bool
    {
        return $actor->can(Permission::GroceryView->value);
    }

    /**
     * Determine whether the user can record a purchase they made
     * themselves. Only an active member may; inactive members are read-only.
     */
    public function create(User $actor): Response
    {
        if (! $actor->can(Permission::GroceryCreate->value)) {
            return Response::deny();
        }

        return $actor->isActiveMember()
            ? Response::allow()
            : Response::deny(__('Only active members can record purchases.'));
    }

    /**
     * Determine whether the user can record purchases with another member
     * as the buyer.
     */
    public function createForOthers(User $actor): bool
    {
        return $actor->can(Permission::GroceryCreateForOthers->value);
    }

    /**
     * Determine whether the user can record a purchase with this buyer:
     * themselves through `create`, anyone else with
     * `grocery.create_for_others`.
     */
    public function createFor(User $actor, Member $buyer): Response
    {
        if ($buyer->user_id === $actor->id) {
            return $this->create($actor);
        }

        return $this->createForOthers($actor) ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can use the form helpers (item names,
     * live totals): anyone who can record or edit a purchase.
     */
    public function draft(User $actor): bool
    {
        return $actor->canAny([
            Permission::GroceryCreate->value,
            Permission::GroceryCreateForOthers->value,
            Permission::GroceryUpdateOwn->value,
            Permission::GroceryUpdateAny->value,
        ]);
    }

    /**
     * Determine whether the user can edit the purchase: anyone with
     * `grocery.update_any`, or its creator inside the edit window.
     */
    public function update(User $actor, GroceryPurchase $purchase): Response
    {
        return $this->change($actor, $purchase, Permission::GroceryUpdateAny, Permission::GroceryUpdateOwn);
    }

    /**
     * Determine whether the user can void the purchase: anyone with
     * `grocery.delete_any`, or its creator inside the edit window.
     */
    public function delete(User $actor, GroceryPurchase $purchase): Response
    {
        return $this->change($actor, $purchase, Permission::GroceryDeleteAny, Permission::GroceryDeleteOwn);
    }

    /**
     * Whether a void by this user must come with a reason. Only the owner
     * voiding their own entry inside the edit window is spared.
     */
    public function voidNeedsReason(User $actor, GroceryPurchase $purchase): bool
    {
        return ! ($actor->can(Permission::GroceryDeleteOwn->value)
            && $purchase->isOwnedBy($actor)
            && $purchase->isWithinEditWindow());
    }

    /**
     * Whether the purchase shows as Locked to the user: it is theirs, the
     * window has closed and they hold no wider permission to change it.
     */
    public function isLockedFor(User $actor, GroceryPurchase $purchase): bool
    {
        return ! $purchase->isVoided()
            && $purchase->isOwnedBy($actor)
            && ! $purchase->isWithinEditWindow()
            && $this->update($actor, $purchase)->denied()
            && $this->delete($actor, $purchase)->denied();
    }

    /**
     * The edit window runs from the server's `created_at` and is checked
     * against the server clock.
     */
    private function change(User $actor, GroceryPurchase $purchase, Permission $any, Permission $own): Response
    {
        if (! $actor->can($any->value) && ! ($actor->can($own->value) && $purchase->isOwnedBy($actor))) {
            return Response::deny();
        }

        if ($purchase->isVoided()) {
            return Response::deny(__('This purchase has been voided.'));
        }

        if ($actor->can($any->value) || $purchase->isWithinEditWindow()) {
            return Response::allow();
        }

        return Response::deny(__('The edit window for this purchase has closed.'));
    }
}
