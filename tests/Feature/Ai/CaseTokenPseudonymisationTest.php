<?php

namespace Tests\Feature\Ai;

use App\Models\Patient;
use App\Services\CaseTokenService;
use App\Services\MedGemmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CaseTokenPseudonymisationTest extends TestCase
{
    use RefreshDatabase;

    private CaseTokenService $caseTokenService;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('clinic.case_token_secret', 'test-secret-32-chars-long-minimum');
        $this->caseTokenService = app(CaseTokenService::class);
    }

    public function test_tokenize_returns_64_char_hex_string(): void
    {
        $patient = Patient::factory()->create();
        $token   = $this->caseTokenService->tokenize($patient);

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function test_tokenize_is_deterministic_per_patient(): void
    {
        $patient = Patient::factory()->create();

        $token1 = $this->caseTokenService->tokenize($patient);
        $token2 = $this->caseTokenService->tokenize($patient);

        $this->assertSame($token1, $token2);
    }

    public function test_resolve_returns_original_patient(): void
    {
        $patient = Patient::factory()->create();
        $token   = $this->caseTokenService->tokenize($patient);

        $resolved = $this->caseTokenService->resolve($token);

        $this->assertNotNull($resolved);
        $this->assertSame($patient->id, $resolved->id);
    }

    public function test_age_band_rounds_to_5_year_bucket(): void
    {
        $dob32 = now()->subYears(32)->subMonths(3);
        $dob5  = now()->subYears(5);
        $dob0  = now()->subMonths(6);

        $this->assertSame('30-34', $this->caseTokenService->ageBand($dob32));
        $this->assertSame('5-9',   $this->caseTokenService->ageBand($dob5));
        $this->assertSame('0-4',   $this->caseTokenService->ageBand($dob0));
        $this->assertSame('unknown', $this->caseTokenService->ageBand(null));
    }

    public function test_medgemma_outbound_body_contains_no_raw_phi(): void
    {
        $patient = Patient::factory()->create([
            'first_name'    => 'John',
            'last_name'     => 'Testpatient',
            'phone'         => '03001234567',
            'email'         => 'john.testpatient@example.com',
            'cnic'          => '35201-1234567-9',
            'date_of_birth' => now()->subYears(28),
            'gender'        => 'male',
        ]);

        $capturedBody = null;

        Http::fake(function ($request) use (&$capturedBody) {
            $capturedBody = $request->body();
            return Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200);
        });

        /** @var MedGemmaService $service */
        $service = app(MedGemmaService::class);

        // Directly invoke the method under test via reflection (it's private).
        $reflect = new \ReflectionClass($service);
        $method  = $reflect->getMethod('buildConsultationPrompt');
        $method->setAccessible(true);
        $prompt = $method->invoke($service, $patient, null);

        // PHI substrings must NOT appear in the outbound prompt.
        $this->assertStringNotContainsString('John',              $prompt);
        $this->assertStringNotContainsString('Testpatient',       $prompt);
        $this->assertStringNotContainsString('03001234567',       $prompt);
        $this->assertStringNotContainsString('john.testpatient',  $prompt);
        $this->assertStringNotContainsString('35201-1234567-9',   $prompt);

        // Case token and age band must be present.
        $this->assertStringContainsString('Case Token:',  $prompt);
        $this->assertStringContainsString('Age Band:',    $prompt);
        $this->assertStringContainsString('Gender: male', $prompt);
    }
}
