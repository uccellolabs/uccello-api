<?php

namespace Uccello\Api\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;

class ApiAuthController extends BaseController
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', [ 'except' => [ 'login' ] ]);
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
            $error = $validator->messages();
            return response()->json([ 'success'=> false, 'error'=> $error ], 400);
        }

        // Detect if it is an email or an username
        $login = request()->get('login');
        $loginFieldName = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        request()->merge([ $loginFieldName => $login ]);

        $credentials = request([ $loginFieldName, 'password' ]);

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([ 'error' => 'User unauthorized' ], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(JWTAuth::user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json([ 'message' => 'Successfully logged out' ]);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60
        ]);
    }
}