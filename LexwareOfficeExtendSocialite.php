<?php

namespace Pirabyte\Socialite\LexwareOffice;

use SocialiteProviders\Manager\SocialiteWasCalled;

class LexwareOfficeExtendSocialite
{
    /**
     * Register the provider.
     *
     * @param  \SocialiteProviders\Manager\SocialiteWasCalled  $socialiteWasCalled
     * @return void
     */
    public function handle(SocialiteWasCalled $socialiteWasCalled)
    {
        $socialiteWasCalled->extendSocialite('lexwareoffice', Provider::class);
    }
}
