<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
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
            'model'    => 'alibayram/medgemma:4b',
            'api_url'  => 'http://localhost:11434',
        ]);

        $response->assertRedirect();

        $medgemma = PlatformSetting::where('platform_name', 'medgemma')->first();
        $this->assertNotNull($medgemma);
        $this->assertEquals('ollama', $medgemma->provider);
        $this->assertEquals('alibayram/medgemma:4b', $medgemma->model);
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

    public function test_owner_profile_shows_not_configured_when_no_key(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertSee('Not Configured');
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
            'model' => 'alibayram/medgemma:4b',
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
            'model' => 'alibayram/medgemma:4b',
        ]);

        $url = $setting->fresh()->chatCompletionsUrl();
        $this->assertEquals(
            'http://localhost:11434/v1/chat/completions',
            $url
        );
    }
}
