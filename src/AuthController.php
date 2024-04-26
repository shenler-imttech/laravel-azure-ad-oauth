<?php

namespace Metrogistics\AzureSocialite;

use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirectToOauthProvider()
    {
        return Socialite::driver('azure-oauth')->redirect();
    }

    public function handleOauthResponse()
    {
        $user = Socialite::driver('azure-oauth')->user();

        $authUser = $this->findOrCreateUser($user);

        $user_status = $authUser->user_status;
        $user_approved = $authUser->user_approved;
        $user_deleted_at = $authUser->deleted_at;

        if (!is_null($user_deleted_at)) {
            \Log::info('user deleted');
            \Log::info('return here');

            $baseUrl = config('azure-oath.web_url') . '/session/admin/login';
            $builtUrl = $baseUrl . '?error_message=' . 'Your user account has been rejected. Please contact an administrator for assistance.';
            return redirect()->away($builtUrl);
        } else if ($user_status == 0 && $user_approved == 0) {
            \Log::info('user inactive and pending');
            \Log::info('return here');

            $baseUrl = config('azure-oath.web_url') . '/session/admin/login';
            $builtUrl = $baseUrl . '?error_message=' . 'Your user account is pending activation. Please contact an administrator to complete the process.';
            return redirect()->away($builtUrl);
        } else if ($user_status == 0 && $user_approved == 1) {
            \Log::info('user inactive');
            \Log::info('return here');

            $baseUrl = config('azure-oath.web_url') . '/session/admin/login';
            $builtUrl = $baseUrl . '?error_message=' . 'Your user account is inactive. Please contact an administrator for assistance.';
            return redirect()->away($builtUrl);
        }

        \Log::info('before $this->generateToken($authUser)');

        $token = $this->generateToken($authUser);

        \Log::info('after $this->generateToken($authUser)');

        $baseUrl = config('azure-oath.web_url') . '/session/admin/login';

        $builtUrl = $baseUrl . '#token_type=Bearer' . '&expires_in=86400' . '&access_token=' . $token . '&refresh_token=' . $token;

        return redirect()->away($builtUrl);
    }

    private function generateToken($authUser = null) {
        \Log::info('private function generateToken($authUser = null) {');

        try {
            if (!$authUser) {
                throw new Exception("User not found!");
            }
            $token = $authUser->createToken('MicrosoftAzure', ["*"])->accessToken;

            return $token;
        } catch (\Throwable $th) {
            \Log::error("AuthController handleOauthResponse generateToken : ", ['error_message' => $th->getMessage()]);

            return null;
        }
    }

    protected function findOrCreateUser($user)
    {
        $user_class = config('azure-oath.user_class');
        $authUser = $user_class::where(config('azure-oath.user_id_field'), $user->id)->withTrashed()->first();

        if ($authUser) {
            return $authUser;
        }

        // else if check if user existing here
        // existingUserAddOn - update user information with azure id
        \Log::info('$user->email');
        \Log::info($user->email);
        $existingUser = $user_class::where('user_email', $user->email)->withTrashed()->first();

        if ($existingUser) {
            $id_field = config('azure-oath.user_id_field');
            $existingUser->$id_field = $user->id;
            $temp_hardcoded_pw = '$2y$10$iUH9jVmlym.WQZt1K3acI.KispPHVliob70RFpYp21X2ykxJaKyYa';
            $existingUser->user_password = $temp_hardcoded_pw;

            \Log::info('converted normal user to SSO user');
            \Log::info('$existingUser->user_username');
            \Log::info($existingUser->user_username);

            $existingUser->save();

            return $existingUser;
        }

        $UserFactory = new UserFactory();

        return $UserFactory->convertAzureUser($user);
    }
}
