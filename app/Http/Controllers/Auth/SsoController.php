<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegUser; // Your User model
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class SsoController extends Controller
{
    public function callback(Request $request)
    {
        // 1. Get the token from the request URL
        $token = $request->query('token');

        if (!$token) {
            return redirect('/login')->with('error', 'SSO token not provided.');
        }

        try {
            // 2. Parse the token. This will automatically verify the signature
            // using the JWT_SECRET from your .env file.
            $payload = JWTAuth::setToken($token)->getPayload();

            // 3. Find or Create the user in your local database
            // We use firstOrCreate to either find the user by email or create them if they don't exist.
            $user = SimpegUser::firstOrCreate(
                ['email' => $payload['email']], // Field to find the user by
                [
                    // Fields to fill if creating a new user
                    'name' => $payload['name'],
                    'password' => bcrypt(str()->random(20)), // Create a random secure password
                    'external_id' => $payload['sub'], // Optional: store the e-portal's user ID
                ]
            );

            // 4. Log the user into your application's session
            Auth::login($user);

            // 5. Redirect to the intended page (e.g., dashboard)
            return redirect()->intended('/dashboard');

        } catch (JWTException $e) {
            // 6. Handle errors (e.g., token is invalid, expired, or blacklisted)
            // You can log the error for debugging: \Log::error($e->getMessage());
            return redirect('/login')->with('error', 'SSO login failed: ' . $e->getMessage());
        }
    }
}