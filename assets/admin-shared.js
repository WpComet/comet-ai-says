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
        const originalHtml = $button.html();
        const buttonTexts = {
            'wpcmt_aisays_generate_single_ai_description': {
                loading: wpcmt_aisays.i18n.generating,
            },
            'wpcmt_aisays_delete_ai_description': {
                loading: wpcmt_aisays.i18n.deleting,
            }
        };

        const config = buttonTexts[action] || { loading: 'Processing...' };
        const selectedLanguage = $('#wpcmt-aisays-language').val();

        $button.prop("disabled", true).html('<span class="spinner is-active" style="float:none; margin:0 4px 0 0; vertical-align:middle;"></span> ' + config.loading);

        $.ajax({
            url: wpcmt_aisays.ajaxurl,
            type: "POST",
            data: {
                action: action,
                product_id: productId,
                nonce: wpcmt_aisays.nonce,
                from: "single-operation",
                language: selectedLanguage
            },
            success: (response) => {
                if (response.success) {
                    successCallback(response, $button, productId, productName);
                } else {
                    $button.prop("disabled", false).html(originalHtml);
                    errorCallback(response.data, productName);
                }
            },
            error: () => {
                $button.prop("disabled", false).html(originalHtml);
                errorCallback(null, productName);
            }
        });
    }
    /**
     * Success callback for generation operations
     */
    const handleGenerationSuccess = (response, $button, productId, productName) => {
        //  console.log( wpcmt_aisays);
        // For products table
        if ($button.closest("tr").length) {
            const $row = $button.closest("tr");
            $row.find(".status-indicator").removeClass("dashicons-no text-warning").addClass("dashicons-yes text-success");
            $row.find(".action-buttons").html(`
                <a href="javascript:void(0);" class="view-ai-desc button" data-product-id="${productId}" data-product-name="${productName}">
                    <span class="dashicons dashicons-visibility"></span> ${wpcmt_aisays.i18n.view_existing}
                </a>
                <a href="javascript:void(0);" class="generate-single-ai button button-primary" data-product-id="${productId}" data-product-name="${productName}">
                    <span class="dashicons dashicons-update"></span> ${wpcmt_aisays.i18n.regenerate}
                </a>
                <a href="javascript:void(0);" class="delete-ai-desc button button-link-delete" data-product-id="${productId}" data-product-name="${productName}">
                    <span class="dashicons dashicons-trash"></span> ${wpcmt_aisays.i18n.delete_ai_description}
                </a>
            `);
        }
        // For product edit page
        else {
            $("#wpcmt-aisays-text").val(response.data.description);
            $("#wpcmt-aisays-result").show();
            $button.prop("disabled", false).html('<span class="dashicons dashicons-media-text"></span> ' + wpcmt_aisays.i18n.generate_ai_description);
        }

        alert(response.data.message);
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
                    <span class="dashicons dashicons-media-text"></span> ${wpcmt_aisays.i18n.generate_ai_description}
                </a>
            `);
        }
        // For product edit page
        else {
            $("#wpcmt-aisays-text").val("");
            $("#wpcmt-aisays-result").hide();
        }

        $button.prop("disabled", false).html('<span class="dashicons dashicons-trash"></span> ' + wpcmt_aisays.i18n.delete_ai_description);
        alert(wpcmt_aisays.i18n.deleted_success.replace("%s", productName));
    };

    /**
     * Error callback for operations
     */
    const handleOperationError = (errorMessage, productName, operationType = 'generate') => {
        const errorMessages = {
            'generate': wpcmt_aisays.i18n.generate_error_generic_specific.replace("%s", productName),
            'delete': wpcmt_aisays.i18n.delete_error_generic.replace("%s", productName)
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
            'wpcmt_aisays_generate_single_ai_description',
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

        if (!confirm(wpcmt_aisays.i18n.delete_confirm.replace("%s", productName))) {
            return;
        }

        handleSingleProductOperation(
            'wpcmt_aisays_delete_ai_description',
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

        $status.text(wpcmt_aisays.i18n.saving).css("color", "blue");

        $.ajax({
            url: wpcmt_aisays.ajaxurl,
            type: "POST",
            data: {
                action: "wpcmt_aisays_save_ai_description",
                product_id: productId,
                description: description,
                nonce: wpcmt_aisays.nonce,
            },
            success: (response) => {
                if (response.success) {
                    $status.text(wpcmt_aisays.i18n.saved).css("color", "green");
                    setTimeout(() => {
                        $status.text("");
                    }, 2000);
                } else {
                    $status.text(wpcmt_aisays.i18n.save_error).css("color", "red");
                }
            },
            error: () => {
                $status.text(wpcmt_aisays.i18n.save_error).css("color", "red");
            },
        });
    });

    // Global Theme Toggle Handler
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
        $(this).find(".theme-icon").text(newTheme === "dark" ? "☀️" : "🌙");
    });

    // Initialize Theme Icon
    var initialTheme = $("#comet-aisays-root").attr("data-theme") || "light";
    $("#comet-theme-toggle .theme-icon").text(initialTheme === "dark" ? "☀️" : "🌙");

    // =============================================
    // PRODUCTS TABLE PAGE FUNCTIONALITY
    // =============================================

    // Single product generation in products table
    $(document).on("click", ".generate-single-ai", function () {
        const productId = $(this).data("product-id");
        const productName = $(this).data("product-name");
        const $button = $(this);

        handleSingleProductOperation(
            'wpcmt_aisays_generate_single_ai_description',
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

        if (!confirm(wpcmt_aisays.i18n.delete_confirm.replace("%s", productName))) {
            return;
        }

        handleSingleProductOperation(
            'wpcmt_aisays_delete_ai_description',
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
        const productName = $(this).data("product-name") || "";
        const $btn = $(this);

        $btn.addClass("is-loading").prop("disabled", true);

        $.ajax({
            url: wpcmt_aisays.ajaxurl,
            type: "POST",
            data: {
                action: "wpcmt_aisays_get_ai_description",
                product_id: productId,
                nonce: wpcmt_aisays.nonce,
            },
            success: (response) => {
                $btn.removeClass("is-loading").prop("disabled", false);
                if (response.success) {
                    if (productName) {
                        $("#wpcmt-aisays-modal-title").text(productName).show();
                    } else {
                        $("#wpcmt-aisays-modal-title").hide();
                    }
                    $("#wpcmt-aisays-content").html(response.data.description);
                    $("#wpcmt-aisays-modal").addClass("is-active").show();
                } else {
                    alert(response.data || wpcmt_aisays.i18n.view_error);
                }
            },
            error: () => {
                $btn.removeClass("is-loading").prop("disabled", false);
                alert(wpcmt_aisays.i18n.view_error);
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
            this.totalCount = productIds.length;
            this.completedCount = 0;
            this.queue = [...productIds];
            this.results = {
                success: 0,
                errors: 0,
                details: [],
            };
            this.isRunning = false;
            this.actionType = actionType; // 'generate' or 'delete'
            this.concurrency = actionType === "delete" ? 4 : 2; // 2 concurrent workers for generation doubles speed while respecting 15 RPM quota
        }

        async start() {
            if (this.isRunning) return;

            this.isRunning = true;
            this.completedCount = 0;
            this.results = { success: 0, errors: 0, details: [] };
            $("#wpcmt-aisays-bulk-results").hide().empty();

            this.showProgress();

            // Launch concurrent workers
            const activeWorkers = [];
            const workerCount = Math.min(this.concurrency, this.queue.length);

            for (let i = 0; i < workerCount; i++) {
                activeWorkers.push(this.runWorker());
            }

            await Promise.all(activeWorkers);

            if (this.isRunning) {
                this.complete();
            }
        }

        async runWorker() {
            while (this.isRunning && this.queue.length > 0) {
                const productId = this.queue.shift();
                await this.processItem(productId);
            }
        }

        stop() {
            this.isRunning = false;
            this.queue = [];
            this.updateProgress("Stopped by user");
            this.showResults();
        }

        async processItem(productId) {
            if (!this.isRunning) return;

            const action = this.actionType === "delete" ? "wpcmt_aisays_delete_ai_description" : "wpcmt_aisays_generate_single_ai_description";
            const progressText = this.actionType === "delete" ? "Deleting" : "Processing";
            const $row = $(`input[value="${productId}"]`).closest("tr");
            const productName = $row.find("strong").first().text().trim() || `Product #${productId}`;

            this.updateProgress(`${progressText}: ${productName}`);

            try {
                const response = await $.ajax({
                    url: wpcmt_aisays.ajaxurl,
                    type: "POST",
                    data: {
                        action: action,
                        product_id: productId,
                        nonce: wpcmt_aisays.nonce,
                        from: "bulk-" + this.actionType,
                    },
                    timeout: 120000, // 2-minute safety timeout for vision/reasoning models
                });

                if (response.success) {
                    this.results.success++;
                    this.results.details.push({
                        product_id: productId,
                        product_name: productName,
                        status: "success",
                        message: response.data.message || `${this.actionType === "delete" ? "Deleted" : "Generated"} for: ${productName}`,
                    });

                    this.updateProductRow(productId, "success");
                } else {
                    this.results.errors++;
                    this.results.details.push({
                        product_id: productId,
                        product_name: productName,
                        status: "error",
                        message: response.data || `${this.actionType === "delete" ? "Deletion" : "Generation"} failed`,
                    });

                    this.updateProductRow(productId, "error");
                }
            } catch (error) {
                this.results.errors++;
                const errorMessage = error.statusText === "timeout"
                    ? "Operation timed out after 120 seconds."
                    : (error.statusText || "Network connection error");
                this.results.details.push({
                    product_id: productId,
                    product_name: productName,
                    status: "error",
                    message: errorMessage,
                });

                this.updateProductRow(productId, "error");
            }

            this.completedCount++;
            if (this.isRunning) {
                this.updateProgress(`Completed ${this.completedCount} of ${this.totalCount}`);
            }
        }

        updateProductRow(productId, status) {
            const $row = $(`input[value="${productId}"]`).closest("tr");

            if (status === "success") {
                if (this.actionType === "generate") {
                    $row.find(".status-indicator").removeClass("dashicons-no text-warning").addClass("dashicons-yes text-success");

                    const productName = $row.find("strong").first().text() || "Product";
                    $row.find(".action-buttons").html(`
                        <a href="javascript:void(0);" class="view-ai-desc button" data-product-id="${productId}" data-product-name="${productName}">
                            <span class="dashicons dashicons-visibility"></span> ${wpcmt_aisays.i18n.view_existing}
                        </a>
                        <a href="javascript:void(0);" class="generate-single-ai button button-primary" data-product-id="${productId}" data-product-name="${productName}">
                            <span class="dashicons dashicons-update"></span> ${wpcmt_aisays.i18n.regenerate}
                        </a>
                        <a href="javascript:void(0);" class="delete-ai-desc button button-link-delete" data-product-id="${productId}" data-product-name="${productName}">
                            <span class="dashicons dashicons-trash"></span> ${wpcmt_aisays.i18n.delete_ai_description}
                        </a>
                    `);
                } else {
                    // For delete operations
                    $row.find(".status-indicator").removeClass("dashicons-yes text-success").addClass("dashicons-no text-warning");

                    const productName = $row.find("strong").first().text() || "Product";
                    $row.find(".action-buttons").html(`
                        <a href="javascript:void(0);" class="generate-single-ai button button-primary" data-product-id="${productId}" data-product-name="${productName}">
                            <span class="dashicons dashicons-media-text"></span> ${wpcmt_aisays.i18n.generate_ai_description}
                        </a>
                    `);
                }
            }
        }

        showProgress() {
            const actionText = this.actionType === "delete" ? "deletion" : "generation";
            $("#wpcmt-aisays-bulk-progress").slideDown(200);
            $("#wpcmt-aisays-progress-text")
                .removeClass("is-success is-warning")
                .addClass("is-primary")
                .text(`0/${this.totalCount} - Starting bulk ${actionText}...`);
            $("#wpcmt-aisays-progress-bar")
                .val(0)
                .attr("value", 0)
                .text("0%");
            $("#wpcmt-aisays-stop-bulk")
                .removeClass("is-light")
                .addClass("is-danger is-outlined")
                .text(this.actionType === "delete" ? "Stop Deletion" : "Stop Generation");
        }

        updateProgress(message) {
            const percent = this.totalCount > 0 ? Math.min(100, Math.round((this.completedCount / this.totalCount) * 100)) : 0;
            $("#wpcmt-aisays-progress-text").text(`${this.completedCount}/${this.totalCount} - ${message}`);
            $("#wpcmt-aisays-progress-bar").val(percent).attr("value", percent).text(`${percent}%`);
        }

        complete() {
            this.isRunning = false;
            $("#wpcmt-aisays-progress-bar").val(100).attr("value", 100).text("100%");

            if (this.results.errors === 0) {
                $("#wpcmt-aisays-progress-text")
                    .removeClass("is-primary is-warning")
                    .addClass("is-success")
                    .text(`All ${this.totalCount} completed successfully!`);
            } else {
                $("#wpcmt-aisays-progress-text")
                    .removeClass("is-primary is-success")
                    .addClass("is-warning")
                    .text(`${this.results.success}/${this.totalCount} completed (${this.results.errors} failed)`);
            }

            $("#wpcmt-aisays-stop-bulk")
                .removeClass("is-danger is-outlined")
                .addClass("is-light")
                .text("Dismiss");

            this.showResults();
        }

        showResults() {
            const actionType = this.actionType;
            const actionText = actionType === "delete" ? "deleted" : "generated";
            const statusClass = this.results.errors === 0 ? "is-success" : (this.results.success === 0 ? "is-danger" : "is-warning");
            const iconClass = this.results.errors === 0 ? "dashicons-yes-alt" : "dashicons-warning";

            let resultsHtml = `
                <div class="notification ${statusClass} is-light mb-4" style="border-radius: 8px;">
                    <button type="button" class="delete" aria-label="close" onclick="$(this).parent().fadeOut();"></button>
                    <p class="is-size-6 has-text-weight-bold mb-1">
                        <span class="dashicons ${iconClass} mr-1"></span>
                        ${wpcmt_aisays.i18n.completed || "Completed"}
                    </p>
                    <p class="mb-0">
                        ${wpcmt_aisays.i18n[actionText + "_count"] ? wpcmt_aisays.i18n[actionText + "_count"].replace("%d", this.results.success) : `${this.results.success} products processed.`}
                        ${this.results.errors > 0 ? ` — <strong>${this.results.errors} failed</strong>` : ""}
                    </p>
            `;

            const errorDetails = this.results.details.filter((detail) => detail.status === "error");
            if (errorDetails.length > 0) {
                resultsHtml += `
                    <details class="mt-3" style="background: rgba(0,0,0,0.04); padding: 8px 12px; border-radius: 6px;">
                        <summary style="cursor: pointer; font-weight: 600;">View Error Details (${errorDetails.length})</summary>
                        <ul class="mt-2 ml-4" style="list-style-type: disc;">
                `;
                errorDetails.forEach((detail) => {
                    resultsHtml += `<li class="mb-1"><strong>${detail.product_name || "Product #" + detail.product_id}:</strong> ${detail.message}</li>`;
                });
                resultsHtml += `
                        </ul>
                    </details>
                `;
            }

            resultsHtml += `</div>`;

            $("#wpcmt-aisays-bulk-results").html(resultsHtml).fadeIn(200);

            $("html, body").animate(
                {
                    scrollTop: $("#wpcmt-aisays-bulk-progress").offset().top - 80,
                },
                400
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
                alert(wpcmt_aisays.i18n.no_products_selected);
                return;
            }

            const actionType = action === "bulk_delete" ? "delete" : "generate";
            const actionText = actionType === "delete" ? "delete" : "generate";
            const defaultConfirm = actionType === "delete"
                ? `Are you sure you want to delete AI descriptions for ${productIds.length} selected products?`
                : `Generate AI descriptions for ${productIds.length} selected products?`;
            const template = actionType === "delete" ? (wpcmt_aisays.i18n && wpcmt_aisays.i18n.bulk_delete_confirm) : (wpcmt_aisays.i18n && wpcmt_aisays.i18n.bulk_confirm);
            const confirmMessage = template ? template.replace("%d", productIds.length) : defaultConfirm;

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

    // Stop / Dismiss bulk operations
    $(document).on("click", "#wpcmt-aisays-stop-bulk", function () {
        if (bulkGenerator && bulkGenerator.isRunning) {
            bulkGenerator.stop();
            return;
        }
        if (bulkDeleter && bulkDeleter.isRunning) {
            bulkDeleter.stop();
            return;
        }
        $("#wpcmt-aisays-bulk-progress").slideUp(200);
    });

    // =============================================
    // MODAL HANDLING
    // =============================================

    function closePreviewModal() {
        $("#wpcmt-aisays-modal").removeClass("is-active").hide();
    }

    $(document).on("click", "#wpcmt-aisays-modal [data-modal-close]", function (e) {
        e.preventDefault();
        closePreviewModal();
    });

    $(document).on("click", "#wpcmt-aisays-copy-modal-desc", function (e) {
        e.preventDefault();
        const text = $("#wpcmt-aisays-content").text();
        if (navigator.clipboard && text) {
            navigator.clipboard.writeText(text).then(() => {
                const $btn = $(this);
                const originalHtml = $btn.html();
                $btn.text(wpcmt_aisays.i18n.saved || "Copied!");
                setTimeout(() => $btn.html(originalHtml), 2000);
            });
        }
    });

    // Close modals when clicking outside
    $(document).on("click", function (e) {
        if ($(e.target).is("#wpcmt-aisays-modal") || $(e.target).hasClass("modal-background")) {
            closePreviewModal();
        }
    });

    // Escape key to close modals
    $(document).on("keyup", function (e) {
        if (e.keyCode === 27) {
            closePreviewModal();
            $("#wpcmt-aisays-existing-modal").hide();
        }
    });
});