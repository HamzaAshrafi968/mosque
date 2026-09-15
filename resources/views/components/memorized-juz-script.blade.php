<script>
    (function () {
        const input = document.getElementById('memorized_juz_input');
        if (!input) return;

        const boxes = document.querySelectorAll('[data-juz-checkbox]');

        input.addEventListener('input', function () {
            const count = Math.max(0, Math.min(30, Math.floor(parseFloat(input.value) || 0)));
            boxes.forEach(function (box) {
                box.checked = parseInt(box.dataset.juzCheckbox, 10) <= count;
            });
        });
    })();
</script>
