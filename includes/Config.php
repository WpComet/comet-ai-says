<?php

namespace WpComet\AISays;

defined('ABSPATH') || exit;

/**
 * Central Configuration, Model Definitions, and Localization Data.
 */
class Config
{
    // Primary Unified Option Name in wp_options
    public const OPTION_SETTINGS             = 'wpcmt_aisays_settings';
    public const TRANSIENT_SKIP_ONBOARDING    = 'wpcmt_aisays_onboarding_skipped';
    public const TRANSIENT_ACTIVATION_REDIRECT = 'wpcmt_aisays_activation_redirect';

    // Setting Array Keys
    public const KEY_PROVIDER          = 'provider';
    public const KEY_GEMINI_KEY        = 'gemini_api_key';
    public const KEY_OPENAI_KEY        = 'openai_api_key';
    public const KEY_LANGUAGE          = 'language';
    public const KEY_CUSTOM_LANGUAGE   = 'custom_language';
    public const KEY_GEMINI_MODEL      = 'gemini_model';
    public const KEY_OPENAI_MODEL      = 'openai_model';
    public const KEY_DISPLAY_MODE      = 'display_mode';
    public const KEY_DISPLAY_POSITION  = 'display_position';
    public const KEY_SHORTCODE         = 'shortcode';
    public const KEY_PROMPT_TEMPLATE   = 'prompt_template';
    public const KEY_MAX_TOKENS        = 'max_tokens';
    public const KEY_WEBHOOK_URL       = 'webhook_url';

    // Backward-Compatible Option Key Aliases
    public const OPTION_PROVIDER          = self::KEY_PROVIDER;
    public const OPTION_GEMINI_KEY        = self::KEY_GEMINI_KEY;
    public const OPTION_OPENAI_KEY        = self::KEY_OPENAI_KEY;
    public const OPTION_LANGUAGE          = self::KEY_LANGUAGE;
    public const OPTION_CUSTOM_LANGUAGE   = self::KEY_CUSTOM_LANGUAGE;
    public const OPTION_GEMINI_MODEL      = self::KEY_GEMINI_MODEL;
    public const OPTION_OPENAI_MODEL      = self::KEY_OPENAI_MODEL;
    public const OPTION_DISPLAY_MODE      = self::KEY_DISPLAY_MODE;
    public const OPTION_DISPLAY_POSITION  = self::KEY_DISPLAY_POSITION;
    public const OPTION_SHORTCODE         = self::KEY_SHORTCODE;
    public const OPTION_PROMPT_TEMPLATE   = self::KEY_PROMPT_TEMPLATE;
    public const OPTION_MAX_TOKENS        = self::KEY_MAX_TOKENS;
    public const OPTION_WEBHOOK_URL       = self::KEY_WEBHOOK_URL;

    // Model Rate Limits (RPM: Requests/Min, TPM: Tokens/Min, RPD: Requests/Day)
    public const MODEL_LIMITS = [
        '3.8-flash'       => ['rpm' => 15, 'tpm' => 1000000, 'rpd' => 1500],
        '3.7-flash'       => ['rpm' => 15, 'tpm' => 1000000, 'rpd' => 1500],
        '3.6-flash'       => ['rpm' => 15, 'tpm' => 1000000, 'rpd' => 1500],
        '3.5-flash-lite'  => ['rpm' => 30, 'tpm' => 1000000, 'rpd' => 1500],
        '3.5-flash'       => ['rpm' => 15, 'tpm' => 1000000, 'rpd' => 1500],
        '3.1-flash'       => ['rpm' => 15, 'tpm' => 1000000, 'rpd' => 1500],
        '3.0-flash'       => ['rpm' => 15, 'tpm' => 1000000, 'rpd' => 200],
        '3.1-pro'         => ['rpm' => 5,  'tpm' => 125000,  'rpd' => 100],
    ];

    // Supported Languages: [English Name, Native Name]
    public const LANGUAGE_DATA = [
        'english'    => ['English', 'English'],
        'spanish'    => ['Spanish', 'Español'],
        'french'     => ['French', 'Français'],
        'german'     => ['German', 'Deutsch'],
        'italian'    => ['Italian', 'Italiano'],
        'portuguese' => ['Portuguese', 'Português'],
        'dutch'      => ['Dutch', 'Nederlands'],
        'russian'    => ['Russian', 'Русский'],
        'japanese'   => ['Japanese', '日本語'],
        'korean'     => ['Korean', '한국어'],
        'chinese'    => ['Chinese', '中文'],
        'arabic'     => ['Arabic', 'العربية'],
        'turkish'    => ['Turkish', 'Türkçe'],
        'hindi'      => ['Hindi', 'हिन्दी'],
    ];

    /**
     * In-memory cache for settings.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $settings_cache = null;

    /**
     * Clear in-memory settings cache.
     */
    public static function clear_cache(): void
    {
        self::$settings_cache = null;
    }

    /**
     * Get default plugin configuration options.
     *
     * @return array<string, mixed>
     */
    public static function get_default_options(): array
    {
        return [
            self::KEY_PROVIDER         => 'gemini',
            self::KEY_GEMINI_KEY       => '',
            self::KEY_OPENAI_KEY       => '',
            self::KEY_LANGUAGE         => 'english',
            self::KEY_CUSTOM_LANGUAGE  => '',
            self::KEY_GEMINI_MODEL     => 'gemini-3.6-flash',
            self::KEY_OPENAI_MODEL     => 'gpt-4o',
            self::KEY_DISPLAY_MODE     => 'automatic',
            self::KEY_DISPLAY_POSITION => 'after_description',
            self::KEY_SHORTCODE        => '[comet-ai-says-product-description]',
            self::KEY_PROMPT_TEMPLATE  => self::get_default_prompt_template(),
            self::KEY_MAX_TOKENS       => 1500,
            self::KEY_WEBHOOK_URL      => '',
        ];
    }

    /**
     * Get all plugin settings merged with defaults.
     *
     * @return array<string, mixed>
     */
    public static function get_settings(): array
    {
        if (null !== self::$settings_cache) {
            return self::$settings_cache;
        }

        $defaults = self::get_default_options();
        $stored   = get_option(self::OPTION_SETTINGS, []);

        self::$settings_cache = is_array($stored) ? array_merge($defaults, $stored) : $defaults;

        return self::$settings_cache;
    }

    /**
     * Get a specific setting value.
     *
     * @param string $key Setting key.
     * @param mixed  $default Optional default if not set.
     * @return mixed
     */
    public static function get_option(string $key, $default = null)
    {
        $settings = self::get_settings();
        return $settings[$key] ?? $default;
    }

    /**
     * Update a single setting or multiple settings.
     *
     * @param string|array<string, mixed> $key_or_array
     * @param mixed                       $value
     * @return bool
     */
    public static function update_setting($key_or_array, $value = null): bool
    {
        $settings = self::get_settings();

        if (is_array($key_or_array)) {
            foreach ($key_or_array as $k => $v) {
                $settings[$k] = $v;
            }
        } else {
            $settings[$key_or_array] = $value;
        }

        self::$settings_cache = $settings;

        return update_option(self::OPTION_SETTINGS, $settings);
    }

    /**
     * Update all settings at once.
     *
     * @param array<string, mixed> $new_settings
     * @return bool
     */
    public static function update_settings(array $new_settings): bool
    {
        return self::update_setting($new_settings);
    }

    /**
     * Get default prompt template.
     */
    public static function get_default_prompt_template(): string
    {
        return "{introduction}\n\n" .
            "PRODUCT DETAILS:\n" .
            "- Product Name: {product_name}\n" .
            "- Short Description: {short_description}\n" .
            "- Categories: {categories}\n" .
            "- Tags: {tags}\n" .
            "- Store: {store_context}\n\n" .
            "SPECIFICATIONS & ATTRIBUTES:\n" .
            "{attributes}\n\n" .
            "VISUAL ANALYSIS:\n" .
            "{image_analysis}\n\n" .
            "INSTRUCTIONS:\n" .
            "{instructions}\n" .
            "- Format with clean HTML paragraphs (<p>) and bullet points (<ul><li>) where appropriate.\n" .
            "- Focus on benefits to the customer and practical use cases.";
    }

    /**
     * Get built-in prompt template presets for different store niches and tones.
     *
     * @return array<string, array{name: string, description: string, template: string}>
     */
    public static function get_prompt_presets(): array
    {
        return [
            'default' => [
                'name'        => __('Default (Balanced & Engaging)', 'comet-ai-says'),
                'description' => __('Standard versatile e-commerce copy balancing features, benefits, and clean HTML formatting.', 'comet-ai-says'),
                'template'    => self::get_default_prompt_template(),
            ],
            'luxury_elegant' => [
                'name'        => __('Luxury & Elegant', 'comet-ai-says'),
                'description' => __('Sophisticated, sensory, and aspirational copy emphasizing exclusivity, premium craftsmanship, and elegance.', 'comet-ai-says'),
                'template'    => "{introduction}\n\n" .
                    "PRODUCT DETAILS:\n" .
                    "- Product Name: {product_name}\n" .
                    "- Short Description: {short_description}\n" .
                    "- Categories: {categories}\n" .
                    "- Tags: {tags}\n" .
                    "- Store: {store_context}\n\n" .
                    "SPECIFICATIONS & ATTRIBUTES:\n" .
                    "{attributes}\n\n" .
                    "VISUAL ANALYSIS:\n" .
                    "{image_analysis}\n\n" .
                    "INSTRUCTIONS:\n" .
                    "{instructions}\n" .
                    "- Tone: Refined, elegant, evocative, and luxurious. Appeal to sensory details, meticulous craftsmanship, and discerning taste.\n" .
                    "- Format with graceful HTML paragraphs (<p>) and an optional curated highlights list (<ul><li>) focusing on distinctive qualities.\n" .
                    "- Highlight the emotional prestige, timeless appeal, and premium materials of the product.",
            ],
            'short_punchy' => [
                'name'        => __('Short & Punchy (Social & Mobile)', 'comet-ai-says'),
                'description' => __('Bite-sized, high-energy, mobile-optimized descriptions designed for fast scanning and quick conversions.', 'comet-ai-says'),
                'template'    => "{introduction}\n\n" .
                    "PRODUCT DETAILS:\n" .
                    "- Product Name: {product_name}\n" .
                    "- Short Description: {short_description}\n" .
                    "- Categories: {categories}\n" .
                    "- Tags: {tags}\n" .
                    "- Store: {store_context}\n\n" .
                    "SPECIFICATIONS & ATTRIBUTES:\n" .
                    "{attributes}\n\n" .
                    "VISUAL ANALYSIS:\n" .
                    "{image_analysis}\n\n" .
                    "INSTRUCTIONS:\n" .
                    "{instructions}\n" .
                    "- Tone: Energetic, direct, punchy, and confident. Zero fluff or filler words.\n" .
                    "- Length: Maximum 100-150 words. Ideal for mobile shoppers and social storefronts.\n" .
                    "- Structure: Start with an attention-grabbing one-line hook (<p><strong>...</strong></p>), followed by 3-4 bold, actionable bullet points (<ul><li><strong>Feature:</strong> Benefit</li></ul>), and end with a snappy closing sentence.",
            ],
            'technical_specs' => [
                'name'        => __('Technical & Specs-Focused', 'comet-ai-says'),
                'description' => __('Structured, authoritative, and fact-driven copy highlighting engineering, compatibility, metrics, and build quality.', 'comet-ai-says'),
                'template'    => "{introduction}\n\n" .
                    "PRODUCT DETAILS:\n" .
                    "- Product Name: {product_name}\n" .
                    "- Short Description: {short_description}\n" .
                    "- Categories: {categories}\n" .
                    "- Tags: {tags}\n" .
                    "- Store: {store_context}\n\n" .
                    "SPECIFICATIONS & ATTRIBUTES:\n" .
                    "{attributes}\n\n" .
                    "VISUAL ANALYSIS:\n" .
                    "{image_analysis}\n\n" .
                    "INSTRUCTIONS:\n" .
                    "{instructions}\n" .
                    "- Tone: Authoritative, objective, informative, and precise. Focus on utility, performance, and durability.\n" .
                    "- Structure: Provide a clear overview paragraph highlighting primary applications, followed by a structured specification list (<ul><li><strong>Spec:</strong> Value</li></ul>).\n" .
                    "- Emphasize compatibility, materials, engineering standards, and exact dimensions/performance metrics from the product data.",
            ],
            'seo_benefits' => [
                'name'        => __('SEO & Benefit-Driven', 'comet-ai-says'),
                'description' => __('Search-optimized copywriting structured with semantic headings, natural keywords, and problem/solution benefits.', 'comet-ai-says'),
                'template'    => "{introduction}\n\n" .
                    "PRODUCT DETAILS:\n" .
                    "- Product Name: {product_name}\n" .
                    "- Short Description: {short_description}\n" .
                    "- Categories: {categories}\n" .
                    "- Tags: {tags}\n" .
                    "- Store: {store_context}\n\n" .
                    "SPECIFICATIONS & ATTRIBUTES:\n" .
                    "{attributes}\n\n" .
                    "VISUAL ANALYSIS:\n" .
                    "{image_analysis}\n\n" .
                    "INSTRUCTIONS:\n" .
                    "{instructions}\n" .
                    "- Tone: Persuasive, informative, and customer-centric.\n" .
                    "- SEO & Structure: Naturally weave in product category and keyword context. Use clean semantic HTML (<h3>Why You'll Love It</h3>, <h3>Key Features</h3>, <p>, <ul><li>).\n" .
                    "- Focus on the problem solved, real-world customer benefits, and address common buying hesitations clearly.",
            ],
            'storyteller' => [
                'name'        => __('Storyteller & Artisan (Boutique)', 'comet-ai-says'),
                'description' => __('Warm, narrative-led copywriting connecting customers to the inspiration, craft, and heartfelt story behind the piece.', 'comet-ai-says'),
                'template'    => "{introduction}\n\n" .
                    "PRODUCT DETAILS:\n" .
                    "- Product Name: {product_name}\n" .
                    "- Short Description: {short_description}\n" .
                    "- Categories: {categories}\n" .
                    "- Tags: {tags}\n" .
                    "- Store: {store_context}\n\n" .
                    "SPECIFICATIONS & ATTRIBUTES:\n" .
                    "{attributes}\n\n" .
                    "VISUAL ANALYSIS:\n" .
                    "{image_analysis}\n\n" .
                    "INSTRUCTIONS:\n" .
                    "{instructions}\n" .
                    "- Tone: Warm, authentic, narrative, and inspiring. Connect the customer to the inspiration and care behind the product.\n" .
                    "- Highlight the textures, handmade or curated essence, lifestyle feeling, and the joy of owning or gifting this item.\n" .
                    "- Format with evocative narrative paragraphs (<p>) and a thoughtful summary of crafted details.",
            ],
        ];
    }

    /**
     * Get Gemini model migration mappings for older/sunset models.
     */
    public static function get_gemini_model_mappings(): array
    {
        return [
            'gemini-flash-latest'          => 'gemini-flash-latest',
            'gemini-3.8-flash'             => 'gemini-3.8-flash',
            'gemini-3.7-flash'             => 'gemini-3.7-flash',
            'gemini-3.6-flash'             => 'gemini-3.6-flash',
            'gemini-3.5-flash'             => 'gemini-3.5-flash',
            'gemini-3.5-flash-lite'        => 'gemini-3.5-flash-lite',
            'gemini-3.1-flash-lite'        => 'gemini-3.1-flash-lite',
            'gemini-3.1-flash-lite-preview'=> 'gemini-3.1-flash-lite',
            'gemini-3.1-flash-preview'     => 'gemini-3.6-flash',
            'gemini-3.0-flash'             => 'gemini-3.6-flash',
            'gemini-3-flash-preview'       => 'gemini-3-flash-preview',
            'gemini-3.1-pro'               => 'gemini-3.1-pro-preview',
            'gemini-2.5-flash'             => 'gemini-3.6-flash',
            'gemini-2.5-pro'               => 'gemini-3.1-pro-preview',
            'gemini-2.5-flash-lite'        => 'gemini-3.5-flash-lite',
            'gemini-2.0-flash'             => 'gemini-3.6-flash',
            'gemini-1.5-flash'             => 'gemini-3.6-flash',
            'gemini-1.5-pro'               => 'gemini-3.1-pro-preview',
        ];
    }

    /**
     * Normalize Gemini model name.
     */
    public static function normalize_gemini_model(string $model): string
    {
        $mappings = self::get_gemini_model_mappings();
        return $mappings[$model] ?? $model;
    }

    /**
     * Available Gemini models for the settings selector.
     * Sourced from official Google documentation: https://ai.google.dev/gemini-api/docs/models
     */
    public static function get_gemini_models(): array
    {
        return [
            'gemini-3.6-flash' => [
                'name'        => 'Gemini 3.6 Flash',
                'description' => __('Google\'s highly stable, versatile Flash model — multimodal vision, fast throughput, and reliable catalog copy (Recommended default).', 'comet-ai-says'),
                'tier'        => 'Gemini 3 Series (Recommended Stable)',
                'recommended' => true,
                'badges'      => [
                    ['text' => __('Recommended', 'comet-ai-says'), 'class' => 'is-primary'],
                    ['text' => __('Stable', 'comet-ai-says'), 'class' => 'is-success is-light'],
                ],
                'icon'        => '✦',
                'quota'       => '15 RPM • 1M TPM',
            ],
            'gemini-3.8-flash' => [
                'name'        => 'Gemini 3.8 Flash',
                'description' => __('Google\'s latest Flash model with deep reasoning. Note: Currently experiencing temporary free-tier throttling / high demand.', 'comet-ai-says'),
                'tier'        => 'Gemini 3 Series (Latest)',
                'badges'      => [
                    ['text' => __('New', 'comet-ai-says'), 'class' => 'is-info is-light'],
                    ['text' => __('High Demand', 'comet-ai-says'), 'class' => 'is-warning is-light'],
                ],
                'icon'        => '✦',
                'quota'       => '15 RPM • 1M TPM',
            ],
            'gemini-3.7-flash' => [
                'name'        => 'Gemini 3.7 Flash',
                'description' => __('Stable Flash model for complex coding, agentic workflows, and reliable multi-step execution.', 'comet-ai-says'),
                'tier'        => 'Gemini 3 Series',
                'badges'      => [
                    ['text' => __('Stable', 'comet-ai-says'), 'class' => 'is-success is-light'],
                ],
                'icon'        => '✦',
                'quota'       => '15 RPM • 1M TPM',
            ],
            'gemini-3.5-flash-lite' => [
                'name'        => 'Gemini 3.5 Flash-Lite',
                'description' => __('Fastest, most cost-effective model built for high-throughput product catalogs and bulk runs.', 'comet-ai-says'),
                'tier'        => 'Gemini 3 Series',
                'badges'      => [
                    ['text' => __('High Volume', 'comet-ai-says'), 'class' => 'is-warning is-light'],
                ],
                'icon'        => '⚡',
                'quota'       => '30 RPM • 1M TPM',
            ],
            'gemini-3.5-flash' => [
                'name'        => 'Gemini 3.5 Flash',
                'description' => __('Legacy stable Flash model providing baseline speed and foundational description performance.', 'comet-ai-says'),
                'tier'        => 'Gemini 3 Series (Legacy)',
                'badges'      => [
                    ['text' => __('Legacy', 'comet-ai-says'), 'class' => 'is-light'],
                ],
                'icon'        => '✦',
                'quota'       => '15 RPM • 1M TPM',
            ],
            'gemini-3.1-flash-lite' => [
                'name'        => 'Gemini 3.1 Flash-Lite',
                'description' => __('Frontier-class performance rivaling larger models at minimal cost overhead.', 'comet-ai-says'),
                'tier'        => 'Gemini 3 Series (Legacy)',
                'badges'      => [
                    ['text' => __('Legacy', 'comet-ai-says'), 'class' => 'is-light'],
                ],
                'icon'        => '⚡',
                'quota'       => '15 RPM • 1M TPM',
            ],
            'gemini-3.1-pro-preview' => [
                'name'        => 'Gemini 3.1 Pro',
                'description' => __('Deep reasoning model recommended when nuanced copy, creative storytelling, or complex specs are required.', 'comet-ai-says'),
                'tier'        => 'Gemini 3 Series (Pro Reasoning)',
                'badges'      => [
                    ['text' => __('Pro Reasoning', 'comet-ai-says'), 'class' => 'is-link is-light'],
                ],
                'icon'        => '◈',
                'quota'       => '2 RPM • 30K TPM',
            ],
            'gemini-flash-latest' => [
                'name'        => 'Gemini Flash Latest',
                'description' => __('Auto-routing alias — dynamically selects the newest Flash model supported by your API key.', 'comet-ai-says'),
                'tier'        => 'Auto-Routing Alias',
                'badges'      => [
                    ['text' => __('Auto-Routing', 'comet-ai-says'), 'class' => 'is-primary is-light'],
                ],
                'icon'        => '↻',
                'quota'       => 'Dynamic Quota',
            ],
        ];
    }

    /**
     * Available OpenAI models for the settings selector.
     */
    public static function get_openai_models(): array
    {
        return [
            'gpt-4o' => [
                'name'        => 'GPT-4o',
                'description' => __('Flagship multimodal model with vision capabilities, nuance, and high reasoning.', 'comet-ai-says'),
                'tier'        => 'Flagship Models',
                'recommended' => true,
                'badges'      => [
                    ['text' => __('Recommended', 'comet-ai-says'), 'class' => 'is-primary'],
                    ['text' => __('Multimodal', 'comet-ai-says'), 'class' => 'is-info is-light'],
                ],
                'icon'        => '✦',
                'quota'       => 'Tier Based',
            ],
            'gpt-4o-mini' => [
                'name'        => 'GPT-4o Mini',
                'description' => __('Fast, cost-effective multimodal model for high-volume catalogs and bulk generation.', 'comet-ai-says'),
                'tier'        => 'Flagship Models',
                'badges'      => [
                    ['text' => __('Fast & Efficient', 'comet-ai-says'), 'class' => 'is-success is-light'],
                ],
                'icon'        => '⚡',
                'quota'       => 'Tier Based',
            ],
            'o1-mini' => [
                'name'        => 'o1-mini',
                'description' => __('Specialized reasoning model for complex attributes and highly technical product data.', 'comet-ai-says'),
                'tier'        => 'Reasoning Models',
                'badges'      => [
                    ['text' => __('Reasoning', 'comet-ai-says'), 'class' => 'is-link is-light'],
                ],
                'icon'        => '◈',
                'quota'       => 'Tier Based',
            ],
            'gpt-4-turbo' => [
                'name'        => 'GPT-4 Turbo',
                'description' => __('Previous flagship GPT-4 model with wide context window.', 'comet-ai-says'),
                'tier'        => 'Legacy Models',
                'badges'      => [
                    ['text' => __('Legacy', 'comet-ai-says'), 'class' => 'is-light'],
                ],
                'icon'        => '✦',
                'quota'       => 'Tier Based',
            ],
            'gpt-3.5-turbo' => [
                'name'        => 'GPT-3.5 Turbo',
                'description' => __('Fast and economical text-only model (no vision support).', 'comet-ai-says'),
                'tier'        => 'Legacy Models',
                'badges'      => [
                    ['text' => __('Text Only', 'comet-ai-says'), 'class' => 'is-light'],
                ],
                'icon'        => '⚡',
                'quota'       => 'Tier Based',
            ],
        ];
    }

    /**
     * Get model rate limits for usage tracking.
     */
    public static function get_model_limits(string $model): array
    {
        foreach (self::MODEL_LIMITS as $key => $limits) {
            if (strpos($model, $key) !== false) {
                return $limits;
            }
        }

        return ['rpm' => 15, 'tpm' => 1000000, 'rpd' => 1500];
    }

    /**
     * Get language-specific prompt components.
     */
    public static function get_language_part(string $language, string $part = 'intro'): string
    {
        $introductions = [
            'english'    => 'Write a compelling product description in English. Use a professional, engaging tone suitable for e-commerce. Highlight key features and benefits.',
            'spanish'    => 'Escribe una descripción de producto convincente en español. Utiliza un tono profesional y atractivo adecuado para el comercio electrónico. Destaca las características clave y los beneficios.',
            'french'     => 'Rédigez une description de produit convaincante en français. Utilisez un ton professionnel et engageant adapté au commerce électronique. Mettez en avant les caractéristiques clés et les avantages.',
            'german'     => 'Verfassen Sie eine überzeugende Produktbeschreibung auf Deutsch. Verwenden Sie einen professionellen, ansprechenden Ton, der für den E-Commerce geeignet ist. Heben Sie die wichtigsten Funktionen und Vorteile hervor.',
            'italian'    => 'Scrivi una descrizione del prodotto convincente in italiano. Usa un tono professionale e coinvolgente adatto per l\'e-commerce. Evidenzia le caratteristiche principali e i benefici.',
            'portuguese' => 'Escreva uma descrição convincente do produto em português. Use um tom profissional e atraente adequado para o comércio eletrônico. Destaque os principais recursos e benefícios.',
            'dutch'      => 'Schrijf een overtuigende productbeschrijving in het Nederlands. Gebruik een professionele, boeiende toon die geschikt is voor e-commerce. Benadruk de belangrijkste kenmerken en voordelen.',
            'russian'    => 'Напишите убедительное описание товара на русском языке. Используйте профессиональный, привлекательный тон, подходящий для электронной коммерции. Выделите ключевые особенности и преимущества.',
            'japanese'   => '日本語で説得力のある商品説明を書いてください。Eコマースに適したプロフェッショナルで魅力的なトーンを使用してください。主な機能と利点を強調してください。',
            'korean'     => '한국어로 매력적인 제품 설명을 작성해 주세요. 전자상거래에 적합한 전문적이고 매력적인 어조를 사용하세요. 주요 기능과 이점을 강조하세요.',
            'chinese'    => '用中文撰写有说服力的产品描述。使用适合电子商务的专业、引人入胜的语气。突出关键特性和优势。',
            'arabic'     => 'اكتب وصفًا مقنعًا للمنتج باللغة العربية. استخدم نبرة احترافية وجذابة مناسبة للتجارة الإلكترونية. سلط الضوء على الميزات والفوائد الرئيسية.',
            'turkish'    => 'Etkileyici bir ürün açıklaması yaz: E-ticaret için uygun, profesyonel ve ilgi çekici bir ton kullan. Temel özellikleri ve faydaları vurgula.',
            'hindi'      => 'हिंदी में एक आकर्षक उत्पाद विवरण लिखें। ई-कॉमर्स के लिए उपयुक्त एक पेशेवर, आकर्षक स्वर का उपयोग करें। मुख्य विशेषताओं और लाभों पर प्रकाश डालें।',
            'custom'     => 'Write a compelling product description in CUSTOM_LANGUAGE. Use a professional, engaging tone suitable for e-commerce. Highlight key features and benefits.',
        ];

        $instructions = [
            'english'    => "- Keep it concise but persuasive (about 150-200 words).\n- Do NOT add any introductory phrases like \"Here is...\", \"I present...\", \"This product...\", etc.",
            'spanish'    => "- Manténgalo conciso pero persuasivo (aproximadamente 150-200 palabras).\n- NO agregue frases introductorias como \"Aquí está...\", \"Presento...\", \"Este producto...\", etc.",
            'french'     => "- Soyez concis mais persuasif (environ 150-200 mots).\n- N'ajoutez PAS de phrases introductives comme \"Voici...\", \"Je présente...\", \"Ce produit...\", etc.",
            'german'     => "- Fassen Sie sich kurz, aber überzeugend (etwa 150-200 Wörter).\n- Fügen Sie KEINE einleitenden Sätze wie \"Hier ist...\", \"Ich präsentiere...\", \"Dieses Produkt...\" usw. hinzu.",
            'italian'    => "- Sii conciso ma persuasivo (circa 150-200 parole).\n- NON aggiungere frasi introduttive come \"Ecco...\", \"Presento...\", \"Questo prodotto...\", ecc.",
            'portuguese' => "- Mantenha conciso, mas persuasivo (cerca de 150-200 palavras).\n- NÃO adicione frases introdutórias como \"Aqui está...\", \"Apresento...\", \"Este produto...\", etc.",
            'dutch'      => "- Houd het beknopt maar overtuigend (ongeveer 150-200 woorden).\n- Voeg GEEN inleidende zinnen toe zoals \"Hier is...\", \"Ik presenteer...\", \"Dit product...\", etc.",
            'russian'    => "- Будьте лаконичны, но убедительны (около 150-200 слов).\n- НЕ добавляйте вводные фразы, такие как \"Вот...\", \"Представляю...\", \"Этот продукт...\" и т.д.",
            'japanese'   => "- 簡潔かつ説得力のある文章にしてください（約150〜200語）。\n- 「こちらが...」「ご紹介します...」「この商品は...」などの導入句を追加しないでください",
            'korean'     => "- 간결하지만 설득력 있게 작성하세요 (약 150-200단어).\n- \"여기...\", \"소개합니다...\", \"이 제품은...\" 등의 도입 문구를 추가하지 마세요",
            'chinese'    => "- 保持简洁但有说服力（约150-200字）。\n- 不要添加任何介绍性短语，如\"这是...\"、\"我介绍...\"、\"本产品...\"等。",
            'arabic'     => "- اجعلها موجزة ولكن مقنعة (حوالي 150-200 كلمة).\n- لا تضيف أي عبارات تمهيدية مثل \"ها هو...\"، \"أقدم...\"، \"هذا المنتج...\"، إلخ.",
            'turkish'    => "- Kısa ama ikna edici olun (yaklaşık 150-200 kelime).\n- 'İşte ürün açıklamanız' veya 'Bu ürün şudur' gibi gereksiz kalıp giriş ifadeleri EKLEME.",
            'hindi'      => "- संक्षिप्त लेकिन प्रेरक रखें (लगभग 150-200 शब्द).\n- \"यहाँ है...\", \"मैं प्रस्तुत करता हूँ...\", \"यह उत्पाद...\" आदि जैसे किसी भी परिचयात्मक वाक्यांश को न जोड़ें।",
            'custom'     => "- Keep it concise but persuasive (about 150-200 words).\n- Do NOT add any introductory phrases like \"Here is...\", \"I present...\", \"This product...\", etc.",
        ];

        $data = ('intro' === $part) ? $introductions : $instructions;
        $text = $data[$language] ?? $data['english'];

        if ('custom' === $language && 'intro' === $part) {
            $custom_language = get_option(self::OPTION_CUSTOM_LANGUAGE, 'English');
            $text = str_replace('CUSTOM_LANGUAGE', $custom_language, $text);
        }

        return $text;
    }
}
