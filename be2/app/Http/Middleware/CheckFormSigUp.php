<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFormSigUp
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->first_name != null && $request->sure_name != null 
        && $request->email != null && $request->password != null
        && $request->gender != null) {
            // return redirect('/info-form-sigup');
            $arrayInfo = $request->all();
            var_dump($arrayInfo);
            // $request->first_name
            // var_dump($request->info_name);
        }
        return $next($request);
    }
}
