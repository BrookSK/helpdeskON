    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Polling de notificações
        function checkNotifications() {
            fetch('<?= baseUrl("notifications/getUnread") ?>')
                .then(r => r.json())
                .then(data => {
                    document.querySelectorAll('.notification-count-sidebar').forEach(b => {
                        if (data.count > 0) {
                            b.textContent = data.count;
                            b.style.display = 'inline-block';
                        } else {
                            b.style.display = 'none';
                        }
                    });
                })
                .catch(() => {});
        }
        setInterval(checkNotifications, 30000);
        checkNotifications();
    </script>
</body>
</html>
