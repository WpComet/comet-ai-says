(function ($) {
  "use strict";

  // Toggle password visibility
  window.toggleVisibility = function (fieldId) {
    var field = document.getElementById(fieldId);
    var button = field.nextElementSibling;

    if (field.classList.contains("masked")) {
      // Show the actual text
      field.classList.remove("masked");
      field.style.webkitTextSecurity = "none";
      field.style.textSecurity = "none";
      button.textContent = wpcmt_aisays.i18n.hide || "Hide";
    } else {
      // Mask the text
      field.classList.add("masked");
      field.style.webkitTextSecurity = "disc";
      field.style.textSecurity = "disc";
      button.textContent = wpcmt_aisays.i18n.show || "Show";
    }
  };

  $(document).ready(function ($) {
    // Provider change handler
    $("#wpcmt_aisays_provider").on("change", function () {
      var provider = $(this).val();
      $("#gemini-model-row, #gemini-api-key-row").toggle(provider === "gemini");
      $("#openai-model-row, #openai-api-key-row").toggle(provider === "openai");
    });

    // Language change handler
    $("#wpcmt_aisays_language").on("change", function () {
      $("#custom-language-row").toggle($(this).val() === "custom");
      updatePromptPreview();
    });

    // Custom language and prompt template handlers
    $("#wpcmt_aisays_custom_language, #wpcmt_aisays_prompt_template").on("input", updatePromptPreview);

    // Display mode change handler
    $("#wpcmt_aisays_display_mode").on("change", function () {
      var isAutomatic = $(this).val() === "automatic";
      $("#display-position-row").toggle(isAutomatic);
      $("#shortcode-row").toggle(!isAutomatic);
    });

    // Model change handler for token ranges
    $("#wpcmt_aisays_gemini_model").on("change", function () {
      updateTokenRange();
      updateCapacityInfo();
    });

    // Token slider handler
    $("#wpcmt_aisays_max_tokens").on("input", function () {
      $("#max-tokens-value").text($(this).val() + " " + (wpcmt_aisays.i18n.tokens || "tokens"));
    });

    // Settings search
    $("#comet-settings-search").on("keyup", function () {
      var searchText = $(this).val().toLowerCase();
      if (searchText.length >= 2) {
        $(".form-table tr").each(function () {
          $(this).toggle($(this).text().toLowerCase().indexOf(searchText) > -1);
        });
      } else {
        $(".form-table tr").show();
      }
    });

    // Initialize
    updateTokenRange();
    updatePromptPreview();
    var apifieldid = document.querySelector(".api-key-field").id;
    toggleVisibility(apifieldid);
  });

  window.updatePromptPreview = function () {
    var template = $("#wpcmt_aisays_prompt_template").val();
    var language = $("#wpcmt_aisays_language").val();
    var customLanguage = $("#wpcmt_aisays_custom_language").val();

    var introduction = getLanguageInstruction(language, "intro", customLanguage);
    var instructions = getLanguageInstruction(language, "instructions", customLanguage);

    var preview = template
      .replace(/{introduction}/g, introduction)
      .replace(/{instructions}/g, instructions)
      .replace(/{product_name}/g, "Sample Product Name")
      .replace(/{short_description}/g, "Sample short description")
      .replace(/{categories}/g, "Sample Category")
      .replace(/{attributes}/g, "- Color: Red\n- Size: Large")
      .replace(/{image_analysis}/g, "Sample image analysis");

    if (template.trim() !== "") {
      $("#preview-content").text(preview);
      $("#prompt-preview").show();
    } else {
      $("#prompt-preview").hide();
    }
  };

  window.getLanguageInstruction = function (language, part, customLanguage) {
    var instruction = wpcmt_aisays.languageData[part][language] || wpcmt_aisays.languageData[part]["english"];
    if (language === "custom" && part === "intro" && customLanguage) {
      instruction = instruction.replace("CUSTOM_LANGUAGE", customLanguage);
    } else if (language === "custom" && part === "intro") {
      instruction = instruction.replace("CUSTOM_LANGUAGE", "Custom Language");
    }
    return instruction;
  };

  window.updateTokenRange = function () {
    var geminiModel = $("#wpcmt_aisays_gemini_model").val();
    var maxTokensInput = $("#wpcmt_aisays_max_tokens");
    var tokensValue = $("#max-tokens-value");
    var recommended = $("#recommended-tokens");
    var capacityInfo = $("#token-capacity-info");

    var configs = {
      // --- 3.0 Generation ---
      "gemini-3.0-pro-preview": {
        min: 1500,
        max: 7000,
        default: 3000,
        rec: wpcmt_aisays.i18n.tokens_2000_8000 || "2000-8000 tokens for complex reasoning (Pro)",
        cap: wpcmt_aisays.i18n.cap_30k_2 || "30K TPM, 2 RPM",
      },
      "gemini-3.0-flash": {
        min: 1000,
        max: 4000,
        default: 2000,
        rec: wpcmt_aisays.i18n.tokens_1000_4000 || "1000-4000 tokens for balanced performance",
        cap: wpcmt_aisays.i18n.cap_1m_15 || "1M TPM, 15 RPM",
      },

      // --- 2.5 Generation ---
      "gemini-2.5-pro": {
        min: 1500,
        max: 7000,
        default: 3000,
        rec: wpcmt_aisays.i18n.tokens_2000_8000 || "2000-8000 tokens for high-quality analysis",
        cap: wpcmt_aisays.i18n.cap_32k_2 || "32K TPM, 2 RPM",
      },
      "gemini-2.5-flash-lite": {
        min: 800,
        max: 2000,
        default: 1200,
        rec: wpcmt_aisays.i18n.tokens_800_2000 || "800-2000 tokens for lightweight/fast tasks",
        cap: wpcmt_aisays.i18n.cap_1m_30 || "1M TPM, 30 RPM (High Volume)",
      },
      "gemini-2.5-flash": {
        min: 1000,
        max: 4000,
        default: 2500,
        rec: wpcmt_aisays.i18n.tokens_1000_4000 || "1000-4000 tokens for balanced performance",
        cap: wpcmt_aisays.i18n.cap_1m_15 || "1M TPM, 15 RPM",
      },

      // --- Legacy / Fallbacks ---
      "gemini-3.0-flash-thinking": { // Legacy fallback
        min: 2000,
        max: 6000,
        default: 3000,
        rec: wpcmt_aisays.i18n.tokens_2000_6000 || "2000-6000 tokens for thinking models",
        cap: wpcmt_aisays.i18n.cap_30k_2 || "30K TPM, 2 RPM",
      },
      "gemini-2.0-flash": {
        min: 1000,
        max: 4000,
        default: 2500,
        rec: wpcmt_aisays.i18n.tokens_1000_4000 || "1000-4000 tokens for balanced performance",
        cap: wpcmt_aisays.i18n.cap_1m_15 || "1M TPM, 15 RPM",
      },

      // --- Default ---
      default: {
        min: 1000,
        max: 5000,
        default: 2500,
        rec: wpcmt_aisays.i18n.tokens_1000_5000 || "1000-5000 tokens for comprehensive descriptions",
        cap: wpcmt_aisays.i18n.cap_standard || "Standard configuration",
      },
    };

    var config = configs.default;
    // Iterate to find the best matching config
    for (var key in configs) {
      if (key !== "default" && geminiModel.includes(key)) {
        config = configs[key];
        break;
      }
    }

    maxTokensInput.attr("min", config.min).attr("max", config.max);
    recommended.text(config.rec);
    capacityInfo.text(config.cap);

    // Reset to default on change:
    maxTokensInput.val(config.default);

    // Or Alternatively only set default if current value is outside new range
    /*if (currentVal < config.min || currentVal > config.max) {
      maxTokensInput.val(config.default);
    }*/


    tokensValue.text(maxTokensInput.val() + " " + (wpcmt_aisays.i18n.tokens || "tokens"));
  };

  window.updateCapacityInfo = function () {
    var geminiModel = $("#wpcmt_aisays_gemini_model").val();
    var capacityInfo = $("#token-capacity-info");

    var capacityText = wpcmt_aisays.i18n.cap_standard || "Standard configuration";

    // Logic updated to match new slugs and prioritize specific suffixes (like lite/pro)
    if (geminiModel.includes("3.0-pro") || geminiModel.includes("thinking")) {
      // Covers 3.0-pro-preview and legacy thinking slugs
      capacityText = wpcmt_aisays.i18n.cap_30k_2 || "30K TPM, 2 RPM (High Reasoning)";
    } else if (geminiModel.includes("3.0-flash")) {
      capacityText = wpcmt_aisays.i18n.cap_1m_15 || "1M TPM, 15 RPM";
    } else if (geminiModel.includes("2.5-pro")) {
      capacityText = wpcmt_aisays.i18n.cap_32k_2 || "32K TPM, 2 RPM (High Quality)";
    } else if (geminiModel.includes("lite")) {
      // Covers 2.5-flash-lite and 2.0-flash-lite
      capacityText = wpcmt_aisays.i18n.cap_1m_30 || "1M TPM, 30 RPM (High Volume)";
    } else if (geminiModel.includes("flash")) {
      // Catch-all for 2.5-flash, 2.0-flash, etc.
      capacityText = wpcmt_aisays.i18n.cap_1m_15 || "1M TPM, 15 RPM";
    } else if (geminiModel.includes("1.5")) {
      capacityText = "Retired Model";
    }

    capacityInfo.text(capacityText);
  };
})(jQuery);
