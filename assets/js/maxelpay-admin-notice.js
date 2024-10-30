jQuery(document).ready(function() {

    var stg_keys = '#woocommerce_maxelpay_maxelpay_stg_payment_key,#woocommerce_maxelpay_maxelpay_stg_payment_secret_key';
    var live_keys = '#woocommerce_maxelpay_maxelpay_payment_key,#woocommerce_maxelpay_maxelpay_payment_secret_key';
    
    jQuery('.maxelpay_modal-toggle').on('click', function (e) {

        e.preventDefault();
        jQuery('.maxelpay_modal').toggleClass('is-visible');
        var body = jQuery("#maxelpay_custom_maxelpay_modal").contents().find("body");
        var target_link = jQuery(body).find('#plugin-information-footer a').attr('href');
        var add_data = jQuery(body).find('#plugin-information-footer').html("<a data-slug='woocommerce' id='plugin_install_from_iframe' class='button button-primary right' href=" + target_link + " target='_blank'>Install Now</a>")
    
    });

    jQuery('input#woocommerce_maxelpay_maxelpay_webhook_url').after('<div class="maxelpay-clipboard-container"><button type="button" id="maxelpay-webhook-url" class="maxelpay-copy"></button><span class="maxelpay-clipboard-hover">Click to copy</span></div>');
    
    jQuery('.maxelpay-copy').on('click', function (e) {
        
        jQuery(this).removeClass('maxelpay-copy');
        jQuery('.maxelpay-clipboard-hover').hide();
        var copy_txt = jQuery("input#woocommerce_maxelpay_maxelpay_webhook_url");
        var decrypt_val = jQuery('input#woocommerce_maxelpay_maxelpay_webhook_url').val();
        var temp_txt_area = jQuery('<textarea>');
        jQuery('body').append(temp_txt_area);
        temp_txt_area.val(decrypt_val).select();
        document.execCommand('copy');
        temp_txt_area.remove();
        copy_txt.select();
        jQuery(this).addClass('maxelpay-copy-done')
        
        setTimeout(function() {

            jQuery('#maxelpay-webhook-url').removeClass('maxelpay-copy-done');
            jQuery('#maxelpay-webhook-url').addClass('maxelpay-copy');
            jQuery('.maxelpay-clipboard-hover').show();

        }, 1000);

    });

    maxelpay_toggle_settings();

    jQuery('select#woocommerce_maxelpay_maxelpay_environment').on('change', function() {

        maxelpay_toggle_settings(); 

    });

    function maxelpay_toggle_settings() {

        var environment = jQuery('#woocommerce_maxelpay_maxelpay_environment').val();
        
        if (environment === 'stg') {
          
            jQuery(live_keys).closest('tr').hide();
            
            jQuery(stg_keys).closest('tr').show();
            
        } else {

            jQuery(live_keys).closest('tr').show();

            jQuery(stg_keys).closest('tr').hide();
           
        }
    }
});
