<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TripInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_the_trip_interface(): void
    {
        $this->get('/')->assertOk()->assertSee('Mis viajes')->assertSee('Crear cuenta');
    }

    public function test_registration_validates_fields_and_hashes_the_password(): void
    {
        $this->postJson('/api/register', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
        $this->postJson('/api/register', [
            'name' => 'Visitante',
            'email' => 'visitante@example.test',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $data = ['name' => 'Visitante', 'email' => 'visitante@example.test', 'password' => 'password123', 'password_confirmation' => 'password123'];
        $this->postJson('/api/register', $data)->assertCreated()->assertJsonPath('user.email', $data['email'])->assertJsonMissingPath('user.password');
        $this->assertTrue(Hash::check($data['password'], User::firstOrFail()->password));
        $this->postJson('/api/register', $data)->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_login_crud_ownership_and_logout_with_real_tokens(): void
    {
        $owner = User::factory()->create(['password' => Hash::make('password123')]);
        $other = User::factory()->create();
        $otherTrip = $other->trips()->create(['origin' => 'Privado', 'destination' => 'Destino', 'departure_at' => '2026-11-01 10:00:00', 'price' => 50]);
        $this->getJson('/api/trips')->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $owner->email, 'password' => 'wrong'])->assertUnprocessable();
        $login = $this->postJson('/api/login', ['email' => $owner->email, 'password' => 'password123'])->assertOk()->assertJsonPath('user.id', $owner->id);
        $token = $login->json('token');
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->postJson('/api/trips', [], $headers)->assertUnprocessable();
        $trip = $this->postJson('/api/trips', [
            'user_id' => $other->id,
            'origin' => 'Avenida Arequipa 1234, Lima',
            'destination' => 'Aeropuerto Jorge Chávez, Callao',
            'departure_at' => '2026-11-01 10:00:00',
            'price' => 25.50,
        ], $headers)->assertCreated()->assertJsonPath('user_id', $owner->id)->assertJsonPath('status', 'pending')->assertJsonPath('origin', 'Avenida Arequipa 1234, Lima')->assertJsonPath('destination', 'Aeropuerto Jorge Chávez, Callao');
        $id = $trip->json('id');
        $this->getJson('/api/trips', $headers)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $id);
        $this->getJson('/api/trips/'.$id, $headers)->assertOk();
        $this->patchJson('/api/trips/'.$id, ['price' => -1], $headers)->assertUnprocessable();
        $this->patchJson('/api/trips/'.$id, ['price' => 90], $headers)->assertOk()->assertJsonPath('price', '90.00');
        $this->getJson('/api/trips/'.$otherTrip->id, $headers)->assertNotFound();
        $this->patchJson('/api/trips/'.$otherTrip->id, ['price' => 999], $headers)->assertNotFound();
        $this->deleteJson('/api/trips/'.$otherTrip->id, [], $headers)->assertNotFound();
        $this->deleteJson('/api/trips/'.$id, [], $headers)->assertOk();
        $this->assertDatabaseMissing('trips', ['id' => $id]);
        $this->assertDatabaseHas('trips', ['id' => $otherTrip->id, 'price' => 50]);
        $this->postJson('/api/logout', [], $headers)->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/trips', $headers)->assertUnauthorized();
    }
}
