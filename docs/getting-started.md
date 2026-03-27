# Getting Started (5 minutes)

1. Install the package
   ```bash
   composer require creativecrafts/laravel-sso
   ```
2. Publish config, migrations, and UI
   ```bash
   php artisan sso:install
   ```
3. Create a tenant, identity provider, and connection
   ```bash
   php artisan sso:make-tenant "Acme"
   php artisan sso:make-idp $(php -r "require 'vendor/autoload.php'; echo \\Illuminate\\Support\\Str::ulid();") "Acme IdP" --protocol=oidc
   php artisan sso:make-connection {tenant_ulid} {idp_id} "Acme OIDC"
   ```
4. Add a login button to your app
   ```blade
   <x-sso-button tenant="{{ $tenantUlid }}" connection="{{ $connectionId }}" />
   ```
5. Run the doctor to verify configuration
   ```bash
   php artisan sso:doctor
   ```

See `config/sso.php` for all options; defaults are safe and multi-tenant aware.
