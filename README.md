# Setup

```bash
composer require pirabyte/lexwareoffice-socialite
```

Add the following to your `services.php`,

```php
'lexwareoffice' => [
    'url' => env('LEXWARE_OFFICE_URL', 'https://sandbox.example.invalid'),
    'api' => env('LEXWARE_OFFICE_API', 'https://api.sandbox.example.invalid'),
    'client_id' => env('LEXWARE_OFFICE_CLIENT_ID'),
    'client_secret' => env('LEXWARE_OFFICE_CLIENT_SECRET'),
    'redirect' => env('LEXWARE_OFFICE_REDIRECT_URL'),
],
```

Use the sandbox values provided to you privately; do not commit confidential endpoint URLs to public repositories.

In `app/Providers/AppServiceProvider.php` add the following listener to the `SocialiteWasCalled` event:

```php
Event::listen(function (SocialiteWasCalled $event) {
    $event->extendSocialite('lexwareoffice', \Pirabyte\Socialite\LexwareOffice\Provider::class);
});
```

> For Laravel < 11, add it to your `EventServiceProvider.php`.


Make sure you have the corresponding `.env` keys present:

* `LEXWARE_OFFICE_URL`
* `LEXWARE_OFFICE_API`
* `LEXWARE_OFFICE_CLIENT_ID`
* `LEXWARE_OFFICE_CLIENT_SECRET`
* `LEXWARE_OFFICE_REDIRECT_URL`

# How to use

In your controller create a new method

```php
public function redirect()
{
    return Socialite::driver('lexwareoffice')->redirect();
}
```

This will redirect to lexware office to start the OAuth2.0 Flow.

Add another method to catch the response

```php
public function callback(): \Illuminate\Http\RedirectResponse
{
    $connection = Socialite::driver('lexwareoffice')->user();
    $user = request()->user();

    LexwareOfficeClient::updateOrCreate([
        'user_id' => $user->id,
    ], [
        'access_token' => $connection->token,
        'refresh_token' => $connection->refreshToken,
        'expires_at' => Carbon::now()->addSeconds($connection->expiresIn),
    ]);

    return redirect()->route('dashboard');
}
```
