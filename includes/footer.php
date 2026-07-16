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
</body>
</html>