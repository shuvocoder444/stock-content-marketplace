/* ============================================================
   Stock Content Marketplace — Frontend JS
   ============================================================ */
(function($){
  'use strict';

  const SCM = {
    currentPage  : 1,
    loading      : false,
    currentAssetId: null,
    filters      : {},

    init() {
      this.bindSearch();
      this.bindFilters();
      this.bindDropdowns();
      this.bindCardActions();
      this.bindLoadMore();
      this.bindModals();
      this.bindSingleActions();
      this.bindShareButtons();
      this.bindViewToggle();
      this.bindVideoHover();
      this.bindTabsAdmin();
      this.initDynamicColor();
    },

    /* ── Search ── */
    bindSearch() {
      const $form  = $('#scm-search-form');
      const $input = $('#scm-search-input');
      let   debounce;

      $form.on('submit', e => {
        e.preventDefault();
        SCM.doSearch( 1 );
      });

      $input.on('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => SCM.doSearch(1), 450);
      });

      $('#scm-sort').on('change', () => SCM.doSearch(1));
    },

    doSearch(page) {
      if (SCM.loading) return;
      SCM.loading   = true;
      SCM.currentPage = page;

      const $grid = $('#scm-asset-grid');
      if (page === 1) $grid.html(SCM.skeleton(6));

      const data = {
        action  : 'scm_search',
        nonce   : scm_ajax.nonce,
        keyword : $('#scm-search-input').val() || '',
        orderby : $('#scm-sort').val() || 'date',
        paged   : page,
        ...SCM.filters,
      };

      $.post(scm_ajax.ajax_url, data)
        .done(res => {
          if (!res.success) return;
          const html = res.data.html;
          if (page === 1) {
            $grid.html(html);
          } else {
            $grid.append(html);
          }
          $('#scm-results-count').text(res.data.total + ' results');
          const $lb = $('#scm-load-more');
          if (res.data.current_page >= res.data.max_pages) {
            $lb.hide();
          } else {
            $lb.show().data('page', page);
          }
        })
        .fail(() => {
          if (page === 1) $grid.html('<p class="scm-error">Search failed. Please try again.</p>');
        })
        .always(() => { SCM.loading = false; });
    },

    /* ── Horizontal Dropdown Toggles ── */
    bindDropdowns() {
      // Toggle dropdown open/close
      $(document).on('click', '.scm-dropdown-btn', function(e){
        e.stopPropagation();
        const $dropdown = $(this).closest('.scm-filter-dropdown');
        const isOpen    = $dropdown.hasClass('is-open');
        // Close all dropdowns
        $('.scm-filter-dropdown').removeClass('is-open');
        // Open clicked one if it was closed
        if (!isOpen) $dropdown.addClass('is-open');
      });

      // Close dropdowns when clicking outside
      $(document).on('click', function(e){
        if (!$(e.target).closest('.scm-filter-dropdown').length) {
          $('.scm-filter-dropdown').removeClass('is-open');
        }
      });
    },

    /* ── Filters (horizontal dropdown links) ── */
    bindFilters() {
      $(document).on('click', '.scm-filter-link', function(e){
        e.preventDefault();
        const $link  = $(this);
        const filter = $link.data('filter');
        const value  = String($link.data('value') ?? '');

        // Toggle active: clear others in same dropdown, toggle self
        const $siblings = $link.closest('.scm-dropdown-content, .scm-filter-list').find(`.scm-filter-link[data-filter="${filter}"]`);
        $siblings.removeClass('active');

        if (SCM.filters[filter] === value && value !== '') {
          // Deselect if already active
          delete SCM.filters[filter];
        } else {
          $link.addClass('active');
          if (value !== '') {
            SCM.filters[filter] = value;
          } else {
            delete SCM.filters[filter];
          }
        }

        // Update dropdown button label to show active filter
        const $btn = $link.closest('.scm-filter-dropdown').find('.scm-dropdown-btn');
        if (SCM.filters[filter]) {
          $btn.addClass('scm-btn-filtered');
        } else {
          $btn.removeClass('scm-btn-filtered');
        }

        // Close the dropdown
        $link.closest('.scm-filter-dropdown').removeClass('is-open');

        SCM.doSearch(1);
      });
    },

    /* ── Card Actions ── */
    bindCardActions() {
      $(document).on('click', '.scm-btn-favorite', function(e){
        e.preventDefault();
        const $btn    = $(this);
        const assetId = $btn.data('id');
        if (!scm_ajax.is_user_logged_in) {
          SCM.toast('Please log in to save favorites.', 'warning');
          return;
        }
        $.post(scm_ajax.ajax_url, { action:'scm_toggle_favorite', nonce:scm_ajax.nonce, asset_id:assetId })
          .done(res => {
            if (!res.success) { SCM.toast(res.data.message || 'Error.', 'error'); return; }
            const added = res.data.action === 'added';
            $btn.toggleClass('is-favorited', added);
            $btn.find('.dashicons').toggleClass('dashicons-heart', true);
            SCM.toast(res.data.message, added ? 'success' : 'info');
          });
      });

      $(document).on('click', '.scm-btn-collection', function(e){
        e.preventDefault();
        if (!scm_ajax.is_user_logged_in) { SCM.toast('Please log in.', 'warning'); return; }
        SCM.currentAssetId = $(this).data('id');
        SCM.openCollectionModal();
      });

      $(document).on('click', '.scm-btn-download', function(e){
        e.preventDefault();
        const $btn    = $(this);
        const assetId = $btn.data('id');
        const premium = $btn.data('premium') == 1;
        if (premium) {
          SCM.openPremiumPopup(assetId);
        } else {
          SCM.doDownload(assetId, $btn);
        }
      });

      $(document).on('click', '.scm-btn-unlock, .scm-btn-premium', function(e){
        e.preventDefault();
        SCM.openPremiumPopup($(this).data('id'));
      });

      $(document).on('click', '.scm-btn-preview', function(e){
        e.preventDefault();
        const assetId = $(this).data('id');
        SCM.openPreview(assetId);
      });

      $(document).on('click', '.scm-crown-badge', function(e){
        e.stopPropagation();
        const assetId = $(this).closest('.scm-card').data('id');
        SCM.openPremiumPopup(assetId);
      });
    },

    doDownload(assetId, $btn) {
      const orig = $btn.html();
      $btn.html('<span class="dashicons dashicons-update scm-spin"></span> Preparing...').prop('disabled', true);
      $.post(scm_ajax.ajax_url, { action:'scm_download_asset', nonce:scm_ajax.nonce, asset_id:assetId })
        .done(res => {
          if (res.success && res.data.download_url) {
            window.location.href = res.data.download_url;
            SCM.toast('Download started!', 'success');
          } else {
            if (res.data && res.data.require_purchase) {
              SCM.openPremiumPopup(assetId);
            } else {
              SCM.toast(res.data && res.data.message ? res.data.message : 'Download failed.', 'error');
            }
          }
        })
        .fail(() => SCM.toast('Download error.', 'error'))
        .always(() => { $btn.html(orig).prop('disabled', false); });
    },

    /* ── Load More ── */
    bindLoadMore() {
      $(document).on('click', '#scm-load-more', function(){
        const nextPage = parseInt($(this).data('page') || 1) + 1;
        SCM.doSearch(nextPage);
      });
    },

    /* ── Modals ── */
    bindModals() {
      $(document).on('click', '.scm-modal-close, .scm-modal-overlay', function(){
        $(this).closest('.scm-modal').fadeOut(200);
      });

      // Premium popup buy now
      $(document).on('click', '#scm-btn-buy-now', function(){
        if (!SCM.currentAssetId) return;
        $.post(scm_ajax.ajax_url, { action:'scm_add_to_cart', nonce:scm_ajax.nonce, asset_id:SCM.currentAssetId })
          .done(res => {
            if (res.success) {
              window.location.href = res.data.checkout_url;
            } else {
              SCM.toast(res.data.message || 'Error.', 'error');
            }
          });
      });

      // Subscribe
      $(document).on('click', '.scm-btn-subscribe', function(){
        const settings = typeof scm_settings !== 'undefined' ? scm_settings : {};
        if (settings.subscription_url) {
          window.location.href = settings.subscription_url;
        } else {
          SCM.toast('Please contact admin for subscription.', 'info');
        }
      });

      // Collection create & add
      $(document).on('click', '#scm-create-new-collection', function(){
        const name = $('#scm-new-collection-name').val().trim();
        if (!name) { SCM.toast('Enter a collection name.', 'warning'); return; }
        $.post(scm_ajax.ajax_url, { action:'scm_create_collection', nonce:scm_ajax.nonce, name })
          .done(res => {
            if (!res.success) { SCM.toast(res.data.message, 'error'); return; }
            if (SCM.currentAssetId) {
              $.post(scm_ajax.ajax_url, { action:'scm_add_to_collection', nonce:scm_ajax.nonce, collection_id:res.data.id, asset_id:SCM.currentAssetId })
                .done(r2 => {
                  SCM.toast(r2.data.message, 'success');
                  $('#scm-collection-modal').fadeOut(200);
                });
            }
          });
      });

      // Delete collection
      $(document).on('click', '.scm-delete-collection', function(){
        if (!confirm('Delete this collection?')) return;
        const id = $(this).data('id');
        $.post(scm_ajax.ajax_url, { action:'scm_delete_collection', nonce:scm_ajax.nonce, collection_id:id })
          .done(res => {
            if (res.success) { $(this).closest('.scm-collection-card').remove(); SCM.toast(res.data.message, 'success'); }
          });
      });
    },

    openPremiumPopup(assetId) {
      SCM.currentAssetId = assetId;
      const $modal = $('#scm-premium-popup');
      $modal.fadeIn(200);
    },

    openCollectionModal() {
      const $modal = $('#scm-collection-modal');
      const $list  = $('#scm-collection-list');
      $list.html('<p>Loading...</p>');
      $modal.fadeIn(200);

      $.get(scm_ajax.ajax_url + '?action=scm_get_collections_html&nonce=' + scm_ajax.nonce)
        .done(res => {
          if (res.success && res.data.html) {
            $list.html(res.data.html);
          } else {
            $list.html('<p>No collections yet.</p>');
          }
        })
        .fail(() => $list.html('<p>No collections found.</p>'));
    },

    openPreview(assetId) {
      const $modal   = $('#scm-preview-modal');
      const $content = $('#scm-preview-content');
      const $card    = $(`.scm-card[data-id="${assetId}"]`);
      const imgSrc   = $card.find('img').attr('src');
      $content.html(imgSrc ? `<img src="${imgSrc}" alt="Preview" />` : '<p>No preview available.</p>');
      $modal.fadeIn(200);
    },

    /* ── Single Asset Page Actions ── */
    bindSingleActions() {
      // Copy link button
      $(document).on('click', '.scm-copy-link', function(){
        const url = $(this).data('url');
        if (navigator.clipboard) {
          navigator.clipboard.writeText(url).then(() => SCM.toast('Link copied!', 'success'));
        } else {
          // Fallback
          const ta = document.createElement('textarea');
          ta.value = url;
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
          SCM.toast('Link copied!', 'success');
        }
      });
    },

    /* ── Share Buttons ── */
    bindShareButtons() {
      $(document).on('click', '.scm-share-btn:not(.scm-copy-link)', function(e){
        const href = $(this).attr('href');
        if (href && href !== '#') {
          e.preventDefault();
          window.open(href, '_blank', 'width=600,height=400');
        }
      });
    },

    /* ── View Toggle ── */
    bindViewToggle() {
      $(document).on('click', '.scm-btn-icon[data-view]', function(){
        const view  = $(this).data('view');
        const $grid = $('#scm-asset-grid');
        $('.scm-btn-icon[data-view]').removeClass('active');
        $(this).addClass('active');
        if (view === 'list') {
          $grid.addClass('scm-list-view');
        } else {
          $grid.removeClass('scm-list-view');
        }
      });
    },

    /* ── Video Hover ── */
    bindVideoHover() {
      $(document).on('mouseenter', '.scm-card', function(){
        const $video = $(this).find('.scm-card-video-preview');
        if ($video.length) $video[0].play();
      }).on('mouseleave', '.scm-card', function(){
        const $video = $(this).find('.scm-card-video-preview');
        if ($video.length) { $video[0].pause(); $video[0].currentTime = 0; }
      });
    },

    /* ── Admin Settings Tabs ── */
    bindTabsAdmin() {
      $(document).on('click', '.scm-tab-link', function(e){
        e.preventDefault();
        const target = $(this).attr('href');
        $('.scm-tab-link').removeClass('active');
        $(this).addClass('active');
        $('.scm-tab-pane').removeClass('active');
        $(target).addClass('active');
      });

      // Premium fields toggle
      const $isPremium = $('#scm_is_premium');
      if ($isPremium.length) {
        const toggle = () => {
          $('.scm-premium-fields').toggleClass('active', $isPremium.is(':checked'));
        };
        toggle();
        $isPremium.on('change', toggle);
      }
    },

    /* ── Dynamic Brand Color ── */
    initDynamicColor() {
      const color = $('body').data('scm-color');
      if (color) {
        document.documentElement.style.setProperty('--scm-primary', color);
      }
    },

    /* ── Toast Notification ── */
    toast(message, type = 'success') {
      const colors = { success:'#10b981', error:'#ef4444', warning:'#f59e0b', info:'#6c63ff' };
      const $toast = $('<div class="scm-toast">').text(message).css({
        position      : 'fixed',
        bottom        : '24px',
        right         : '24px',
        background    : colors[type] || colors.info,
        color         : '#fff',
        padding       : '12px 24px',
        borderRadius  : '10px',
        fontSize      : '14px',
        fontWeight    : '600',
        zIndex        : 999999,
        boxShadow     : '0 4px 20px rgba(0,0,0,.2)',
        opacity       : 0,
        transform     : 'translateY(16px)',
        transition    : 'all .25s ease',
      });
      $('body').append($toast);
      setTimeout(() => $toast.css({ opacity:1, transform:'translateY(0)' }), 10);
      setTimeout(() => $toast.css({ opacity:0, transform:'translateY(16px)' }), 2800);
      setTimeout(() => $toast.remove(), 3100);
    },

    /* ── Skeleton Loader ── */
    skeleton(count) {
      let html = '';
      for (let i = 0; i < count; i++) {
        html += `<div class="scm-card">
          <div class="scm-card-thumb scm-skeleton" style="height:180px;"></div>
          <div class="scm-card-info">
            <div class="scm-skeleton" style="height:14px;border-radius:4px;margin-bottom:8px;"></div>
            <div class="scm-skeleton" style="height:12px;border-radius:4px;width:60%;"></div>
          </div>
        </div>`;
      }
      return html;
    },
  };

  $(document).ready(() => SCM.init());

  // Add spin animation for loading
  $('<style>.scm-spin{animation:spin .8s linear infinite}@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}</style>').appendTo('head');

})(jQuery);
