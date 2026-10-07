<?php

namespace App\Modules\Identity\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deactivating and deleting a staff sign-in account. Shared by the Staff
 * directory and User Roles & Permissions so both act on the account the
 * same way — a staff member deactivated from one page is deactivated
 * everywhere, not just relabelled on one list.
 */
class UserAccounts
{
    /**
     * Only an Organization Owner/Admin (or a platform admin) may do this,
     * never to their own account (an admin locking themselves out has no
     * way back in), and only an Owner may act on another Owner — otherwise
     * an Admin could remove the people above them.
     */
    public static function authorize(User $actor, User $target): void
    {
        abort_unless($actor->hasAnyRole(AdministrationRoles::ALLOWED), 403);
        abort_unless($actor->ownsTenant($target->tenant_id), 403);
        abort_if($actor->is($target), 422, 'You cannot deactivate or delete your own account.');

        if ($target->hasRole('Organization Owner') && ! $actor->isPlatformAdmin()) {
            abort_unless($actor->hasRole('Organization Owner'), 403, 'Only an Organization Owner can do this to another Owner.');
        }
    }

    /**
     * Blocks sign-in (AuthController::login) and ends any session already
     * open, so a deactivated carer is out straight away rather than at
     * their next login.
     */
    public static function deactivate(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->update(['status' => 'inactive']);
            $user->staffProfile?->update(['employment_status' => 'inactive']);
            static::endSessions($user);
        });
    }

    public static function reactivate(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->update(['status' => 'active']);
            $user->staffProfile?->update(['employment_status' => 'active']);
        });
    }

    /**
     * Removes the account and its HR file (staff profile and HR documents).
     * The user row itself is soft deleted, not erased: care notes, MAR
     * entries, incidents and visits they recorded reference it, and several
     * of those (care_notes.author_id among them) cascade on delete — erasing
     * the row would erase clients' care records with it. The email is
     * released so the same person can be added again later.
     */
    public static function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            if ($profile = $user->staffProfile) {
                foreach ($profile->documents as $document) {
                    Storage::disk('local')->delete($document->path);
                    $document->delete();
                }
                $profile->delete();
            }

            static::endSessions($user);
            $user->forceFill([
                'status' => 'deleted',
                'email' => "deleted-{$user->id}-".$user->email,
            ])->save();
            $user->delete();
        });
    }

    protected static function endSessions(User $user): void
    {
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
