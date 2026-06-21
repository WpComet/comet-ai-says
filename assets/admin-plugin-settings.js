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
      // --- 3.5 Generation ---
      "gemini-3.5-flash": {
        min: 1000,
        max: 8000,
        default: 3000,
        rec: wpcmt_aisays.i18n.tokens_1500_5000 || "1000-8000 tokens for detailed descriptions",
        cap: wpcmt_aisays.i18n.cap_1m_15 || "1M TPM, 15 RPM",
      },

      // --- 3.1 / 3.0 Generation ---
      "gemini-3.1-pro-preview": {
        min: 1500,
        max: 7000,
        default: 3000,
        rec: wpcmt_aisays.i18n.tokens_2000_8000 || "2000-8000 tokens for complex reasoning (Pro)",
        cap: wpcmt_aisays.i18n.cap_30k_2 || "30K TPM, 2 RPM",
      },
      "gemini-3.1-flash-lite": {
        min: 1000,
        max: 4000,
        default: 2000,
        rec: wpcmt_aisays.i18n.tokens_1000_4000 || "1000-4000 tokens for fast/high-volume tasks",
        cap: wpcmt_aisays.i18n.cap_1m_30 || "1M TPM, 30 RPM",
      },
      "gemini-3-flash": {
        min: 1000,
        max: 4000,
        default: 2000,
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
    if (geminiModel.includes("3.1-pro")) {
      capacityText = wpcmt_aisays.i18n.cap_30k_2 || "30K TPM, 2 RPM (High Reasoning)";
    } else if (geminiModel.includes("3.5-flash") || geminiModel.includes("3-flash")) {
      capacityText = wpcmt_aisays.i18n.cap_1m_15 || "1M TPM, 15 RPM";
    } else if (geminiModel.includes("lite")) {
      // Covers 3.1-flash-lite
      capacityText = wpcmt_aisays.i18n.cap_1m_30 || "1M TPM, 30 RPM (High Volume)";
    } else if (geminiModel.includes("flash")) {
      // Catch-all for 2.5-flash, etc.
      capacityText = wpcmt_aisays.i18n.cap_1m_15 || "1M TPM, 15 RPM";
    }

    capacityInfo.text(capacityText);
  };
})(jQuery);
