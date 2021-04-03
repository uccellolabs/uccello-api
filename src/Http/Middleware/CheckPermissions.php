<?php

namespace Uccello\Api\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Uccello\Api\Models\ApiToken;
use Uccello\Core\Facades\Uccello;
use Uccello\Core\Models\Domain;
use Uccello\Core\Models\Module;

class CheckPermissions
{
    protected $apiToken;

    /**
     * Check if the user has permission to access the asked page or redirect to 403 page.
     * Rule: An user is allowed if he is admin or if he has the asked capability.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string $capability
     * @return mixed
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function handle($request, Closure $next, string $capability)
    {
        $domain = $request->domain;
        $module = $request->module;

        // If we don't use multi domains, find the first one
        if (!Uccello::useMultiDomains()) {
            $domain = Domain::firstOrFail();
        }

        // Check if module is active in domain
        if (!$module->isActiveOnDomain($domain)) {
            return response()->json(['success' => false, 'message' => 'Module not active'], 404);
        }

        // Retrieve Api Token
        $this->retrieveApiTokenFromAuthenticatedUser();

        // Check if domain is allowed
        if (!$this->isDomainAllowed($domain)) {
            return response()->json(['success' => false, 'message' => 'Domain not allowed'], 403);
        }

        // Check if IP is allowed
        if (!$this->isIpAllowed()) {
            return response()->json(['success' => false, 'message' => 'IP not allowed'], 403);
        }

        // Check if token is still valid
        if (!$this->isStillValid()) {
            return response()->json(['success' => false, 'message' => 'Token has expired'], 403);
        }

        // Check if token has capability
        if (!$this->tokenHasCapability($module, $capability)) {
            return response()->json(['success' => false, 'message' => 'Token not allowed'], 403);
        }

        return $next($request);
    }

    /**
     * Retrieve API Token from authenticated user
     *
     * @return void
     */
    private function retrieveApiTokenFromAuthenticatedUser()
    {
        $this->apiToken = ApiToken::where('user_id', Auth::id())->first();
    }

    /**
     * Check if is same domain as the token one
     *
     * @param [type] $domain
     *
     * @return boolean
     */
    private function isDomainAllowed($domain)
    {
        return $this->apiToken->domain_id === $domain->getKey();
    }

    /**
     * Checks if remote IP address is allowed
     *
     * @return boolean
     */
    private function isIpAllowed()
    {
        $isAllowed = false;

        if (!empty($this->apiToken->allowed_ip)) {
            $allowedIps = explode(',', $this->apiToken->allowed_ip);
            foreach ($allowedIps as $allowedIp) {
                if (Request::ip() === trim($allowedIp)) {
                    $isAllowed = true;
                    break;
                }
            }
        } else {
            $isAllowed = true;
        }

        return $isAllowed;
    }

    /**
     * Checks if valid_till is empty or if is greater or equal to today.
     *
     * @return boolean
     */
    private function isStillValid()
    {
        return empty($this->apiToken->valid_until) || Carbon::today()->lessThanOrEqualTo($this->apiToken->valid_until);
    }

    /**
     * A token is allowed if it has the capability
     *
     * @param \Uccello\Core\Models\Module $module
     * @param string $capability
     *
     * @return void
     */
    private function tokenHasCapability(Module $module, string $capability)
    {
        return optional(optional($this->apiToken->permissions)->{$module->name})->{$capability} ? true : false;
    }
}
