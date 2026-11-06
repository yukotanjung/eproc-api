<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

class Permission extends Controller
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = Auth::user();
        $user = User::with('roles', 'roles.permissions')->where("id", "=", $user->id)->first();
        if ($user) {
            foreach ($permissions as $permission) {

                if ($user->hasPermission($permission)) {

                    $request->attributes->add(['auth' => (["user"=>$user,"role"=>$permission])]);
                    return $next($request);
                }
            }
        }

        return $this->invalidResponse("Your request is not allowed");
    }
}
