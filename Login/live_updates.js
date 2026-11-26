// Shows a "Refresh" banner when orders change. Add to a page with:  <script src="live_updates.js"></script>
(function () {
    let first = null, firstNew = 0, banner;

    function show(text) {
        if (!banner) {
            banner = document.createElement('div');
            banner.className = 'fixed bottom-6 left-1/2 z-[90] flex -translate-x-1/2 items-center gap-4 rounded-full bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-2xl';
            banner.innerHTML = '<span id="ff-live-text"></span>' +
                '<button type="button" class="rounded-full bg-[#ff4d0a] px-4 py-1.5 font-bold hover:bg-orange-500">Refresh</button>';
            banner.querySelector('button').addEventListener('click', () => location.reload());
            document.body.appendChild(banner);
        }
        banner.querySelector('#ff-live-text').textContent = text;
    }

    async function tick() {
        if (document.hidden) return;
        try {
            const res = await fetch('poll.php', { cache: 'no-store' });
            if (!res.ok) return;
            const d = await res.json();
            if (!d.sig) return;
            if (first === null) { first = d.sig; firstNew = d.new; return; }
            if (d.sig !== first) show(d.new > firstNew ? 'New order received' : 'Orders updated');
        } catch (e) { /* ignore network errors */ }
    }

    tick();
    setInterval(tick, 15000);
})();
