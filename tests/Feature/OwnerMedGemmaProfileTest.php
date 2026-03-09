<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OwnerMedGemmaProfileTest extends TestCase
{
    public function test_owner_sees_medgemma_card_on_profile(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertSee('MedGemma AI');
        $response->assertSee('API Configuration');
    }

    public function test_non_owner_does_not_see_medgemma_card_on_profile(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Doctor');

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertDontSee('MedGemma AI');
    }

    public function test_owner_can_save_medgemma_settings_from_profile(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $response = $this->actingAs($user)->patch('/owner/platform-settings', [
            'api_key' => 'hf_test_key_123',
            'model'   => 'google/medgemma-4b-it',
        ]);

        $response->assertRedirect();

        $medgemma = PlatformSetting::where('platform_name', 'medgemma')->first();
        $this->assertNotNull($medgemma);
        $this->assertEquals('hf_test_key_123', $medgemma->api_key);
        $this->assertEquals('google/medgemma-4b-it', $medgemma->model);
    }

    public function test_owner_can_save_ollama_provider(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $response = $this->actingAs($user)->patch('/owner/platform-settings', [
            'provider' => 'ollama',
            'model'    => 'medgemma',
            'api_url'  => 'http://localhost:11434',
        ]);

        $response->assertRedirect();

        $medgemma = PlatformSetting::where('platform_name', 'medgemma')->first();
        $this->assertNotNull($medgemma);
        $this->assertEquals('ollama', $medgemma->provider);
        $this->assertEquals('medgemma', $medgemma->model);
        $this->assertEquals('http://localhost:11434', $medgemma->api_url);
    }

    public function test_non_owner_cannot_save_medgemma_settings(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Doctor');

        $response = $this->actingAs($user)->patch('/owner/platform-settings', [
            'api_key' => 'hf_test_key_123',
            'model'   => 'google/medgemma-4b-it',
        ]);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_medgemma_settings(): void
    {
        $response = $this->patch('/owner/platform-settings', [
            'api_key' => 'hf_test_key_123',
            'model'   => 'google/medgemma-4b-it',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_owner_profile_shows_disconnected_status_for_new_ollama_default(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        // With Ollama defaults, isReady() is true (URL + model set) but status is 'disconnected'
        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertSee('Disconnected');
    }

    public function test_owner_profile_shows_provider_selector(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertSee('Hugging Face');
        $response->assertSee('Ollama');
    }

    public function test_platform_setting_is_ready_with_ollama(): void
    {
        $setting = PlatformSetting::medgemma();
        $setting->update([
            'provider' => 'ollama',
            'api_url' => 'http://localhost:11434',
            'model' => 'medgemma',
            'api_key' => null,
        ]);

        $this->assertTrue($setting->fresh()->isReady());
        $this->assertTrue($setting->fresh()->isOllama());
    }

    public function test_platform_setting_not_ready_without_hf_key(): void
    {
        $setting = PlatformSetting::medgemma();
        $setting->update([
            'provider' => 'huggingface',
            'api_key' => null,
        ]);

        $this->assertFalse($setting->fresh()->isReady());
    }

    public function test_owner_profile_shows_not_configured_for_huggingface_without_key(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $setting = PlatformSetting::medgemma();
        $setting->update([
            'provider' => 'huggingface',
            'api_key' => null,
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertSee('Not Configured');
    }

    public function test_chat_completions_url_for_huggingface(): void
    {
        $setting = PlatformSetting::medgemma();
        $setting->update([
            'provider' => 'huggingface',
            'api_url' => 'https://router.huggingface.co/hf-inference/models/',
            'model' => 'google/medgemma-4b-it',
        ]);

        $url = $setting->fresh()->chatCompletionsUrl();
        $this->assertEquals(
            'https://router.huggingface.co/hf-inference/models/google/medgemma-4b-it/v1/chat/completions',
            $url
        );
    }

    public function test_chat_completions_url_for_ollama(): void
    {
        $setting = PlatformSetting::medgemma();
        $setting->update([
            'provider' => 'ollama',
            'api_url' => 'http://localhost:11434',
            'model' => 'medgemma',
        ]);

        $url = $setting->fresh()->chatCompletionsUrl();
        $this->assertEquals(
            'http://localhost:11434/v1/chat/completions',
            $url
        );
    }

    public function test_medgemma_defaults_to_ollama_provider(): void
    {
        // Ensure no existing setting
        PlatformSetting::where('platform_name', 'medgemma')->delete();

        $setting = PlatformSetting::medgemma();

        $this->assertEquals('ollama', $setting->provider);
        $this->assertEquals('medgemma', $setting->model);
        $this->assertEquals('http://localhost:11434', $setting->api_url);
        $this->assertTrue($setting->isOllama());
        $this->assertTrue($setting->isReady());
    }

    public function test_ollama_connection_test_sends_correct_request(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $setting = PlatformSetting::medgemma();
        $setting->update([
            'provider' => 'ollama',
            'api_url' => 'http://localhost:11434',
            'model' => 'medgemma',
        ]);

        Http::fake([
            'localhost:11434/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Hi']]],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson('/owner/platform-settings/test');

        $response->assertOk();
        $response->assertJson(['status' => 'connected']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:11434/v1/chat/completions'
                && $request['model'] === 'medgemma'
                && !$request->hasHeader('Authorization');
        });
    }

    public function test_ollama_connection_test_handles_failure_gracefully(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $setting = PlatformSetting::medgemma();
        $setting->update([
            'provider' => 'ollama',
            'api_url' => 'http://localhost:11434',
            'model' => 'medgemma',
        ]);

        Http::fake([
            'localhost:11434/v1/chat/completions' => Http::response('Connection refused', 500),
        ]);

        $response = $this->actingAs($user)->postJson('/owner/platform-settings/test');

        $response->assertOk();
        $response->assertJson(['status' => 'failed']);

        $setting->refresh();
        $this->assertEquals('failed', $setting->status);
        $this->assertNotNull($setting->last_error);
    }

    public function test_connection_refused_error_gets_enriched_message_for_localhost(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $setting = PlatformSetting::medgemma();
        $setting->update([
            'provider' => 'ollama',
            'api_url'  => 'http://localhost:11434',
            'model'    => 'medgemma',
        ]);

        // Simulate a cURL error 7 (connection refused) via an exception
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 7: Failed to connect to localhost port 11434: Connection refused');
        });

        $response = $this->actingAs($user)->postJson('/owner/platform-settings/test');

        $response->assertOk();
        $response->assertJson(['status' => 'failed']);

        $data = $response->json();
        $this->assertStringContainsString('VPS', $data['error']);
        $this->assertStringContainsString('localhost', $data['error']);

        $setting->refresh();
        $this->assertEquals('failed', $setting->status);
        $this->assertNotNull($setting->last_error);
    }

    public function test_connection_refused_for_non_localhost_gets_generic_enriched_message(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $setting = PlatformSetting::medgemma();
        $setting->update([
            'provider' => 'ollama',
            'api_url'  => 'http://192.168.1.50:11434',
            'model'    => 'medgemma',
        ]);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 7: Failed to connect to 192.168.1.50 port 11434: Connection refused');
        });

        $response = $this->actingAs($user)->postJson('/owner/platform-settings/test');

        $response->assertOk();
        $response->assertJson(['status' => 'failed']);

        $data = $response->json();
        $this->assertStringContainsString('Cannot reach Ollama', $data['error']);
        $this->assertStringNotContainsString('VPS/server', $data['error']);
    }

    public function test_dns_resolution_failure_gets_enriched_message(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $setting = PlatformSetting::medgemma();
        $setting->update([
            'provider' => 'ollama',
            'api_url'  => 'http://my-ollama-host:11434',
            'model'    => 'medgemma',
        ]);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 6: Could not resolve host: my-ollama-host');
        });

        $response = $this->actingAs($user)->postJson('/owner/platform-settings/test');

        $response->assertOk();
        $response->assertJson(['status' => 'failed']);

        $data = $response->json();
        $this->assertStringContainsString('DNS', $data['error']);
    }

    public function test_bypass_tunnel_reminder_header_is_sent_for_ollama(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $setting = PlatformSetting::medgemma();
        $setting->update([
            'provider' => 'ollama',
            'api_url'  => 'https://smooth-terms-feel.loca.lt',
            'model'    => 'medgemma',
        ]);

        Http::fake([
            'smooth-terms-feel.loca.lt/*' => Http::response([
                'choices' => [['message' => ['content' => 'Hi']]],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson('/owner/platform-settings/test');

        $response->assertOk();
        $response->assertJson(['status' => 'connected']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('bypass-tunnel-reminder', 'true');
        });
    }

    public function test_localtunnel_401_response_gives_helpful_error(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $setting = PlatformSetting::medgemma();
        $setting->update([
            'provider' => 'ollama',
            'api_url'  => 'https://smooth-terms-feel.loca.lt',
            'model'    => 'medgemma',
        ]);

        // Simulate the Localtunnel reminder page (HTML body + 401)
        Http::fake([
            'smooth-terms-feel.loca.lt/*' => Http::response(
                '<html><body>Tunnel Password - loca.lt</body></html>',
                401
            ),
        ]);

        $response = $this->actingAs($user)->postJson('/owner/platform-settings/test');

        $response->assertOk();
        $response->assertJson(['status' => 'failed']);

        $data = $response->json();
        $this->assertStringContainsString('Localtunnel', $data['error']);
        $this->assertStringContainsString('Tunnel Password', $data['error']);
    }

    public function test_tunnel_timeout_gets_tunnel_aware_message(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $setting = PlatformSetting::medgemma();
        $setting->update([
            'provider' => 'ollama',
            'api_url'  => 'https://smooth-terms-feel.loca.lt',
            'model'    => 'medgemma',
        ]);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 60000 milliseconds');
        });

        $response = $this->actingAs($user)->postJson('/owner/platform-settings/test');

        $response->assertOk();
        $response->assertJson(['status' => 'failed']);

        $data = $response->json();
        $this->assertStringContainsString('timed out', $data['error']);
        $this->assertStringContainsString('tunnel', strtolower($data['error']));
    }

    public function test_bypass_tunnel_reminder_header_not_sent_for_huggingface(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $setting = PlatformSetting::medgemma();
        $setting->update([
            'provider' => 'huggingface',
            'api_url'  => 'https://router.huggingface.co/hf-inference/models/',
            'api_key'  => 'hf_test_key_123',
            'model'    => 'google/medgemma-4b-it',
        ]);

        Http::fake([
            'router.huggingface.co/*' => Http::response([
                'choices' => [['message' => ['content' => 'Hi']]],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson('/owner/platform-settings/test');

        $response->assertOk();
        $response->assertJson(['status' => 'connected']);

        Http::assertSent(function ($request) {
            return !$request->hasHeader('bypass-tunnel-reminder');
        });
    }
}
