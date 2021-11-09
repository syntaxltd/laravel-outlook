<?php

namespace Syntax\LaravelSocialIntegration\Traits;

use Google_Service_Gmail;
use Illuminate\Support\Arr;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;

/**
 * Trait Configurable
 * @package Dytechltd\LaravelOutlook\Traits
 */
trait Configurable
{

    protected $additionalScopes = [];
    private $_config;

    public function __construct($config)
    {
        $this->_config = $config;
    }

    public function config($string = null)
    {
        $config = SocialAccessToken::where('partner_user_id', $this->userId)->get()->toArray();
        if ($string) {
            if (isset($config[$string])) {
                return $config[$string];
            }
        } else {
            return $config;
        }
    }

    /**
     * @return array
     */
    public function getConfigs()
    {
        return [
            'client_secret' => config('laravel-social-integration.services.gmail.client_secret'),
            'client_id' => config('laravel-social-integration.services.gmail.client_id'),
            'redirect_uri' => url(config('laravel-social-integration.services.gmail.redirect_url')),
            'state' => config('laravel-social-integration.services.gmail.state') ?? null,
        ];
    }

    public function setAdditionalScopes(array $scopes): static
    {
        $this->additionalScopes = $scopes;

        return $this;
    }

    private function configApi()
    {
        $type = config('laravel-social-integration.services.gmail.access_type');
        $approval_prompt = config('laravel-social-integration.services.gmail.approval_prompt');

        $this->setScopes($this->getUserScopes());

        $this->setAccessType($type);

        $this->setApprovalPrompt($approval_prompt);
    }

    public abstract function setScopes($scopes);

    private function getUserScopes(): array
    {
        return $this->mapScopes();
    }

    private function mapScopes(): array
    {
        $scopes = config('laravel-social-integration.services.gmail.scopes');
        $scopes = array_unique(array_filter($scopes));
        $mappedScopes = [];

        if (!empty($scopes)) {
            foreach ($scopes as $scope) {
                $mappedScopes[] = $this->scopeMap($scope);
            }
        }

        return $mappedScopes;
    }

    private function scopeMap($scope)
    {
        $scopes = [
            'all' => Google_Service_Gmail::MAIL_GOOGLE_COM,
            'compose' => Google_Service_Gmail::GMAIL_COMPOSE,
            'insert' => Google_Service_Gmail::GMAIL_INSERT,
            'labels' => Google_Service_Gmail::GMAIL_LABELS,
            'metadata' => Google_Service_Gmail::GMAIL_METADATA,
            'modify' => Google_Service_Gmail::GMAIL_MODIFY,
            'readonly' => Google_Service_Gmail::GMAIL_READONLY,
            'send' => Google_Service_Gmail::GMAIL_SEND,
            'settings_basic' => Google_Service_Gmail::GMAIL_SETTINGS_BASIC,
            'settings_sharing' => Google_Service_Gmail::GMAIL_SETTINGS_SHARING,
        ];

        return Arr::get($scopes, $scope);
    }

    public abstract function setAccessType($type);

    public abstract function setApprovalPrompt($approval);

}
