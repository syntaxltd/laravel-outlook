<?php

namespace Syntax\LaravelSocialIntegration\Modules\outlook;

use GuzzleHttp\Exception\GuzzleException;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\User;
use Throwable;

class LaravelOutlook
{
    /**
     * @throws Throwable
     * @throws GraphException
     * @throws GuzzleException
     */
    public function fetchMessages()
    {
        /** @var User $user */
        $user = $this->getGraphClient()->createRequest('GET', '/me?$select=displayName,mail,mailboxSettings,userPrincipalName')
            ->setReturnType(User::class)->execute();

        return $user;
    }

    /**
     * @throws Throwable
     */
    public function getGraphClient(): Graph
    {
        return (new Graph)->setAccessToken($this->auth()->getToken());
    }

    public function auth(): AuthClient
    {
        return new AuthClient;
    }
}
