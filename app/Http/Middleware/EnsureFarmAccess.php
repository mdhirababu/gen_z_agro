<?php

namespace App\Http\Middleware;

use App\Models\Farm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFarmAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $farm = $request->route('farm');

        if (! $farm instanceof Farm) {
            $farm = Farm::findOrFail($farm);
        }

        $user = $request->user();
        $role = $farm->roleOf($user);

        if ($role === null && $user->is_super_admin) {
            $role = 'super_admin';
        }

        // সদস্য না হলে 404, যেন খামারের অস্তিত্বই বোঝা না যায়
        abort_if($role === null, 404);

        $request->attributes->set('currentFarm', $farm);
        $request->attributes->set('currentFarm', $farm);
        app()->instance('currentFarm', $farm);
        $request->attributes->set('farmRole', $role);

        view()->share('currentFarm', $farm);
        view()->share('farmRole', $role);

        return $next($request);
    }
}
