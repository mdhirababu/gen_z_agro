<?php

namespace App\Policies;

use App\Models\Farm;
use App\Models\User;

class FarmPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Farm $farm): bool
    {
        return $farm->roleOf($user) !== null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Farm $farm): bool
    {
        return $farm->roleOf($user) === Farm::ROLE_OWNER;
    }

    public function delete(User $user, Farm $farm): bool
    {
        return $farm->roleOf($user) === Farm::ROLE_OWNER;
    }

    public function manageMembers(User $user, Farm $farm): bool
    {
        return $farm->roleOf($user) === Farm::ROLE_OWNER;
    }

    public function manageBilling(User $user, Farm $farm): bool
    {
        return $farm->roleOf($user) === Farm::ROLE_OWNER;
    }

    public function manageFinance(User $user, Farm $farm): bool
    {
        return in_array($farm->roleOf($user), [Farm::ROLE_OWNER, Farm::ROLE_MANAGER], true);
    }

    /** স্বাস্থ্য রেকর্ড বদলানো ও মোছা: মালিক ও ম্যানেজার */
    public function manageHealth(User $user, Farm $farm): bool
    {
        return in_array($farm->roleOf($user), [Farm::ROLE_OWNER, Farm::ROLE_MANAGER], true);
    }

    /** খাবারের ধরন বদলানো ও রেকর্ড মোছা: মালিক ও ম্যানেজার */
    public function manageFeed(User $user, Farm $farm): bool
    {
        return in_array($farm->roleOf($user), [Farm::ROLE_OWNER, Farm::ROLE_MANAGER], true);
    }

    /** SMS বিজ্ঞপ্তির সেটিং: শুধু মালিক (খরচ হয় বলে) */
    public function manageNotifications(User $user, Farm $farm): bool
    {
        return $farm->roleOf($user) === Farm::ROLE_OWNER;
    }

    /** অডিট লগ দেখা: শুধু মালিক */
    public function viewAudit(User $user, Farm $farm): bool
    {
        return $farm->roleOf($user) === Farm::ROLE_OWNER;
    }

    /** খামারের সব ডেটা নামানো: শুধু মালিক */
    public function exportData(User $user, Farm $farm): bool
    {
        return $farm->roleOf($user) === Farm::ROLE_OWNER;
    }
}
