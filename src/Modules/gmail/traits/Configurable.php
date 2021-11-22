<?php

namespace Syntax\LaravelMailIntegration\Modules\gmail\traits;

use Google_Service_Gmail;
use Illuminate\Support\Arr;

/**
 * Trait Configurable
 * @package Syntax\LaravelMailIntegration\Traits
 */
trait Configurable
{
    /**
     * @return array
     */
    public function getConfigs()
    {
        return [
            'client_secret' => config('laravel-mail-integration.services.gmail.client_secret'),
            'client_id' => config('laravel-mail-integration.services.gmail.client_id'),
            'redirect_uri' => url(config('laravel-mail-integration.services.gmail.redirect_url')),
            'state' => config('laravel-mail-integration.services.gmail.state') ?? null,
        ];
    }

    public abstract function setScopes($scopes);

    public abstract function setAccessType($type);

    public abstract function setApprovalPrompt($approval);

    private function configApi()
    {
        $type = config('laravel-mail-integration.services.gmail.access_type');
        $approval_prompt = config('laravel-mail-integration.services.gmail.approval_prompt');

        $this->setScopes($this->getUserScopes());

        $this->setAccessType($type);

        $this->setApprovalPrompt($approval_prompt);
    }

    private function haveReadScope()
    {
        $scopes = $this->getUserScopes();

        return in_array(Google_Service_Gmail::GMAIL_READONLY, $scopes);
    }

    private function getUserScopes(): array
    {
        return $this->mapScopes();
    }

    private function mapScopes(): array
    {
        $scopes = config('laravel-mail-integration.services.gmail.scopes');
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

}
