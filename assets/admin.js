jQuery(document).ready(function($) {
    let bulkGenerator = null;

    // =============================================
    // PRODUCT EDIT PAGE FUNCTIONALITY - SIMPLIFIED
    // =============================================
    
    // Generate AI Description on product edit page - SIMPLIFIED
    $('#generate-wpcmt-aisays').on('click', function() {
        const productId = $(this).data('product-id');
        const $loading = $('#wpcmt-aisays-loading');
        const $button = $(this);
        
        $loading.show();
        $button.prop('disabled', true);
        
        $.ajax({
            url: wpcmtAISays.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_single_ai_description',
                product_id: productId,
                nonce: wpcmtAISays.nonce,
                from: 'edit-product'
            },
            success: function(response) {
                $loading.hide();
                $button.prop('disabled', false);
                
                if (response.success) {
                    // Update the textarea and show result section immediately
                    $('#wpcmt-aisays-text').val(response.data.description);
                    $('#wpcmt-aisays-result').show();
                    alert('AI description generated successfully!');
                } else {
                    alert(wpcmtAISays.i18n.generate_error + response.data);
                }
            },
            error: function() {
                $loading.hide();
                $button.prop('disabled', false);
                alert(wpcmtAISays.i18n.generate_error_generic);
            }
        });
    });
    
    // Save AI Description on product edit page
    $('#save-wpcmt-aisays').on('click', function() {
        const productId = $(this).data('product-id');
        const description = $('#wpcmt-aisays-text').val();
        const $status = $('#wpcmt-aisays-save-status');
        
        $status.text(wpcmtAISays.i18n.saving).css('color', 'blue');
        
        $.ajax({
            url: wpcmtAISays.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_ai_description',
                product_id: productId,
                description: description,
                nonce: wpcmtAISays.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.text(wpcmtAISays.i18n.saved).css('color', 'green');
                    setTimeout(function() {
                        $status.text('');
                    }, 2000);
                } else {
                    $status.text(wpcmtAISays.i18n.save_error).css('color', 'red');
                }
            },
            error: function() {
                $status.text(wpcmtAISays.i18n.save_error).css('color', 'red');
            }
        });
    });

    // =============================================
    // PRODUCTS TABLE PAGE FUNCTIONALITY - SIMPLIFIED
    // =============================================
    
    // Single product generation in products table
    $(document).on('click', '.generate-single-ai', function() {
        const productId = $(this).data('product-id');
        const productName = $(this).data('product-name');
        const $button = $(this);
        
        $button.prop('disabled', true).html('<span class="spinner is-active"></span> ' + wpcmtAISays.i18n.generating);
        
        $.ajax({
            url: wpcmtAISays.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_single_ai_description',
                product_id: productId,
                nonce: wpcmtAISays.nonce,
                from: 'adming-single-row'
            },
            success: function(response) {
                                        console.log( wpcmtAISays.i18n);
                     console.log( wpcmtAISays.i18n.regenerate);
                if (response.success) {
                     console.log( wpcmtAISays.i18n);
                     console.log( wpcmtAISays.i18n.regenerate);

                    // Update the row immediately
                    const $row = $button.closest('tr');
                    $row.find('.status-indicator')
                        .removeClass('dashicons-no text-warning')
                        .addClass('dashicons-yes text-success');
                    $row.find('.action-buttons').html(`
                        <a href="javascript:void(0);" class="view-ai-desc button" data-product-id="${productId}">
                            <span class="dashicons dashicons-visibility"></span> ${wpcmtAISays.i18n.view_existing}
                        </a>
                        <a href="javascript:void(0);" class="generate-single-ai button button-primary" data-product-id="${productId}" data-product-name="${productName}">
                            <span class="dashicons dashicons-update"></span> ${wpcmtAISays.i18n.regenerate}
                        </a>
                    `);
                    
                    alert(wpcmtAISays.i18n.generated_success.replace('%s', productName));
                } else {
                    alert(wpcmtAISays.i18n.generate_error + response.data);
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-media-text"></span> ' + wpcmtAISays.i18n.generate_ai_description);
                }
            },
            error: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-media-text"></span> ' + wpcmtAISays.i18n.generate_ai_description);
                alert(wpcmtAISays.i18n.generate_error_generic_specific.replace('%s', productName));
            }
        });
    });
    
    // View AI description in products table
    $(document).on('click', '.view-ai-desc', function() {
        const productId = $(this).data('product-id');
        
        $.ajax({
            url: wpcmtAISays.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_ai_description',
                product_id: productId,
                nonce: wpcmtAISays.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#wpcmt-aisays-content').text(response.data.description);
                    $('#wpcmt-aisays-modal').show();
                } else {
                    alert(wpcmtAISays.i18n.view_error);
                }
            },
            error: function() {
                alert(wpcmtAISays.i18n.view_error);
            }
        });
    });
    
    // =============================================
    // BULK GENERATION - NEW SEQUENTIAL SYSTEM
    // =============================================
    
    // BulkGenerator class for sequential processing
    class BulkGenerator {
        constructor(productIds) {
            this.productIds = productIds;
            this.currentIndex = 0;
            this.results = {
                success: 0,
                errors: 0,
                details: []
            };
            this.isRunning = false;
        }
        
        async start() {
            if (this.isRunning) return;
            
            this.isRunning = true;
            this.showProgress();
            await this.processNext();
        }
        
        stop() {
            this.isRunning = false;
            this.updateProgress('Stopped by user');
            this.hideProgress();
            this.showResults();
        }
        
        async processNext() {
            if (!this.isRunning || this.currentIndex >= this.productIds.length) {
                this.complete();
                return;
            }
            
            const productId = this.productIds[this.currentIndex];
            this.updateProgress(`Processing product ${this.currentIndex + 1} of ${this.productIds.length}`);
            
            try {
                const response = await $.ajax({
                    url: wpcmtAISays.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'generate_single_ai_description',
                        product_id: productId,
                        nonce: wpcmtAISays.nonce,
                        from: 'bulk-generation'
                    },
                    timeout: 30000
                });
                
                if (response.success) {
                    this.results.success++;
                    this.results.details.push({
                        product_id: productId,
                        status: 'success',
                        message: response.data.message || `Generated for: ${response.data.product_name}`
                    });
                    
                    this.updateProductRow(productId, 'success');
                } else {
                    this.results.errors++;
                    this.results.details.push({
                        product_id: productId,
                        status: 'error',
                        message: response.data || 'Generation failed'
                    });
                    
                    this.updateProductRow(productId, 'error');
                }
                
                this.currentIndex++;
                this.processNext();
                
            } catch (error) {
                this.results.errors++;
                const errorMessage = error.statusText || 'Network error';
                this.results.details.push({
                    product_id: productId,
                    status: 'error',
                    message: errorMessage
                });
                
                this.updateProductRow(productId, 'error');
                this.currentIndex++;
                this.processNext();
            }
        }
        
        updateProductRow(productId, status) {
            const $row = $(`input[value="${productId}"]`).closest('tr');
            
            if (status === 'success') {
                          console.log( wpcmtAISays.i18n);
                     console.log( wpcmtAISays.i18n.regenerate);

                $row.find('.status-indicator')
                    .removeClass('dashicons-no text-warning')
                    .addClass('dashicons-yes text-success');
                
                const productName = $row.find('strong').first().text() || 'Product';
                $row.find('.action-buttons').html(`
                    <a href="javascript:void(0);" class="view-ai-desc button" data-product-id="${productId}">
                        <span class="dashicons dashicons-visibility"></span> ${wpcmtAISays.i18n.view_existing}
                    </a>
                    <a href="javascript:void(0);" class="generate-single-ai button button-primary" data-product-id="${productId}" data-product-name="${productName}">
                        <span class="dashicons dashicons-update"></span> ${wpcmtAISays.i18n.regenerate}
                    </a>
                `);
            }
        }
        
        showProgress() {
            $('#wpcmt-aisays-bulk-progress').show();
            this.updateProgress('Starting bulk generation...');
        }
        
        updateProgress(message) {
            const progress = this.currentIndex / this.productIds.length * 100;
            $('#wpcmt-aisays-progress-text').text(
                `${this.currentIndex}/${this.productIds.length} - ${message}`
            );
            $('#wpcmt-aisays-progress-bar').css('width', `${progress}%`);
        }
        
        hideProgress() {
            $('#wpcmt-aisays-bulk-progress').hide();
        }
        
        complete() {
            this.isRunning = false;
            this.hideProgress();
            this.showResults();
        }
        
        showResults() {
            let resultsHtml = '';
            
            if (this.results.errors === 0) {
                resultsHtml = `
                    <div class="notice notice-success">
                        <p><strong>${wpcmtAISays.i18n.completed}</strong></p>
                        <p>${wpcmtAISays.i18n.generated_count.replace('%d', this.results.success)}</p>
                    </div>
                `;
            } else {
                resultsHtml = `
                    <div class="notice notice-warning">
                        <p><strong>${wpcmtAISays.i18n.completed}</strong></p>
                        <p>${wpcmtAISays.i18n.generated_count.replace('%d', this.results.success)}</p>
                        <p>${wpcmtAISays.i18n.generate_error_specific.replace('%s', this.results.errors)}</p>
                `;
                
                const errorDetails = this.results.details.filter(detail => detail.status === 'error');
                if (errorDetails.length > 0) {
                    resultsHtml += `
                        <details style="margin-top: 10px;">
                            <summary><strong>Error Details:</strong></summary>
                            <ul style="margin-left: 20px;">
                    `;
                    errorDetails.forEach(detail => {
                        resultsHtml += `<li>${detail.message}</li>`;
                    });
                    resultsHtml += `
                            </ul>
                        </details>
                    `;
                }
                
                resultsHtml += `</div>`;
            }
            
            $('#wpcmt-aisays-bulk-results').html(resultsHtml).show();
            
            $('html, body').animate({
                scrollTop: $('#wpcmt-aisays-bulk-results').offset().top - 100
            }, 500);
        }
    }

    // Handle bulk action form submission
    $(document).on('click', '#doaction, #doaction2', function(e) {
        const $button = $(this);
        const action = $button.closest('.tablenav').find('.bulkactions select').val();
        
        if (action === 'bulk_generate') {
            e.preventDefault();
            
            const productIds = [];
            $('input[name="product_ids[]"]:checked').each(function() {
                productIds.push(parseInt($(this).val()));
            });
            
            if (productIds.length === 0) {
                alert(wpcmtAISays.i18n.no_products_selected);
                return;
            }
            
            if (!confirm(wpcmtAISays.i18n.bulk_confirm.replace('%d', productIds.length))) {
                return;
            }
            
            bulkGenerator = new BulkGenerator(productIds);
            bulkGenerator.start();
        }
    });
    
    // Stop bulk generation
    $(document).on('click', '#wpcmt-aisays-stop-bulk', function() {
        if (bulkGenerator) {
            bulkGenerator.stop();
        }
    });

    // =============================================
    // MODAL HANDLING
    // =============================================
    
    // Close modals when clicking outside
    $(document).on('click', function(e) {
        if ($(e.target).is('#wpcmt-aisays-modal')) {
            $('#wpcmt-aisays-modal').hide();
        }
    });

    // Escape key to close modals
    $(document).on('keyup', function(e) {
        if (e.keyCode === 27) {
            $('#wpcmt-aisays-modal').hide();
        }
    });
});