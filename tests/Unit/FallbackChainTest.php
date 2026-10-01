<?php

namespace WpComet\AISays\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpComet\AISays\AIGenerator;
use ReflectionClass;

class FallbackChainTest extends TestCase
{
    public function test_candidate_fallbacks_exclude_current_model(): void
    {
        $candidate_fallbacks = ['gemini-3.6-flash', 'gemini-3.5-flash', 'gemini-2.5-flash'];
        $current_model = 'gemini-3.6-flash';

        $filtered = array_filter($candidate_fallbacks, fn($c) => $c !== $current_model);

        $this->assertNotContains('gemini-3.6-flash', $filtered);
        $this->assertContains('gemini-3.5-flash', $filtered);
        $this->assertContains('gemini-2.5-flash', $filtered);
    }

    public function test_candidate_fallbacks_retain_all_alternatives_when_on_flagship(): void
    {
        $candidate_fallbacks = ['gemini-3.6-flash', 'gemini-3.5-flash', 'gemini-2.5-flash'];
        $current_model = 'gemini-3.8-flash';

        $filtered = array_filter($candidate_fallbacks, fn($c) => $c !== $current_model);

        $this->assertCount(3, $filtered);
        $this->assertSame($candidate_fallbacks, array_values($filtered));
    }
}
