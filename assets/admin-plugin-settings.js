(function ($) {
  "use strict";

  // Toggle password visibility with debounce guard
  var lastToggleTime = 0;
  window.toggleVisibility = function (fieldId) {
    var now = Date.now();
    if (now - lastToggleTime < 200) {
      return;
    }
    lastToggleTime = now;

    var field = typeof fieldId === "string" ? document.getElementById(fieldId) : fieldId;
    if (!field) return;

    var container = field.closest(".pw-wrap") || field.parentElement;
    var btn = container ? container.querySelector(".toggle-key-visibility") : null;
    var icon = btn ? btn.querySelector(".dashicons") : null;
    var textSpan = btn ? btn.querySelector(".toggle-key-text") : null;

    var isMasked = field.classList.contains("masked");

    if (isMasked) {
      // Reveal the key
      field.classList.remove("masked");
      field.style.webkitTextSecurity = "none";
      field.style.textSecurity = "none";

      if (icon) {
        icon.className = "dashicons dashicons-hidden";
      }
      if (textSpan) {
        textSpan.textContent = (window.wpcmt_aisays && wpcmt_aisays.i18n && wpcmt_aisays.i18n.hide) || "Hide";
      }
      if (btn) {
        btn.setAttribute("title", (window.wpcmt_aisays && wpcmt_aisays.i18n && wpcmt_aisays.i18n.hide) || "Hide");
      }
    } else {
      // Conceal the key
      field.classList.add("masked");
      field.style.webkitTextSecurity = "disc";
      field.style.textSecurity = "disc";

      if (icon) {
        icon.className = "dashicons dashicons-visibility";
      }
      if (textSpan) {
        textSpan.textContent = (window.wpcmt_aisays && wpcmt_aisays.i18n && wpcmt_aisays.i18n.show) || "Show";
      }
      if (btn) {
        btn.setAttribute("title", (window.wpcmt_aisays && wpcmt_aisays.i18n && wpcmt_aisays.i18n.show) || "Show");
      }
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

    // Model change handler for token ranges (tiles and selects)
    $(document).on("change", 'input[name="wpcmt_aisays_settings[gemini_model]"], input[name="wpcmt_aisays_gemini_model"], #wpcmt_aisays_gemini_model', function () {
      $('.comet-model-tiles-grid[data-provider="gemini"] .comet-model-tile').removeClass("is-selected");
      $(this).closest(".comet-model-tile").addClass("is-selected");
      updateTokenRange();
      updateCapacityInfo();
    });

    $(document).on("change", 'input[name="wpcmt_aisays_settings[openai_model]"], input[name="wpcmt_aisays_openai_model"], #wpcmt_aisays_openai_model', function () {
      $('.comet-model-tiles-grid[data-provider="openai"] .comet-model-tile').removeClass("is-selected");
      $(this).closest(".comet-model-tile").addClass("is-selected");
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

    // Initialize Theme Icon
    var initialTheme = $("#comet-aisays-root").attr("data-theme") || "light";
    $("#comet-theme-toggle .theme-icon").text(initialTheme === "dark" ? "☀️" : "🌙");

    // Theme Toggle Handler
    $(document).on("click", "#comet-theme-toggle", function (e) {
      e.preventDefault();
      var root = document.getElementById("comet-aisays-root");
      if (!root) return;
      var currentTheme = root.getAttribute("data-theme") || "light";
      var newTheme = currentTheme === "dark" ? "light" : "dark";
      root.setAttribute("data-theme", newTheme);
      try {
        localStorage.setItem("comet-theme-mode", newTheme);
      } catch (err) {}
      $("#comet-theme-toggle .theme-icon").text(newTheme === "dark" ? "☀️" : "🌙");
    });

    // Variable Pills for Prompt Template
    $(document).on("click", ".comet-var-pill", function (e) {
      e.preventDefault();
      var pillVar = $(this).data("var");
      var textarea = document.getElementById("wpcmt_aisays_prompt_template");
      if (!textarea || !pillVar) return;

      var start = textarea.selectionStart || textarea.value.length;
      var end = textarea.selectionEnd || textarea.value.length;
      var text = textarea.value;

      textarea.value = text.substring(0, start) + pillVar + text.substring(end);
      textarea.selectionStart = textarea.selectionEnd = start + pillVar.length;
      textarea.focus();
      updatePromptPreview();
    });

    // Reset to Default Prompt Template
    $(document).on("click", "#wpcmt-aisays-reset-prompt", function (e) {
      e.preventDefault();
      var defaultTpl = $("#comet-default-prompt-template").val();
      if (defaultTpl) {
        $("#wpcmt_aisays_prompt_template").val(defaultTpl);
        if (typeof updatePromptPreview === "function") {
          updatePromptPreview();
        }
      }
    });

    // Toggle Prompt Variables Reference Guide Box
    $(document).on("click", "#comet-toggle-var-guide-btn", function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $guideBox = $("#comet-prompt-vars-guide-box");
      var isVisible = $guideBox.is(":visible");

      $guideBox.slideToggle(200);
      $btn.toggleClass("is-active", !isVisible);
      $btn.attr("aria-expanded", !isVisible ? "true" : "false");
      $btn.find(".comet-guide-chevron").css("transform", !isVisible ? "rotate(180deg)" : "rotate(0deg)");
    });

    // Click delegation for toggle-key-visibility button
    $(document).on("click", ".toggle-key-visibility", function (e) {
      e.preventDefault();
      e.stopPropagation();
      var targetId = $(this).attr("data-target");
      var $input = targetId ? $("#" + targetId) : $(this).closest(".pw-wrap").find(".api-key-field");
      if ($input.length && $input.attr("id")) {
        toggleVisibility($input.attr("id"));
      }
    });

    // Synchronize model tile selection on load
    function syncModelTiles() {
      $('.comet-model-tiles-grid').each(function () {
        var $grid = $(this);
        var $checked = $grid.find('input.comet-tile-radio:checked');
        if ($checked.length) {
          $grid.find('.comet-model-tile').removeClass('is-selected');
          $checked.closest('.comet-model-tile').addClass('is-selected');
        } else {
          var $firstTile = $grid.find('.comet-model-tile').first();
          if ($firstTile.length) {
            $grid.find('.comet-model-tile').removeClass('is-selected');
            $firstTile.addClass('is-selected');
            $firstTile.find('input.comet-tile-radio').prop('checked', true).trigger('change');
          }
        }
      });
    }

    // Initialize
    syncModelTiles();
    updateTokenRange();
    updatePromptPreview();
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
      .replace(/{tags}/g, "Sample Tag")
      .replace(/{store_context}/g, "Sample Store")
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
    var instruction = (wpcmt_aisays.languageData && wpcmt_aisays.languageData[part] && wpcmt_aisays.languageData[part][language]) ||
                      (wpcmt_aisays.languageData && wpcmt_aisays.languageData[part] && wpcmt_aisays.languageData[part]["english"]) || "";
    if (language === "custom" && part === "intro" && customLanguage) {
      instruction = instruction.replace("CUSTOM_LANGUAGE", customLanguage);
    } else if (language === "custom" && part === "intro") {
      instruction = instruction.replace("CUSTOM_LANGUAGE", "Custom Language");
    }
    return instruction;
  };

  window.updateTokenRange = function () {
    var geminiModel = $('input[name="wpcmt_aisays_settings[gemini_model]"]:checked, input[name="wpcmt_aisays_gemini_model"]:checked').val() || $("#wpcmt_aisays_gemini_model").val() || "";
    var maxTokensInput = $("#wpcmt_aisays_max_tokens");
    var tokensValue = $("#max-tokens-value");
    var recommended = $("#recommended-tokens");
    var capacityInfo = $("#token-capacity-info");

    var configs = {
      // --- 3.8 / 3.7 Generation ---
      "gemini-3.8-flash": {
        min: 1000,
        max: 8000,
        default: 3000,
        rec: wpcmt_aisays.i18n.tokens_1500_5000 || "1000-8000 tokens for detailed descriptions",
        cap: wpcmt_aisays.i18n.cap_1m_15 || "1M TPM, 15 RPM (Latest Flagship)",
      },
      "gemini-3.7-flash": {
        min: 1000,
        max: 8000,
        default: 3000,
        rec: wpcmt_aisays.i18n.tokens_1500_5000 || "1000-8000 tokens for detailed descriptions",
        cap: wpcmt_aisays.i18n.cap_1m_15 || "1M TPM, 15 RPM",
      },
      "gemini-3.6-flash": {
        min: 1000,
        max: 8000,
        default: 3000,
        rec: wpcmt_aisays.i18n.tokens_1500_5000 || "1000-8000 tokens for detailed descriptions",
        cap: wpcmt_aisays.i18n.cap_1m_15 || "1M TPM, 15 RPM",
      },
      // --- 3.5 Generation ---
      "gemini-3.5-flash-lite": {
        min: 1000,
        max: 4000,
        default: 2000,
        rec: wpcmt_aisays.i18n.tokens_1000_4000 || "1000-4000 tokens for high-volume catalogs",
        cap: wpcmt_aisays.i18n.cap_1m_30 || "1M TPM, 30 RPM",
      },
      "gemini-3.5-flash": {
        min: 1000,
        max: 8000,
        default: 2500,
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
    var geminiModel = $('input[name="wpcmt_aisays_settings[gemini_model]"]:checked, input[name="wpcmt_aisays_gemini_model"]:checked').val() || $("#wpcmt_aisays_gemini_model").val() || "";
    var capacityInfo = $("#token-capacity-info");

    var capacityText = wpcmt_aisays.i18n.cap_standard || "Standard configuration";

    // Logic updated to match new slugs and prioritize specific suffixes (like lite/pro)
    if (geminiModel.includes("3.1-pro")) {
      capacityText = wpcmt_aisays.i18n.cap_30k_2 || "30K TPM, 2 RPM (High Reasoning)";
    } else if (geminiModel.includes("3.8-flash")) {
      capacityText = wpcmt_aisays.i18n.cap_1m_15 || "1M TPM, 15 RPM (Latest Flagship)";
    } else if (geminiModel.includes("3.7-flash") || geminiModel.includes("3.6-flash") || geminiModel.includes("3.5-flash") || geminiModel.includes("3-flash")) {
      capacityText = wpcmt_aisays.i18n.cap_1m_15 || "1M TPM, 15 RPM";
    } else if (geminiModel.includes("lite")) {
      capacityText = wpcmt_aisays.i18n.cap_1m_30 || "1M TPM, 30 RPM (High Volume)";
    } else if (geminiModel.includes("flash")) {
      capacityText = wpcmt_aisays.i18n.cap_1m_15 || "1M TPM, 15 RPM";
    }

    capacityInfo.text(capacityText);
  };
})(jQuery);
