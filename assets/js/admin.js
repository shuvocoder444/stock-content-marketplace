/* ============================================================
   Stock Content Marketplace — Admin JS
   ============================================================ */
(function($){
  'use strict';

  /* ── Media Uploader ── */
  function mediaUploader(btnSelector, hiddenId, displayId, multiple) {
    $(document).on('click', btnSelector, function(e){
      e.preventDefault();
      const $btn = $(this);
      // Debug: ensure media library is available
      if ( typeof wp === 'undefined' || typeof wp.media === 'undefined' ) {
        console.error('WP media is not available on this page.');
        alert('Media library is not loaded. Please ensure the page allows media uploads (edit the asset in admin).');
        return;
      }
      console.log('Opening media frame for', btnSelector, hiddenId, displayId);
      const frame = wp.media({
        title   : 'Select File',
        multiple: multiple || false,
      });

      frame.on('select', function(){
        if (multiple) {
          const attachments = frame.state().get('selection').toJSON();
          const ids = attachments.map(a => a.id);
          const currentIds = ($('#' + hiddenId).val() || '').split(',').filter(Boolean);
          const allIds = [...new Set([...currentIds, ...ids])];
          $('#' + hiddenId).val(allIds.join(','));

          attachments.forEach(a => {
            if (a.sizes && a.sizes.thumbnail) {
              $('#' + displayId).append(`<img src="${a.sizes.thumbnail.url}" style="width:70px;height:70px;object-fit:cover;border-radius:6px;border:2px solid #ddd;margin:4px;">`);
            }
          });
        } else {
          const attachment = frame.state().get('selection').first().toJSON();
          $('#' + hiddenId).val(attachment.id);
          if (displayId) {
            $('#' + displayId).val(attachment.url || attachment.filename);
          }
        }
      });
      frame.open();
    });
  }

  mediaUploader('.scm-upload-gallery',  'scm_gallery_ids',      'scm-gallery-preview-display', true);
  mediaUploader('.scm-upload-file',     'scm_download_file_id', 'scm_download_file_url',       false);
  mediaUploader('.scm-upload-featured-video', 'scm_featured_video_id', 'scm_featured_video_url', false);

  /* ── Settings Tabs ── */
  $(document).on('click', '.scm-tab-link', function(e){
    e.preventDefault();
    const target = $(this).attr('href');
    $('.scm-tab-link').removeClass('active');
    $(this).addClass('active');
    $('.scm-tab-pane').removeClass('active');
    $(target).addClass('active');
  });

  /* ── Auto-create WooCommerce Product ── */
  $(document).on('click', '.scm-create-woo-product', function(){
    const $btn    = $(this);
    const postId  = $('#post_ID').val();
    if (!postId) { alert('Save the post first.'); return; }

    $btn.text('Creating...').prop('disabled', true);

    $.post(scm_admin_ajax.ajax_url, {
      action   : 'scm_create_woo_product',
      nonce    : scm_admin_ajax.nonce,
      asset_id : postId,
    }).done(res => {
      if (res.success) {
        alert(res.data.message);
        $('[name="scm_woo_product_id"]').val(res.data.product_id);
      } else {
        alert(res.data.message || 'Error creating product.');
      }
    }).fail(() => alert('Request failed.')).always(() => {
      $btn.text('Auto-Create WC Product').prop('disabled', false);
    });
  });

  /* ── Premium Fields Toggle ── */
  function togglePremiumFields() {
    const isPremium = $('#scm_is_premium').is(':checked');
    $('.scm-premium-fields').toggleClass('active', isPremium);
  }
  $('#scm_is_premium').on('change', togglePremiumFields);
  togglePremiumFields();

})(jQuery);
