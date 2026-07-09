<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Contracts\PasskeyDeletedResponse as PasskeyDeletedResponseContract;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Laravel\Passkeys\Contracts\PasskeyRegistrationResponse as PasskeyRegistrationResponseContract;
use Laravel\Passkeys\Passkeys;
use App\Http\Responses\PasskeyDeletedResponse;
use App\Http\Responses\PasskeyLoginResponse;
use App\Http\Responses\PasskeyRegistrationResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // We register our own webauthn routes in routes/api.php (matching this
        // app's api/auth:sanctum conventions), so the package's own web-middleware
        // routes are disabled. Must happen during register() so it takes effect
        // before PasskeysServiceProvider::boot() decides whether to load them.
        Passkeys::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function(Request $request) {
        // This helps our local testing to avoid throttling restrictions
        $attempts = (config('app.env') === 'local') ? 1000 : 5;

        return
            Limit::perMinute($attempts)->by($request->input('email'))
                ->response(function(Request $request, array $headers) {
                    return response()->json([
                        'status' => 'Request failed.',
                        'message' => 'Too many invalid attempts.  Please try again in a few minutes.',
                        'data' => [],
                    ], 200);
                });
            });

        // Override the package's default responses so passkey ceremonies
        // return this app's HttpResponses envelope (and, for login, a Sanctum
        // token) instead of the package's redirect/Blade-oriented defaults.
        $this->app->singleton(PasskeyLoginResponseContract::class, PasskeyLoginResponse::class);
        $this->app->singleton(PasskeyRegistrationResponseContract::class, PasskeyRegistrationResponse::class);
        $this->app->singleton(PasskeyDeletedResponseContract::class, PasskeyDeletedResponse::class);
    }
}
