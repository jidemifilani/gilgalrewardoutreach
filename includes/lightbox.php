<?php
/**
 * The gallery lightbox markup, shared by gallery.php and outreach.php.
 * Behaviour lives in assets/js/main.js; with JS off the page simply never
 * opens it, and every photo is still visible in the grid.
 */
?>
<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Photo viewer">
  <button class="lightbox-close" type="button" aria-label="Close photo viewer"><?= icon('close') ?></button>
  <button class="lightbox-nav lightbox-prev" type="button" aria-label="Previous photo"><?= icon('arrow') ?></button>
  <button class="lightbox-nav lightbox-next" type="button" aria-label="Next photo"><?= icon('arrow') ?></button>

  <div class="lightbox-inner">
    <img src="" alt="">
    <p class="lightbox-cap"><strong></strong><span></span></p>
  </div>
</div>
