<?php


namespace Dytechltd\LaravelOutlook;

use Google_Client;
use Google_Service_Gmail;
use League\Flysystem\Exception;
use \Safe\file_get_contents;
use \Safe\json_decode;

class LaravelGmail extends Google_Client
{

    /**
     * @throws \Google\Exception
     */
    public function getOAuthClient(): Google_Client
    {
        $this->setApplicationName('Gmail API PHP Quickstart');
        $this->setScopes(Google_Service_Gmail::GMAIL_READONLY);
        $this->setAuthConfig('credentials.json');
        $this->setAccessType('offline');
        $this->setPrompt('select_account consent');

        return $this;
    }

    public function getAccessToken(): string
    {
        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.
        $tokenPath = 'token.json';
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $this->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($this->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($this->getRefreshToken()) {
                $this->fetchAccessTokenWithRefreshToken($this->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $this->createAuthUrl();
                printf("Open the following link in your browser:\n%s\n", $authUrl);
                print 'Enter verification code: ';
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $this->fetchAccessTokenWithAuthCode($authCode);
                $this->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new Exception(join(', ', $accessToken));
                }
            }
            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($this->getAccessToken()));
        }

        return $accessToken;
    }
}