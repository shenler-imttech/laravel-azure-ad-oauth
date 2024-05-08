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

        $token = $this->generateToken($authUser);

        $baseUrl = config('azure-oath.web_url') . '/session/admin/login';

        $builtUrl = $baseUrl . '#token_type=Bearer' . '&expires_in=31536000' . '&access_token=' . $token . '&refresh_token=' . $token;

        return redirect()->away($builtUrl);
    }

    private function generateToken($authUser = null) {
        try {
            if (!$authUser) {
                throw new Exception("User not found!");
            }
            $token = $authUser->createToken('MicrosoftAzure', ["*"])->accessToken;

            return $token;
        } catch (\Throwable $th) {
            \Log::error("AuthController handleOauthResponse generateToken: ", ['error_message' => $th->getMessage()]);

            return null;
        }
    }

    protected function findOrCreateUser($user)
    {
        $user_class = config('azure-oath.user_class');
        $authUser = $user_class::where(config('azure-oath.user_id_field'), $user->id)->first();

        if ($authUser) {
            return $authUser;
        }

        \Log::info('$user->email: ' . $user->email);
        $existingUser = $user_class::where('user_email', $user->email)->first();

        if ($existingUser) {
            $id_field = config('azure-oath.user_id_field');
            $existingUser->$id_field = $user->id;
            $temp_hardcoded_pw = ''; // set password to empty
            $existingUser->user_password = $temp_hardcoded_pw;

            \Log::info('converted normal user to SSO user');
            \Log::info('$existingUser->user_username: ' . $existingUser->user_username);

            $existingUser->save();

            return $existingUser;
        }

        $UserFactory = new UserFactory();

        return $UserFactory->convertAzureUser($user);
    }
}
