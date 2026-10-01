<?php

namespace WpComet\AISays\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpComet\AISays\AIGenerator;

class AIGeneratorValidationTest extends TestCase
{
    /**
     * @dataProvider invalidDescriptionsProvider
     */
    public function test_is_valid_description_rejects_invalid_inputs($input, string $reason): void
    {
        $this->assertFalse(
            AIGenerator::is_valid_description($input),
            "Failed asserting that {$reason} is recognized as invalid"
        );
    }

    public function invalidDescriptionsProvider(): array
    {
        return [
            'Null value' => [null, 'null value'],
            'Array value' => [['desc' => 'Some text'], 'array value'],
            'Integer value' => [12345, 'integer value'],
            'Boolean value' => [false, 'boolean value'],
            'Empty string' => ['', 'empty string'],
            'Only whitespace' => ['    ', 'whitespace string'],
            'Very short text (< 25 chars)' => ['Great cotton shirt.', 'short description stub'],
            'JSON API error response' => [
                '{"error": {"code": 429, "message": "Resource exhausted", "status": "RESOURCE_EXHAUSTED"}}',
                'JSON error payload'
            ],
            'Prefix: Error:' => ['Error: Failed to connect to server after 3 attempts.', 'Error: prefix'],
            'Prefix: Fatal:' => ['Fatal: Memory limit exceeded during image parsing.', 'Fatal: prefix'],
            'Prefix: Warning:' => ['Warning: Invalid argument supplied for foreach.', 'Warning: prefix'],
            'Prefix: Exception:' => ['Exception: Call to undefined method in file.', 'Exception: prefix'],
            'Error signature: AI Error' => ['AI Error: 404 Model gemini-3.8-flash not found.', 'AI Error signature'],
            'Error signature: AI Quota Error' => [
                'AI Quota Error: Gemini API quota exceeded for the selected model. Try switching to Gemini 3.5 Flash',
                'AI Quota Error signature'
            ],
            'Error signature: AI Service Unavailable' => [
                'AI Service Unavailable: Google AI service is currently experiencing temporary high demand for this model.',
                'AI Service Unavailable signature'
            ],
            'Error signature: cURL error' => ['Network Error: cURL error 28: Operation timed out after 30000 milliseconds', 'cURL error'],
            'Error signature: rate limit' => ['The model returned rate limit exceeded. Please wait a moment.', 'rate limit signature'],
            'Error signature: check your api key' => ['Please check your API key and provider settings in the options page.', 'API key notice signature'],
            'Error signature: high demand' => ['The model is currently facing spikes in demand, please try again soon.', 'high demand signature'],
            'Error signature: permission denied' => ['Permission denied. You do not have access to edit products.', 'permission denied signature'],
        ];
    }

    /**
     * @dataProvider validDescriptionsProvider
     */
    public function test_is_valid_description_accepts_legitimate_descriptions(string $input): void
    {
        $this->assertTrue(
            AIGenerator::is_valid_description($input),
            "Failed asserting that legitimate description is recognized as valid"
        );
    }

    public function validDescriptionsProvider(): array
    {
        return [
            'Standard paragraph description' => [
                'Elevate your everyday comfort with the Elisa EverCool™ Tee, crafted with innovative moisture-wicking technology designed to keep you fresh and dry.'
            ],
            'Multi-paragraph formatted description' => [
                "Experience superior all-day performance with Emma Leggings.\n\nFeaturing an ultra-stretch fabric blend, these leggings move effortlessly with your body whether you're at the gym or on the go."
            ],
            'Description with bullet points' => [
                "The Endurance Watch delivers precision and durability:\n- 50m Water Resistance\n- 7-Day Battery Life\n- Scratch-resistant sapphire glass crystal."
            ],
            'HTML formatted description' => [
                "<p>Discover true sustainable luxury with our <strong>Organic Cotton Crewneck</strong>. Ethically sourced and tailored for an immaculate modern silhouette.</p>"
            ],
        ];
    }
}
