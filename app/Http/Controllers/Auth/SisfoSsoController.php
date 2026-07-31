<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SisfoSsoController extends Controller
{
    public function login(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return Redirect::route('dashboard');
        }

        return Inertia::render('Auth/Login', [
            'error' => $request->session()->get('sso_error'),
        ]);
    }

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(config('sso.client_id'), 503, 'Client ID SSO SISFO belum dikonfigurasi.');

        $state = Str::random(64);
        $verifier = $this->base64UrlEncode(random_bytes(64));
        $challenge = $this->base64UrlEncode(hash('sha256', $verifier, true));

        $request->session()->put([
            'sso_state' => $state,
            'sso_code_verifier' => $verifier,
        ]);

        $query = http_build_query([
            'client_id' => config('sso.client_id'),
            'redirect_uri' => config('sso.redirect_uri'),
            'response_type' => 'code',
            'scope' => config('sso.scope'),
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return Redirect::away(config('sso.base_url').'/oauth/authorize?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->failed($request, 'Login dibatalkan atau akses aplikasi tidak disetujui.');
        }

        $expectedState = $request->session()->pull('sso_state');
        $verifier = $request->session()->pull('sso_code_verifier');

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->query('state')) || ! $verifier) {
            return $this->failed($request, 'Sesi login tidak valid atau sudah kedaluwarsa. Silakan mulai kembali.');
        }

        if (! $request->filled('code')) {
            return $this->failed($request, 'Kode otorisasi tidak diterima dari SSO SISFO.');
        }

        try {
            $tokenResponse = Http::asForm()
                ->acceptJson()
                ->timeout(20)
                ->retry(2, 300, throw: false)
                ->post(config('sso.base_url').'/oauth/token', [
                    'grant_type' => 'authorization_code',
                    'client_id' => config('sso.client_id'),
                    'redirect_uri' => config('sso.redirect_uri'),
                    'code' => $request->string('code')->toString(),
                    'code_verifier' => $verifier,
                ]);

            if ($tokenResponse->failed() || ! $tokenResponse->json('access_token')) {
                Log::warning('Penukaran token SSO SISFO gagal.', [
                    'status' => $tokenResponse->status(),
                    'response' => $tokenResponse->json(),
                ]);

                return $this->failed($request, 'SSO SISFO tidak dapat menyelesaikan proses login.');
            }

            $profileResponse = Http::withToken($tokenResponse->json('access_token'))
                ->acceptJson()
                ->timeout(20)
                ->retry(2, 300, throw: false)
                ->get(config('sso.base_url').'/api/sso/user');

            if ($profileResponse->failed()) {
                Log::warning('Pengambilan profil SSO SISFO gagal.', ['status' => $profileResponse->status()]);

                return $this->failed($request, 'Profil pengguna tidak dapat diambil dari SISFO.');
            }

            $profile = $profileResponse->json();
            $subject = (string) ($profile['sub'] ?? '');
            $email = Str::lower((string) ($profile['email'] ?? ''));

            if ($subject === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->failed($request, 'Profil SSO SISFO tidak memiliki identitas yang valid.');
            }

            $user = User::query()
                ->where('sso_subject', $subject)
                ->orWhere('email', $email)
                ->first() ?? new User;

            $user->forceFill([
                'sso_subject' => $subject,
                'name' => (string) ($profile['name'] ?? $email),
                'email' => $email,
                'email_verified_at' => ($profile['email_verified'] ?? false) ? now() : $user->email_verified_at,
                'avatar_url' => $profile['picture'] ?? null,
                'sso_roles' => array_values((array) ($profile['roles'] ?? [])),
                'last_sso_login_at' => now(),
                'password' => $user->exists ? $user->password : Str::password(64),
            ])->save();

            Auth::login($user, true);
            $request->session()->regenerate();

            return Redirect::intended(route('dashboard'));
        } catch (ConnectionException $exception) {
            Log::error('Koneksi ke SSO SISFO gagal.', ['message' => $exception->getMessage()]);

            return $this->failed($request, 'Server SSO SISFO sedang tidak dapat dijangkau.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed($request, 'Terjadi kendala saat memproses akun SISFO.');
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::route('home');
    }

    private function failed(Request $request, string $message): RedirectResponse
    {
        $request->session()->forget(['sso_state', 'sso_code_verifier']);

        return Redirect::route('login')->with('sso_error', $message);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
