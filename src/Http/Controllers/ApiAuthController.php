<?php

namespace Uccello\Api\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Uccello\Core\Facades\Uccello;
use Uccello\Core\Models\Capability;
use Uccello\Core\Models\Domain;

class ApiAuthController extends BaseController
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum', [ 'except' => [ 'login' ] ]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        // Check if fields are defined
        $rules = [
            'login' => 'required',
            'password' => 'required',
        ];

        $input = request()->only('login', 'password');
        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'validation_errors' => $validator->errors()]);
        }

        // Detect if it is an email or an username
        $login = request()->get('login');
        $loginFieldName = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        request()->merge([ $loginFieldName => $login ]);

        $credentials = request([$loginFieldName, 'password']);

        if (!Auth::once($credentials)) {
            return response()->json(['success' => false, 'message' => 'User unauthorized'], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('token')->plainTextToken;

        return response()->json([
            "success" => true,
            "access_token" => $token,
            "multi_domains" => Uccello::useMultiDomains(),
            "user" => $user
        ]);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $user = Auth::user();
        if (!is_null($user)) {
            return response()->json(['success' => true, 'user' => $user]);
        } else {
            return response()->json(['success' => false, 'message' => 'User not authenticated']);
        }
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        // Get user who requested the logout
        $user = Auth::user();

        // Revoke current user token
        $user->tokens()->where('id', $user->currentAccessToken()->id)->delete();

        return response()->json(['success' => true, 'message' => 'Successfully logged out']);
    }

    /**
     * Returns all user capabilities on a domain.
     *
     * @param \Uccello\Core\Models\Domain $domain
     *
     * @return array
     */
    public function capabilities(Domain $domain)
    {
        $permissions = [];
        $capabilities = Capability::all();

        foreach ($domain->modules as $module) {
            $permissions[$module->name] = [];
            foreach ($capabilities as $capability) {
                $permissions[$module->name][$capability->name] = Auth::user()->hasCapabilityOnModule($capability->name, $domain, $module);
            }
        }

        return $permissions;
    }

    public function domains()
    {
        $allowedDomains = collect();

        $user = Auth::user();
        $domains = Domain::orderBy('name', 'asc')->get();

        foreach ($domains as $domain) {
            if ($user->is_admin === true || $user->hasRoleOnDomain($domain)) {
                $allowedDomains[] = $domain;
            }
        }

        return $allowedDomains;
    }

    public function modules(?Domain $domain)
    {
        if (!Uccello::useMultiDomains()) {
            $domain = Domain::firstOrFail();
        }

        $allowedModules = collect();

        $user = Auth::user();
        $modules = $domain->modules()->get();

        foreach ($modules as $module) {
            if ($user->capabilitiesOnModule($domain, $module)->count() > 0) {
                $moduleData = $module;
                $moduleData->translation = uctrans($module->name, $module);
                $moduleData->crud = !empty($module->model_class);
                $allowedModules[] = $moduleData;
            }
        }

        return $allowedModules;
    }
}
