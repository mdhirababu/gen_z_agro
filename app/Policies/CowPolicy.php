<?php

namespace App\Policies;

use App\Models\Cow;
use App\Models\Farm;
use App\Models\User;

class CowPolicy
{
    private function manages(User $user, Cow $cow): bool
    {
        return in_array($cow->farm->roleOf($user), [Farm::ROLE_OWNER, Farm::ROLE_MANAGER], true);
    }

    public function viewAny(User $user, Farm $farm): bool
    {
        return $farm->roleOf($user) !== null;
    }

    public function view(User $user, Cow $cow): bool
    {
        return $cow->farm->roleOf($user) !== null;
    }

    // স্টাফও নতুন গরু ও ওজন যোগ করতে পারবেন
    public function create(User $user, Farm $farm): bool
    {
        return $farm->roleOf($user) !== null;
    }

    public function addWeight(User $user, Cow $cow): bool
    {
        return $cow->farm->roleOf($user) !== null;
    }

    // বদলানো ও মোছা শুধু মালিক ও ম্যানেজার, মুছতে পারেন শুধু মালিক
    public function update(User $user, Cow $cow): bool
    {
        return $this->manages($user, $cow);
    }

    public function deleteWeight(User $user, Cow $cow): bool
    {
        return $this->manages($user, $cow);
    }

    public function delete(User $user, Cow $cow): bool
    {
        return $cow->farm->roleOf($user) === Farm::ROLE_OWNER;
    }
}
