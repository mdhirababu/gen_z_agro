<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Services\OnboardingService;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /** নিজের জন্য গাইড লুকানো (অন্য সদস্যদের পাতায় কিছু বদলায় না) */
    public function dismiss(Request $request, Farm $farm, OnboardingService $onboarding)
    {
        $onboarding->dismiss($farm, $request->user());

        return redirect()->route('farms.dashboard', $farm);
    }

    public function restore(Request $request, Farm $farm, OnboardingService $onboarding)
    {
        $onboarding->restore($farm, $request->user());

        return redirect()->route('farms.dashboard', $farm);
    }
}
