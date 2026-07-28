</main>
<!-- Bootstrap 5 JS Bundle (incluye Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= BASE_URL ?>assets/js/main.js"></script>

<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('<?= BASE_URL ?>service-worker.js')
      .catch(function (e) { console.warn('SW no registrado:', e); });
  });
}
</script>
<!-- Lightbox de imágenes -->
<div id="imgLightbox" onclick="this.style.display='none'" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:2000;align-items:center;justify-content:center;cursor:zoom-out;">
  <img id="imgLightboxImg" src="" alt="" style="max-width:92%;max-height:92%;border-radius:8px;box-shadow:0 0 30px rgba(0,0,0,.6);">
</div>
<script>
document.addEventListener('click', function (e) {
  const img = e.target.closest('img.js-lightbox');
  if (!img) return;
  document.getElementById('imgLightboxImg').src = img.dataset.full || img.src;
  document.getElementById('imgLightbox').style.display = 'flex';
});
</script>
</body>
</html>