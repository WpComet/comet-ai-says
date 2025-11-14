/**
 * Comet AI Says - Admin JavaScript
 * Uses ES6 features with jQuery for WordPress compatibility
 */

jQuery(document).ready(function ($) {
    let bulkGenerator = null;
    let bulkDeleter = null;

    // =============================================
    // REUSABLE AJAX HANDLERS
    // =============================================

    /**
     * Unified AJAX handler for single product operations
     */
function handleSingleProductOperation(action, productId, productName, $button, successCallback, errorCallback) {
    const buttonTexts = {
        'generate_single_ai_description': {
            loading: wpcmtAISays.i18n.generating,
            default: '<span class="dashicons dashicons-media-text"></span> ' + wpcmtAISays.i18n.generate_ai_description
        },
        'delete_ai_description': {
            loading: wpcmtAISays.i18n.deleting,
            default: '<span class="dashicons dashicons-trash"></span> ' + wpcmtAISays.i18n.delete_ai_description
        }
    };

    const buttonConfig = buttonTexts[action] || {
        loading: 'Processing...',
        default: $button.html()
    };

    // Get the selected language from the dropdown
    const selectedLanguage = $('#wpcmt-aisays-language').val();

    $button.prop("disabled", true).html('<span class="spinner is-active"></span> ' + buttonConfig.loading);

    $.ajax({
        url: wpcmtAISays.ajaxurl,
        type: "POST",
        data: {
            action: action,
            product_id: productId,
            nonce: wpcmtAISays.nonce,
            from: "single-operation",
            language: selectedLanguage // Add the selected language
        },
        success: (response) => {
            if (response.success) {
                successCallback(response, $button, productId, productName);
            } else {
                $button.prop("disabled", false).html(buttonConfig.default);
                errorCallback(response.data, productName);
            }
        },
        error: () => {
            $button.prop("disabled", false).html(buttonConfig.default);
            errorCallback(null, productName);
        }
    });
}
    /**
     * Success callback for generation operations
     */
    const handleGenerationSuccess = (response, $button, productId, productName) => {
        // For products table
        if ($button.closest("tr").length) {
            const $row = $button.closest("tr");
            $row.find(".status-indicator").removeClass("dashicons-no text-warning").addClass("dashicons-yes text-success");
            $row.find(".action-buttons").html(`
                <a href="javascript:void(0);" class="view-ai-desc button" data-product-id="${productId}">
                    <span class="dashicons dashicons-visibility"></span> ${wpcmtAISays.i18n.view_existing}
                </a>
                <a href="javascript:void(0);" class="generate-single-ai button button-primary" data-product-id="${productId}" data-product-name="${productName}">
                    <span class="dashicons dashicons-update"></span> ${wpcmtAISays.i18n.regenerate}
                </a>
                <a href="javascript:void(0);" class="delete-ai-desc button button-link-delete" data-product-id="${productId}" data-product-name="${productName}">
                    <span class="dashicons dashicons-trash"></span> ${wpcmtAISays.i18n.delete_ai_description}
                </a>
            `);
        }
        // For product edit page
        else {
            $("#wpcmt-aisays-text").val(response.data.description);
            $("#wpcmt-aisays-result").show();
        }
        
        $button.prop("disabled", false).html('<span class="dashicons dashicons-media-text"></span> ' + wpcmtAISays.i18n.generate_ai_description);
        alert(wpcmtAISays.i18n.generated_success.replace("%s", productName));
    };

    /**
     * Success callback for deletion operations
     */
    const handleDeletionSuccess = (response, $button, productId, productName) => {
        // For products table
        if ($button.closest("tr").length) {
            const $row = $button.closest("tr");
            $row.find(".status-indicator").removeClass("dashicons-yes text-success").addClass("dashicons-no text-warning");
            $row.find(".action-buttons").html(`
                <a href="javascript:void(0);" class="generate-single-ai button button-primary" data-product-id="${productId}" data-product-name="${productName}">
                    <span class="dashicons dashicons-media-text"></span> ${wpcmtAISays.i18n.generate_ai_description}
                </a>
            `);
        }
        // For product edit page
        else {
            $("#wpcmt-aisays-text").val("");
            $("#wpcmt-aisays-result").hide();
        }
        
        $button.prop("disabled", false).html('<span class="dashicons dashicons-trash"></span> ' + wpcmtAISays.i18n.delete_ai_description);
        alert(wpcmtAISays.i18n.deleted_success.replace("%s", productName));
    };

    /**
     * Error callback for operations
     */
    const handleOperationError = (errorMessage, productName, operationType = 'generate') => {
        const errorMessages = {
            'generate': wpcmtAISays.i18n.generate_error_generic_specific.replace("%s", productName),
            'delete': wpcmtAISays.i18n.delete_error_generic.replace("%s", productName)
        };
        
        alert(errorMessage || errorMessages[operationType]);
    };

    // =============================================
    // PRODUCT EDIT PAGE FUNCTIONALITY
    // =============================================

    // Generate AI Description on product edit page
    $("#generate-wpcmt-aisays").on("click", function () {
        const productId = $(this).data("product-id");
        const productName = $(this).data("product-name") || "this product";
        const $button = $(this);
        const $loading = $("#wpcmt-aisays-loading");

        $loading.show();
        $button.prop("disabled", true);

        handleSingleProductOperation(
            'generate_single_ai_description',
            productId,
            productName,
            $button,
            (response, $btn) => {
                $loading.hide();
                handleGenerationSuccess(response, $btn, productId, productName);
            },
            (error) => {
                $loading.hide();
                handleOperationError(error, productName, 'generate');
            }
        );
    });

    // Delete AI Description on product edit page
    $("#delete-wpcmt-aisays").on("click", function () {
        const productId = $(this).data("product-id");
        const productName = $(this).data("product-name") || "this product";
        const $button = $(this);

        if (!confirm(wpcmtAISays.i18n.delete_confirm.replace("%s", productName))) {
            return;
        }

        handleSingleProductOperation(
            'delete_ai_description',
            productId,
            productName,
            $button,
            (response, $btn) => {
                handleDeletionSuccess(response, $btn, productId, productName);
            },
            (error) => {
                handleOperationError(error, productName, 'delete');
            }
        );
    });

    // Save AI Description on product edit page
    $("#save-wpcmt-aisays").on("click", function () {
        const productId = $(this).data("product-id");
        const description = $("#wpcmt-aisays-text").val();
        const $status = $("#wpcmt-aisays-save-status");

        $status.text(wpcmtAISays.i18n.saving).css("color", "blue");

        $.ajax({
            url: wpcmtAISays.ajaxurl,
            type: "POST",
            data: {
                action: "save_ai_description",
                product_id: productId,
                description: description,
                nonce: wpcmtAISays.nonce,
            },
            success: (response) => {
                if (response.success) {
                    $status.text(wpcmtAISays.i18n.saved).css("color", "green");
                    setTimeout(() => {
                        $status.text("");
                    }, 2000);
                } else {
                    $status.text(wpcmtAISays.i18n.save_error).css("color", "red");
                }
            },
            error: () => {
                $status.text(wpcmtAISays.i18n.save_error).css("color", "red");
            },
        });
    });

    // =============================================
    // PRODUCTS TABLE PAGE FUNCTIONALITY
    // =============================================

    // Single product generation in products table
    $(document).on("click", ".generate-single-ai", function () {
        const productId = $(this).data("product-id");
        const productName = $(this).data("product-name");
        const $button = $(this);

        handleSingleProductOperation(
            'generate_single_ai_description',
            productId,
            productName,
            $button,
            handleGenerationSuccess,
            (error) => {
                handleOperationError(error, productName, 'generate');
            }
        );
    });

    // Single product deletion in products table
    $(document).on("click", ".delete-ai-desc", function () {
        const productId = $(this).data("product-id");
        const productName = $(this).data("product-name");
        const $button = $(this);

        if (!confirm(wpcmtAISays.i18n.delete_confirm.replace("%s", productName))) {
            return;
        }

        handleSingleProductOperation(
            'delete_ai_description',
            productId,
            productName,
            $button,
            handleDeletionSuccess,
            (error) => {
                handleOperationError(error, productName, 'delete');
            }
        );
    });

    // View AI description in products table
    $(document).on("click", ".view-ai-desc", function () {
        const productId = $(this).data("product-id");

        $.ajax({
            url: wpcmtAISays.ajaxurl,
            type: "POST",
            data: {
                action: "get_ai_description",
                product_id: productId,
                nonce: wpcmtAISays.nonce,
            },
            success: (response) => {
                if (response.success) {
                    $("#wpcmt-aisays-content").text(response.data.description);
                    $("#wpcmt-aisays-modal").show();
                } else {
                    alert(wpcmtAISays.i18n.view_error);
                }
            },
            error: () => {
                alert(wpcmtAISays.i18n.view_error);
            },
        });
    });

    // =============================================
    // BULK GENERATION - SEQUENTIAL SYSTEM
    // =============================================

    // BulkGenerator class for sequential processing
    class BulkGenerator {
        constructor(productIds, actionType = "generate") {
            this.productIds = productIds;
            this.currentIndex = 0;
            this.results = {
                success: 0,
                errors: 0,
                details: [],
            };
            this.isRunning = false;
            this.actionType = actionType; // 'generate' or 'delete'
        }

        async start() {
            if (this.isRunning) return;

            this.isRunning = true;
            this.showProgress();
            await this.processNext();
        }

        stop() {
            this.isRunning = false;
            this.updateProgress("Stopped by user");
            this.hideProgress();
            this.showResults();
        }

        async processNext() {
            if (!this.isRunning || this.currentIndex >= this.productIds.length) {
                this.complete();
                return;
            }

            const productId = this.productIds[this.currentIndex];
            const action = this.actionType === "delete" ? "delete_ai_description" : "generate_single_ai_description";
            const progressText = this.actionType === "delete" ? "Deleting" : "Processing";

            this.updateProgress(`${progressText} product ${this.currentIndex + 1} of ${this.productIds.length}`);

            try {
                const response = await $.ajax({
                    url: wpcmtAISays.ajaxurl,
                    type: "POST",
                    data: {
                        action: action,
                        product_id: productId,
                        nonce: wpcmtAISays.nonce,
                        from: "bulk-" + this.actionType,
                    },
                    timeout: 30000,
                });

                if (response.success) {
                    this.results.success++;
                    this.results.details.push({
                        product_id: productId,
                        status: "success",
                        message: response.data.message || `${this.actionType === "delete" ? "Deleted" : "Generated"} for: ${response.data.product_name}`,
                    });

                    this.updateProductRow(productId, "success");
                } else {
                    this.results.errors++;
                    this.results.details.push({
                        product_id: productId,
                        status: "error",
                        message: response.data || `${this.actionType === "delete" ? "Deletion" : "Generation"} failed`,
                    });

                    this.updateProductRow(productId, "error");
                }

                this.currentIndex++;
                this.processNext();
            } catch (error) {
                this.results.errors++;
                const errorMessage = error.statusText || "Network error";
                this.results.details.push({
                    product_id: productId,
                    status: "error",
                    message: errorMessage,
                });

                this.updateProductRow(productId, "error");
                this.currentIndex++;
                this.processNext();
            }
        }

        updateProductRow(productId, status) {
            const $row = $(`input[value="${productId}"]`).closest("tr");

            if (status === "success") {
                if (this.actionType === "generate") {
                    $row.find(".status-indicator").removeClass("dashicons-no text-warning").addClass("dashicons-yes text-success");

                    const productName = $row.find("strong").first().text() || "Product";
                    $row.find(".action-buttons").html(`
                        <a href="javascript:void(0);" class="view-ai-desc button" data-product-id="${productId}">
                            <span class="dashicons dashicons-visibility"></span> ${wpcmtAISays.i18n.view_existing}
                        </a>
                        <a href="javascript:void(0);" class="generate-single-ai button button-primary" data-product-id="${productId}" data-product-name="${productName}">
                            <span class="dashicons dashicons-update"></span> ${wpcmtAISays.i18n.regenerate}
                        </a>
                        <a href="javascript:void(0);" class="delete-ai-desc button button-link-delete" data-product-id="${productId}" data-product-name="${productName}">
                            <span class="dashicons dashicons-trash"></span> ${wpcmtAISays.i18n.delete_ai_description}
                        </a>
                    `);
                } else {
                    // For delete operations
                    $row.find(".status-indicator").removeClass("dashicons-yes text-success").addClass("dashicons-no text-warning");

                    const productName = $row.find("strong").first().text() || "Product";
                    $row.find(".action-buttons").html(`
                        <a href="javascript:void(0);" class="generate-single-ai button button-primary" data-product-id="${productId}" data-product-name="${productName}">
                            <span class="dashicons dashicons-media-text"></span> ${wpcmtAISays.i18n.generate_ai_description}
                        </a>
                    `);
                }
            }
        }

        showProgress() {
            $("#wpcmt-aisays-bulk-progress").show();
            const actionText = this.actionType === "delete" ? "deletion" : "generation";
            this.updateProgress(`Starting bulk ${actionText}...`);
        }

        updateProgress(message) {
            const progress = (this.currentIndex / this.productIds.length) * 100;
            $("#wpcmt-aisays-progress-text").text(`${this.currentIndex}/${this.productIds.length} - ${message}`);
            $("#wpcmt-aisays-progress-bar").css("width", `${progress}%`);
        }

        hideProgress() {
            $("#wpcmt-aisays-bulk-progress").hide();
        }

        complete() {
            this.isRunning = false;
            this.hideProgress();
            this.showResults();
        }

        showResults() {
            let resultsHtml = "";
            const actionType = this.actionType;
            const actionText = actionType === "delete" ? "deleted" : "generated";
            const actionTextPast = actionType === "delete" ? "deletion" : "generation";

            if (this.results.errors === 0) {
                resultsHtml = `
                    <div class="notice notice-success">
                        <p><strong>${wpcmtAISays.i18n.completed}</strong></p>
                        <p>${wpcmtAISays.i18n[actionText + "_count"].replace("%d", this.results.success)}</p>
                    </div>
                `;
            } else {
                resultsHtml = `
                    <div class="notice notice-warning">
                        <p><strong>${wpcmtAISays.i18n.completed}</strong></p>
                        <p>${wpcmtAISays.i18n[actionText + "_count"].replace("%d", this.results.success)}</p>
                        <p>${wpcmtAISays.i18n[actionType + "_error_specific"].replace("%s", this.results.errors)}</p>
                `;

                const errorDetails = this.results.details.filter((detail) => detail.status === "error");
                if (errorDetails.length > 0) {
                    resultsHtml += `
                        <details style="margin-top: 10px;">
                            <summary><strong>Error Details:</strong></summary>
                            <ul style="margin-left: 20px;">
                    `;
                    errorDetails.forEach((detail) => {
                        resultsHtml += `<li>${detail.message}</li>`;
                    });
                    resultsHtml += `
                            </ul>
                        </details>
                    `;
                }

                resultsHtml += `</div>`;
            }

            $("#wpcmt-aisays-bulk-results").html(resultsHtml).show();

            $("html, body").animate(
                {
                    scrollTop: $("#wpcmt-aisays-bulk-results").offset().top - 100,
                },
                500
            );
        }
    }

    // Handle bulk action form submission
    $(document).on("click", "#doaction, #doaction2", function (e) {
        const $button = $(this);
        const action = $button.closest(".tablenav").find(".bulkactions select").val();

        if (action === "bulk_generate" || action === "bulk_delete") {
            e.preventDefault();

            const productIds = [];
            $('input[name="product_ids[]"]:checked').each(function () {
                productIds.push(parseInt($(this).val()));
            });

            if (productIds.length === 0) {
                alert(wpcmtAISays.i18n.no_products_selected);
                return;
            }

            const actionType = action === "bulk_delete" ? "delete" : "generate";
            const actionText = actionType === "delete" ? "delete" : "generate";
            const confirmMessage =
                actionType === "delete"
                    ? wpcmtAISays.i18n.bulk_delete_confirm.replace("%d", productIds.length)
                    : wpcmtAISays.i18n.bulk_confirm.replace("%d", productIds.length);

            if (!confirm(confirmMessage)) {
                return;
            }

            if (actionType === "delete") {
                bulkDeleter = new BulkGenerator(productIds, "delete");
                bulkDeleter.start();
            } else {
                bulkGenerator = new BulkGenerator(productIds, "generate");
                bulkGenerator.start();
            }
        }
    });

    // Stop bulk operations
    $(document).on("click", "#wpcmt-aisays-stop-bulk", function () {
        if (bulkGenerator && bulkGenerator.isRunning) {
            bulkGenerator.stop();
        }
        if (bulkDeleter && bulkDeleter.isRunning) {
            bulkDeleter.stop();
        }
    });

    // =============================================
    // MODAL HANDLING
    // =============================================

    // Close modals when clicking outside
    $(document).on("click", function (e) {
        if ($(e.target).is("#wpcmt-aisays-modal")) {
            $("#wpcmt-aisays-modal").hide();
        }
    });

    // Escape key to close modals
    $(document).on("keyup", function (e) {
        if (e.keyCode === 27) {
            $("#wpcmt-aisays-modal").hide();
        }
    });
});