<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminUpRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $roles = array_map(
            static fn (string $role): string => strtolower($role),
            $user->sso_roles,
        );
        $isAdminUp = in_array('adminup', $roles, true);

        abort_unless($isAdminUp, 403, 'Halaman ini hanya dapat diakses oleh role adminup.');

        return $next($request);
    }
}
