<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Cryptocurrency;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register', 'verifyEmail', 'forgotPassword', 'resetPassword']]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'country' => 'required|string|max:100',
            'date_of_birth' => 'required|date|before:' . now()->subYears(18),
            'referral_code' => 'nullable|string|exists:users,referral_code',
            'terms_accepted' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find referrer if referral code provided
            $referrer = null;
            if ($request->referral_code) {
                $referrer = User::where('referral_code', $request->referral_code)->first();
            }

            // Create user
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'country' => $request->country,
                'date_of_birth' => $request->date_of_birth,
                'referred_by' => $referrer ? $referrer->id : null,
                'referral_code' => $this->generateReferralCode(),
                'language' => $request->language ?? 'en',
                'timezone' => $request->timezone ?? 'UTC',
            ]);

            // Assign default role
            $user->assignRole('user');

            // Create default wallets for major cryptocurrencies
            $this->createDefaultWallets($user);

            // Send email verification
            $this->sendEmailVerification($user);

            // If referred, credit referral bonus
            if ($referrer) {
                $this->creditReferralBonus($referrer, $user);
            }

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please check your email for verification.',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'remember_me' => 'boolean',
            'two_factor_code' => 'nullable|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $credentials = $request->only('email', 'password');
            $user = User::where('email', $request->email)->first();

            // Check if user exists
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check account status
            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is suspended or banned'
                ], 403);
            }

            // Check if account is locked
            if ($user->locked_until && $user->locked_until->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is temporarily locked until ' . $user->locked_until->format('Y-m-d H:i:s')
                ], 423);
            }

            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                $this->handleFailedLogin($user);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check 2FA if enabled
            if ($user->two_factor_enabled) {
                if (!$request->two_factor_code) {
                    return response()->json([
                        'success' => false,
                        'message' => '2FA code required',
                        'requires_2fa' => true
                    ], 200);
                }

                if (!$this->verify2FA($user, $request->two_factor_code)) {
                    $this->handleFailedLogin($user);
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid 2FA code'
                    ], 401);
                }
            }

            // Generate JWT token
            $customClaims = ['remember' => $request->remember_me ?? false];
            $token = JWTAuth::fromUser($user, $customClaims);

            // Update login info
            $user->update([
                'failed_login_attempts' => 0,
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not create token'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            
            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out'
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout'
            ], 500);
        }
    }

    public function me()
    {
        try {
            $user = Auth::user();
            return response()->json([
                'success' => true,
                'data' => $this->formatUserData($user, true)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }

    public function refresh()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            
            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token could not be refreshed'
            ], 401);
        }
    }

    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)
                   ->where('login_token', $request->token)
                   ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification token'
            ], 400);
        }

        $user->update([
            'email_verified_at' => now(),
            'login_token' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully'
        ]);
    }

    public function enable2FA(Request $request)
    {
        $user = Auth::user();
        
        if ($user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => '2FA is already enabled'
            ], 400);
        }

        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();
        
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        // Store secret temporarily (will be confirmed when user verifies)
        $user->update(['two_factor_secret' => encrypt($secret)]);

        return response()->json([
            'success' => true,
            'data' => [
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl,
                'recovery_codes' => $user->generateRecoveryCodes()
            ]
        ]);
    }

    public function confirm2FA(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        if (!$this->verify2FA($user, $request->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid 2FA code'
            ], 400);
        }

        $user->update(['two_factor_enabled' => true]);

        return response()->json([
            'success' => true,
            'message' => '2FA enabled successfully'
        ]);
    }

    public function disable2FA(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password'
            ], 400);
        }

        if (!$this->verify2FA($user, $request->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid 2FA code'
            ], 400);
        }

        $user->disable2FA();

        return response()->json([
            'success' => true,
            'message' => '2FA disabled successfully'
        ]);
    }

    private function verify2FA($user, $code)
    {
        if (!$user->two_factor_secret) {
            return false;
        }

        $google2fa = app('pragmarx.google2fa');
        $secret = decrypt($user->two_factor_secret);
        
        // Check regular code
        if ($google2fa->verifyKey($secret, $code)) {
            return true;
        }

        // Check recovery codes
        if ($user->two_factor_recovery_codes && in_array($code, $user->two_factor_recovery_codes)) {
            // Remove used recovery code
            $recoveryCodes = array_diff($user->two_factor_recovery_codes, [$code]);
            $user->update(['two_factor_recovery_codes' => array_values($recoveryCodes)]);
            return true;
        }

        return false;
    }

    private function handleFailedLogin($user)
    {
        $user->increment('failed_login_attempts');
        
        if ($user->failed_login_attempts >= config('auth.max_login_attempts', 5)) {
            $user->lockAccount(30, 'Too many failed login attempts');
        }
    }

    private function formatUserData($user, $detailed = false)
    {
        $data = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
            'is_verified' => $user->is_verified,
            'kyc_status' => $user->kyc_status,
            'two_factor_enabled' => $user->two_factor_enabled,
            'email_verified_at' => $user->email_verified_at,
            'referral_code' => $user->referral_code,
            'language' => $user->language,
            'timezone' => $user->timezone,
            'created_at' => $user->created_at,
        ];

        if ($detailed) {
            $data['wallets'] = $user->wallets->map(function ($wallet) {
                return [
                    'id' => $wallet->id,
                    'cryptocurrency' => $wallet->cryptocurrency->only(['id', 'symbol', 'name', 'logo_url']),
                    'balance' => $wallet->balance,
                    'available_balance' => $wallet->available_balance,
                    'usd_value' => $wallet->usd_value,
                ];
            });
            $data['total_balance_usd'] = $user->getTotalBalance();
        }

        return $data;
    }

    private function createDefaultWallets($user)
    {
        $defaultCurrencies = ['BTC', 'ETH', 'USDT', 'BNB'];
        
        foreach ($defaultCurrencies as $symbol) {
            $cryptocurrency = Cryptocurrency::where('symbol', $symbol)->first();
            if ($cryptocurrency) {
                Wallet::create([
                    'user_id' => $user->id,
                    'cryptocurrency_id' => $cryptocurrency->id,
                    'demo_balance' => $symbol === 'USDT' ? 10000 : 0, // Give demo USDT
                ]);
            }
        }
    }

    private function generateReferralCode()
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        } while (User::where('referral_code', $code)->exists());
        
        return $code;
    }

    private function sendEmailVerification($user)
    {
        $token = bin2hex(random_bytes(32));
        $user->update(['login_token' => $token]);

        // Send email (implement actual email sending)
        // Mail::to($user->email)->send(new EmailVerification($user, $token));
    }

    private function creditReferralBonus($referrer, $newUser)
    {
        $bonusAmount = config('app.referral_bonus_amount', 25);
        $usdtCurrency = Cryptocurrency::where('symbol', 'USDT')->first();
        
        if ($usdtCurrency) {
            $wallet = Wallet::getOrCreateWallet($referrer->id, $usdtCurrency->id);
            $wallet->credit($bonusAmount, 'Referral bonus for ' . $newUser->email);
        }
    }
}