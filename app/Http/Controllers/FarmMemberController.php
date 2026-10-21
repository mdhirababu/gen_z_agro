<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FarmMemberController extends Controller
{
    public function index(Farm $farm)
    {
        Gate::authorize('manageMembers', $farm);

        $members = $farm->users()->get();

        return view('farms.members', compact('farm', 'members'));
    }

    public function store(Request $request, Farm $farm)
    {
        Gate::authorize('manageMembers', $farm);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', 'in:manager,staff'],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();

        // মালিকের role যেন ভুলে বদলে না যায়
        if ($user->id === $farm->owner_user_id) {
            return back()->withErrors(['email' => 'এই ব্যক্তি খামারের মালিক।']);
        }

        $alreadyMember = $farm->users()->where('users.id', $user->id)->exists();

        // নতুন সদস্য যোগের সময় প্ল্যানের সীমা যাচাই (backend-এই)
        if (! $alreadyMember && ! $farm->canAddMembers()) {
            return back()->withErrors([
                'email' => 'আপনার প্ল্যানের সদস্য সীমা শেষ। প্ল্যান আপগ্রেড করুন।',
            ]);
        }

        $farm->users()->syncWithoutDetaching([
            $user->id => ['role' => $data['role']],
        ]);

        return back()->with('success', 'সদস্য যোগ করা হয়েছে।');
    }

    public function destroy(Farm $farm, User $user)
    {
        Gate::authorize('manageMembers', $farm);

        abort_if($user->id === $farm->owner_user_id, 422, 'মালিককে সরানো যাবে না।');

        $farm->users()->detach($user->id);

        return back()->with('success', 'সদস্য সরানো হয়েছে।');
    }
}
