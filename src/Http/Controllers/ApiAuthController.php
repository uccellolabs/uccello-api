<?php

namespace Uccello\Api\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Uccello\Api\Models\ApiToken;
use Uccello\Core\Facades\Uccello;
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
        $this->middleware('auth:sanctum');
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
     * Returns all user capabilities on a domain.
     *
     * @param \Uccello\Core\Models\Domain $domain
     *
     * @return array
     */
    public function capabilities(Domain $domain)
    {
        $permissions = [];

        $apiToken = ApiToken::where('user_id', Auth::id())->first();

        foreach ($domain->modules as $module) {
            $permissions[$module->name] = [];
            foreach (['create', 'retrieve', 'update', 'delete'] as $capability) {
                $permissions[$module->name][$capability] = optional(optional($apiToken->permissions)->{$module->name})->{$capability} ? true : false;
            }
        }

        return $permissions;
    }

    public function domains()
    {
        $allowedDomains = collect();

        $apiToken = ApiToken::where('user_id', Auth::id())->first();
        $tokenDomain = Domain::find($apiToken->domain_id);

        if ($tokenDomain) {
            $allowedDomains[] = $tokenDomain;
        }

        return $allowedDomains;
    }

    public function modules(?Domain $domain)
    {
        if (!Uccello::useMultiDomains()) {
            $domain = Domain::firstOrFail();
        }

        $allowedModules = collect();

        $apiToken = ApiToken::where('user_id', Auth::id())->first();
        $modules = $domain->modules()->get();

        foreach ($modules as $module) {
            if (optional($apiToken->permissions)->{$module->name}) {
                $moduleData = $module;
                $moduleData->translation = uctrans($module->name, $module);
                $moduleData->crud = !empty($module->model_class);
                $allowedModules[] = $moduleData;
            }
        }

        return $allowedModules;
    }
}
