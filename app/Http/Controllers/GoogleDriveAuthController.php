<?php

namespace App\Http\Controllers;

use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * OAuth round trip that lets an admin connect the Kudos Google account DOES stores files with.
 */
class GoogleDriveAuthController extends Controller
{
    public const STATE_SESSION_KEY = 'google_drive_oauth_state';

    /**
     * Send the admin to Google's consent screen.
     */
    public function redirect(Request $request, GoogleDriveService $drive): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if (! $drive->hasOAuthClient()) {
            return to_route('settings.google-drive')
                ->with('error', __('Faltan GOOGLE_DRIVE_CLIENT_ID y GOOGLE_DRIVE_CLIENT_SECRET en el archivo .env.'));
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        return redirect()->away($drive->authorizationUrl(route('google-drive.callback'), $state));
    }

    /**
     * Google sends the admin back here with a one-time code (or an error when they cancelled).
     */
    public function callback(Request $request, GoogleDriveService $drive): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $expectedState = (string) $request->session()->pull(self::STATE_SESSION_KEY, '');
        if ($expectedState === '' || ! hash_equals($expectedState, (string) $request->query('state', ''))) {
            return to_route('settings.google-drive')
                ->with('error', __('La respuesta de Google no coincide con esta sesión. Vuelve a intentarlo.'));
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return to_route('settings.google-drive')
                ->with('error', __('Se canceló la conexión con Google Drive.'));
        }

        try {
            $account = $drive->connect((string) $request->query('code'), route('google-drive.callback'));
        } catch (RuntimeException $e) {
            report($e);

            return to_route('settings.google-drive')->with('error', $e->getMessage());
        }

        return to_route('settings.google-drive')
            ->with('success', __('Google Drive conectado con :email.', ['email' => $account['email']]));
    }

    protected function authorizeAdmin(Request $request): void
    {
        abort_unless((bool) $request->user()?->isAdmin(), 403, __('No tiene permisos para acceder a esta sección.'));
    }
}
