<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_search_requires_authentication(): void
    {
        Http::preventStrayRequests();
        $this->getJson('/api/addresses?q=Lima')->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_address_search_validates_query_before_calling_provider(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Http::fake();
        foreach (['', 'Li', str_repeat('a', 256)] as $query) {
            $this->getJson('/api/addresses?q='.urlencode($query))->assertUnprocessable()->assertJsonValidationErrors('q');
        }
        Http::assertNothingSent();
    }

    public function test_missing_configuration_returns_a_safe_message(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(['services.geoapify.key' => null]);
        Http::fake();
        $this->getJson('/api/addresses?q=Lima')->assertStatus(503)->assertJsonStructure(['message']);
        Http::assertNothingSent();
    }

    public function test_it_returns_address_and_district_without_country_postcode_or_neighborhood(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(['services.geoapify.key' => 'test-secret-key']);
        Http::fake(['api.geoapify.com/*' => Http::response(['results' => [
            ['address_line1' => 'Avenida Arequipa 1234', 'city' => 'Lima', 'suburb' => 'Santa Beatriz', 'postcode' => '15072', 'country_code' => 'pe'],
            ['address_line1' => 'Avenida Arequipa 1234', 'city' => 'Lima', 'suburb' => 'Santa Beatriz', 'postcode' => '15072', 'country_code' => 'pe'],
            ['address_line1' => 'Aeropuerto Jorge Chávez', 'district' => 'Callao', 'country_code' => 'pe'],
            ['address_line1' => 'Otra dirección', 'country_code' => 'cl'],
            ['address_line1' => str_repeat('a', 256), 'country_code' => 'pe'],
            ['address_line1' => 'Avenida Ejemplo 123', 'city' => 'San Isidro', 'district' => 'Chacarilla', 'country_code' => 'pe'],
            ['country_code' => 'pe'],
        ]])]);
        $this->getJson('/api/addresses?q=Are')->assertOk()->assertExactJson([
            ['name' => 'Avenida Arequipa 1234', 'label' => 'Avenida Arequipa 1234, Lima'],
            ['name' => 'Aeropuerto Jorge Chávez', 'label' => 'Aeropuerto Jorge Chávez, Callao'],
            ['name' => 'Avenida Ejemplo 123', 'label' => 'Avenida Ejemplo 123, San Isidro'],
        ])->assertDontSee('test-secret-key');
        Http::assertSent(fn ($request): bool => $request['apiKey'] === 'test-secret-key'
            && $request['text'] === 'Are'
            && $request['filter'] === 'countrycode:pe'
            && $request['lang'] === 'es'
            && ! isset($request['type'])
            && $request['format'] === 'json'
            && $request['limit'] === 5);
    }

    public function test_it_keeps_the_street_number_when_the_address_line_is_missing(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(['services.geoapify.key' => 'test-secret-key']);
        Http::fake(['api.geoapify.com/*' => Http::response(['results' => [
            ['street' => 'Avenida Ejemplo', 'housenumber' => '123', 'country_code' => 'pe'],
        ]])]);
        $this->getJson('/api/addresses?q=Ejemplo')->assertOk()->assertExactJson([
            ['name' => 'Avenida Ejemplo 123', 'label' => 'Avenida Ejemplo 123'],
        ]);
    }

    public function test_no_matches_returns_an_empty_list(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(['services.geoapify.key' => 'test-secret-key']);
        Http::fake(['api.geoapify.com/*' => Http::response(['results' => []])]);
        $this->getJson('/api/addresses?q=zzz')->assertOk()->assertExactJson([]);
    }

    public function test_provider_errors_and_invalid_responses_do_not_leak_credentials(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(['services.geoapify.key' => 'test-secret-key']);
        foreach ([Http::response(['message' => 'test-secret-key'], 403), Http::response('invalid json')] as $response) {
            Http::fake(['api.geoapify.com/*' => $response]);
            $this->getJson('/api/addresses?q=Lima')->assertStatus(502)->assertJsonStructure(['message'])->assertDontSee('test-secret-key');
        }
    }

    public function test_connection_failure_returns_a_safe_error(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(['services.geoapify.key' => 'test-secret-key']);
        Http::fake(['api.geoapify.com/*' => Http::failedConnection()]);
        $this->getJson('/api/addresses?q=Lima')->assertStatus(502)->assertJsonStructure(['message'])->assertDontSee('test-secret-key');
    }
}
