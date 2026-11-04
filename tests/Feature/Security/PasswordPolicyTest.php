<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    private function passes(string $password): bool
    {
        return Validator::make(['password' => $password], ['password' => [Password::defaults()]])->passes();
    }

    public function test_production_requires_long_passwords_with_letters_and_numbers_that_are_not_breached()
    {
        $this->app['env'] = 'production';
        // The breach lookup answers with SHA-1 suffixes; report this password's as breached.
        $breachedSuffix = strtoupper(substr(sha1('bauko2026harvest'), 5));
        Http::fake(['api.pwnedpasswords.com/*' => Http::response("{$breachedSuffix}:42\r\n")]);

        $this->assertFalse($this->passes('short1'));
        $this->assertFalse($this->passes('onlyletterslong'));
        $this->assertFalse($this->passes('bauko2026harvest'));
        $this->assertTrue($this->passes('rice-terraces-2026'));
    }

    public function test_weak_passwords_are_refused_in_every_environment()
    {
        $this->assertFalse($this->passes('password'));
        $this->assertFalse($this->passes('password12'));
        $this->assertFalse($this->passes('1234567890'));
        $this->assertFalse($this->passes('Bauko@12345'));
        $this->assertFalse($this->passes('aaaa1111aaaa'));
        $this->assertFalse($this->passes('abcdefgh12'));
        $this->assertTrue($this->passes('rice-terraces-2026'));
        $this->assertTrue($this->passes('Kabayan7market'));
    }
}
