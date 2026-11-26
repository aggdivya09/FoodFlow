/*! FoodFlow add-on (v2): search + add to cart + favourites on every logged-in page.
 *  Drop this file in the project root and add ONE <script> line per page.
 *  It finds dish cards by itself (h3 name + ₹ price + "Add" button), so no card markup changes.
 */
(function () {
  "use strict";

  /* ---------- setup ---------- */
  const SCRIPT_URL = (document.currentScript && document.currentScript.src) || location.href;
  let rawBase = SCRIPT_URL.replace(/[^/]*$/, "");
  const BASE = rawBase.replace(/dashboard\/$/, "");          // project root URL
  const PROFILE_PAGE = BASE + "dashboard/profile.php";
  const CHECKOUT_PAGE = BASE + "Cart/checkout.php";
  if (!window.FF_CHECKOUT_URL) {
    window.FF_CHECKOUT_URL = CHECKOUT_PAGE;
  }
  const INDEX_PAGES = [                                   // pages used for cross-page search
    "categories/pizzaafterlogin.html", "categories/burgerafterlogin.html",
    "categories/pastafterlogin.html", "categories/tacosafterlogin.html",
    "categories/beveragesafterlogin.html", "categories/dessertafterlogin.html",
    "categories/combosafterlogin.html", "Menu/menuafterlogin.html"
  ];

  try {                                                   // dashboard/profile pass the logged-in user
    if (window.FF_USER) localStorage.setItem("ff_user", String(window.FF_USER));
    if (window.FF_PROFILE) localStorage.setItem("ff_profile", JSON.stringify(window.FF_PROFILE));
  } catch (e) {}
  const USER = (function () { try { return localStorage.getItem("ff_user"); } catch (e) { return null; } })() || "guest";
  const K_CART = "ff2_cart_" + USER, K_FAV = "ff2_favs_" + USER;

  const read = k => { try { return JSON.parse(localStorage.getItem(k)) || {}; } catch (e) { return {}; } };
  const write = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} };
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const rupee = n => "₹" + Number(n).toLocaleString("en-IN");
  const slug = s => s.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");

  const REG = {};                                         // id -> dish, for every dish seen on screen
  const reg = it => (REG[it.id] = REG[it.id] || it);

  /* ---------- find dish cards on a page (live or fetched) ---------- */
  const isAdd = b => /^add( to cart)?$/i.test(b.textContent.trim());
  const countAdds = el => [...el.querySelectorAll("button")].filter(isAdd).length;
  const CATS = { pizza: "Pizza", burger: "Burgers", pasta: "Pasta", tacos: "Tacos", beverages: "Beverages", dessert: "Desserts", combos: "Combos", menu: "Menu" };
  function pageCat(url) {
    const m = url.toLowerCase().match(/(pizza|burger|pasta|tacos|beverages|dessert|combos|menu)/);
    return m ? CATS[m[1]] : "";
  }
  function extractCards(root, baseHref) {
    const out = [];
    root.querySelectorAll("button").forEach(btn => {
      if (!isAdd(btn)) return;
      let card = btn.parentElement;
      while (card && !card.querySelector("h3")) card = card.parentElement;
      if (!card) return;
      while (card.parentElement && card.parentElement.tagName !== "BODY" && countAdds(card.parentElement) === 1) card = card.parentElement;
      const name = card.querySelector("h3").textContent.trim();
      const price = card.textContent.match(/₹\s*([\d,]+)/);
      if (!name || !price) return;
      const img = card.querySelector("img"), src = img && img.getAttribute("src");
      const p = card.querySelector("p");
      out.push({
        card, btn,
        item: {
          id: slug(name), name, price: +price[1].replace(/,/g, ""),
          img: src ? new URL(src, baseHref).href : "", desc: p ? p.textContent.trim() : "",
          cat: pageCat(baseHref), href: baseHref
        }
      });
    });
    return out;
  }
  const PAGE = extractCards(document, location.href);

  /* ---------- cart + favourites ---------- */
  const getCart = () => read(K_CART), getFavs = () => read(K_FAV);
  const cartCount = () => Object.values(getCart()).reduce((a, i) => a + i.qty, 0);
  const cartTotal = () => Object.values(getCart()).reduce((a, i) => a + i.qty * i.price, 0);
  const slim = it => ({ id: it.id, name: it.name, price: it.price, img: it.img, cat: it.cat, href: it.href });
  const changed = () => { syncUI(); document.dispatchEvent(new CustomEvent("ff:change")); };

  function addToCart(item, delta) {
    delta = delta || 1;
    const cart = getCart(), cur = cart[item.id] || Object.assign(slim(item), { qty: 0 });
    cur.qty += delta;
    if (cur.qty <= 0) delete cart[item.id]; else cart[item.id] = cur;
    write(K_CART, cart); changed();
  }
  function removeFromCart(id) { const c = getCart(); delete c[id]; write(K_CART, c); changed(); }
  function toggleFav(item) {
    const favs = getFavs(), adding = !favs[item.id];
    if (adding) favs[item.id] = slim(item); else delete favs[item.id];
    write(K_FAV, favs);
    toast(item.name + (adding ? " added to favourites" : " removed from favourites"));
    changed();
  }

  /* ---------- toast ---------- */
  let toastTimer;
  function toast(msg) {
    let t = document.getElementById("ff-toast");
    if (!t) {
      t = document.createElement("div");
      t.id = "ff-toast"; t.setAttribute("role", "status");
      t.className = "pointer-events-none fixed bottom-6 left-1/2 z-[80] -translate-x-1/2 rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white opacity-0 shadow-lg transition-opacity duration-200";
      document.body.appendChild(t);
    }
    t.textContent = msg; t.classList.remove("opacity-0");
    clearTimeout(toastTimer); toastTimer = setTimeout(() => t.classList.add("opacity-0"), 1800);
  }

  /* ---------- wire the page's cards (Add + heart) ---------- */
  PAGE.forEach(({ card, btn, item }) => {
    reg(item);
    card.dataset.ffId = item.id;
    btn.dataset.ffAdd = item.id;
    let heart = card.querySelector("button i.fa-heart");
    heart = heart && heart.closest("button");
    if (!heart) {                                         // card has no heart yet: add one
      const img = card.querySelector("img"), box = img ? img.parentElement : card;
      if (getComputedStyle(box).position === "static") box.classList.add("relative");
      heart = document.createElement("button");
      heart.type = "button";
      heart.className = "absolute right-3 top-3 z-10 rounded-full bg-white p-2 text-gray-500 shadow-md hover:text-red-500";
      heart.innerHTML = '<i class="fa-regular fa-heart text-xl"></i>';
      box.appendChild(heart);
    }
    heart.dataset.ffHeart = item.id;
  });

  /* ---------- cart drawer ---------- */
  function buildDrawer() {
    if (document.getElementById("ff-drawer")) return;
    const w = document.createElement("div");
    w.innerHTML =
      '<div id="ff-overlay" class="fixed inset-0 z-[60] hidden bg-black/40"></div>' +
      '<aside id="ff-drawer" aria-label="Your cart" class="fixed right-0 top-0 z-[61] flex h-full w-full max-w-md translate-x-full flex-col bg-white shadow-2xl transition-transform duration-300">' +
      '<div class="flex items-center justify-between border-b border-gray-200 px-5 py-4"><h2 class="text-xl font-extrabold text-slate-900">Your Cart</h2>' +
      '<button type="button" data-close-cart aria-label="Close cart" class="rounded-full p-2 text-slate-600 hover:bg-gray-100"><i class="fa-solid fa-xmark text-xl"></i></button></div>' +
      '<div id="ff-cart-items" class="flex-1 overflow-y-auto px-5 py-3"></div><div id="ff-cart-footer" class="border-t border-gray-200 px-5 py-4"></div></aside>';
    document.body.appendChild(w);
  }
  const imgTag = (it, s) => it.img
    ? '<img src="' + esc(it.img) + '" alt="" class="' + s + ' rounded-xl object-cover">'
    : '<div class="' + s + ' flex items-center justify-center rounded-xl bg-[#fff3e6] text-orange-500"><i class="fa-solid fa-utensils"></i></div>';
  function openCart() { buildDrawer(); renderDrawer(); closeSearch(); document.getElementById("ff-overlay").classList.remove("hidden"); document.getElementById("ff-drawer").classList.remove("translate-x-full"); }
  function closeCart() { const d = document.getElementById("ff-drawer"); if (!d) return; d.classList.add("translate-x-full"); document.getElementById("ff-overlay").classList.add("hidden"); }
  function renderDrawer() {
    const list = document.getElementById("ff-cart-items"), foot = document.getElementById("ff-cart-footer");
    if (!list) return;
    const items = Object.values(getCart());
    if (!items.length) {
      list.innerHTML = '<div class="flex h-full flex-col items-center justify-center gap-2 text-center text-gray-500"><i class="fa-solid fa-cart-shopping text-4xl text-orange-300"></i><p class="font-bold text-slate-800">Your cart is empty</p><p class="text-sm">Add a dish and it will show up here.</p></div>';
      foot.innerHTML = ""; return;
    }
    list.innerHTML = items.map(it => reg(it) && (
      '<div class="flex items-center gap-3 border-b border-gray-100 py-3">' + imgTag(it, "h-16 w-16 shrink-0") +
      '<div class="min-w-0 flex-1"><p class="truncate font-bold text-slate-900">' + esc(it.name) + '</p><p class="text-sm text-gray-500">' + rupee(it.price) + ' each</p>' +
      '<div class="mt-1 flex items-center gap-2"><button type="button" data-ff-dec="' + esc(it.id) + '" aria-label="Decrease quantity" class="h-7 w-7 rounded-full bg-gray-100 font-bold hover:bg-orange-100">−</button>' +
      '<span class="w-6 text-center font-bold">' + it.qty + '</span>' +
      '<button type="button" data-ff-inc="' + esc(it.id) + '" aria-label="Increase quantity" class="h-7 w-7 rounded-full bg-gray-100 font-bold hover:bg-orange-100">+</button></div></div>' +
      '<div class="text-right"><p class="font-extrabold text-orange-600">' + rupee(it.price * it.qty) + '</p>' +
      '<button type="button" data-ff-remove="' + esc(it.id) + '" class="mt-1 text-xs font-semibold text-red-500 hover:underline">Remove</button></div></div>'
    )).join("");
    foot.innerHTML =
      '<div class="mb-3 flex items-center justify-between text-lg"><span class="font-semibold text-slate-700">Subtotal</span><span class="font-extrabold text-slate-900">' + rupee(cartTotal()) + '</span></div>' +
      '<button type="button" data-ff-checkout class="w-full rounded-xl bg-orange-600 py-3 font-bold text-white transition hover:bg-orange-700">Checkout</button>' +
      '<button type="button" data-ff-clear class="mt-2 w-full rounded-xl py-2 text-sm font-semibold text-gray-500 hover:text-red-500">Clear cart</button>';
  }

  /* ---------- keep every badge / heart in sync ---------- */
  function syncUI() {
    const n = cartCount(), favs = getFavs(), nf = Object.keys(favs).length;
    document.querySelectorAll("[data-cart-count]").forEach(el => { el.textContent = n; el.classList.toggle("hidden", !n); });
    document.querySelectorAll("[data-fav-count]").forEach(el => { el.textContent = nf; el.classList.toggle("hidden", !nf); });
    document.querySelectorAll("[data-ff-heart]").forEach(btn => {
      const on = !!favs[btn.dataset.ffHeart], i = btn.querySelector("i");
      btn.setAttribute("aria-pressed", on); btn.setAttribute("aria-label", on ? "Remove from favourites" : "Add to favourites");
      btn.classList.toggle("text-red-500", on); btn.classList.toggle("text-gray-500", !on);
      if (i) { i.classList.toggle("fa-solid", on); i.classList.toggle("fa-regular", !on); i.classList.toggle("text-red-500", on); }
    });
    renderDrawer();
  }

  /* ---------- search ---------- */
  const matches = (it, q) => q.split(/\s+/).every(t => (it.name + " " + it.cat + " " + it.desc).toLowerCase().includes(t));
  let indexCache;
  function getCatalog() {
    if (!indexCache) indexCache = (async () => {
      let lists; try { lists = JSON.parse(sessionStorage.getItem("ff2_index")); } catch (e) {}
      if (!lists) {
        lists = await Promise.all(INDEX_PAGES.map(async p => {
          try {
            const url = BASE + p, res = await fetch(url);
            if (!res.ok) return [];
            const doc = new DOMParser().parseFromString(await res.text(), "text/html");
            return extractCards(doc, url).map(x => x.item);
          } catch (e) { return []; }
        }));
        if (lists.some(l => l.length)) try { sessionStorage.setItem("ff2_index", JSON.stringify(lists)); } catch (e) {}
      }
      return lists.flat();
    })();
    return indexCache.then(rest => {
      const seen = new Set(), all = [];
      PAGE.map(x => x.item).concat(rest).forEach(it => { if (!seen.has(it.id)) { seen.add(it.id); all.push(it); } });
      return all;
    });
  }

  let panel;
  function showPanel(anchor, html) {
    if (!panel) {
      panel = document.createElement("div");
      panel.id = "ff-results";
      panel.className = "fixed z-[70] max-h-[70vh] overflow-y-auto rounded-2xl border border-gray-200 bg-white py-1 shadow-xl";
      document.body.appendChild(panel);
    }
    const r = anchor.getBoundingClientRect();
    panel.style.left = r.left + "px"; panel.style.top = (r.bottom + 6) + "px"; panel.style.width = Math.max(r.width, 320) + "px";
    panel.innerHTML = html; panel.classList.remove("hidden");
  }
  const hidePanel = () => panel && panel.classList.add("hidden");

  function resultRow(it) {
    reg(it);
    const here = it.href.split("#")[0] === location.href.split("#")[0];
    const title = here ? esc(it.name) : '<a href="' + esc(it.href) + '" class="hover:text-orange-600">' + esc(it.name) + "</a>";
    return '<div class="flex items-center gap-3 px-4 py-2 hover:bg-orange-50">' + imgTag(it, "h-10 w-10 shrink-0") +
      '<div class="min-w-0 flex-1"><p class="truncate text-sm font-bold text-slate-900">' + title + '</p><p class="text-xs text-gray-500">' + esc(it.cat || "Dish") + " · " + rupee(it.price) + "</p></div>" +
      '<button type="button" data-ff-heart="' + esc(it.id) + '" class="p-2 text-gray-500"><i class="fa-regular fa-heart text-lg"></i></button>' +
      '<button type="button" data-ff-add="' + esc(it.id) + '" class="rounded-lg bg-orange-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-orange-700">Add</button></div>';
  }

  // input = the text box, anchor = element the results hang under, filter = also hide non-matching cards on this page
  function bindSearch(input, anchor, filter) {
    async function run() {
      const q = input.value.trim().toLowerCase();
      if (filter) PAGE.forEach(({ card, item }) => { card.style.display = !q || matches(item, q) ? "" : "none"; });
      if (!q) return hidePanel();
      showPanel(anchor, '<p class="px-4 py-3 text-sm text-gray-500">Searching…</p>');
      const found = (await getCatalog()).filter(it => matches(it, q)).slice(0, 8);
      if (input.value.trim().toLowerCase() !== q) return;          // user kept typing
      showPanel(anchor, found.length ? found.map(resultRow).join("") : '<p class="px-4 py-3 text-sm text-gray-500">No dishes match “' + esc(input.value.trim()) + "”.</p>");
      syncUI();
    }
    input.addEventListener("input", run);
    input.addEventListener("focus", run);
    input.addEventListener("keydown", e => { if (e.key === "Escape") { input.value = ""; run(); input.blur(); } });
  }

  let bar;                                                // slide-down search bar for pages with no search box
  function openSearch() {
    if (!bar) {
      bar = document.createElement("div");
      bar.id = "ff-searchbar";
      bar.className = "fixed inset-x-0 top-0 z-[65] bg-white p-4 shadow-lg";
      bar.innerHTML = '<div class="mx-auto flex max-w-2xl items-center gap-3 rounded-full bg-gray-100 px-5 py-3"><i class="fa-solid fa-magnifying-glass text-gray-500"></i>' +
        '<input type="search" autocomplete="off" aria-label="Search dishes" placeholder="Search for your favorite dishes..." class="w-full bg-transparent text-sm outline-none placeholder:text-gray-500">' +
        '<button type="button" data-ff-close-search aria-label="Close search" class="text-gray-500 hover:text-slate-900"><i class="fa-solid fa-xmark"></i></button></div>';
      document.body.appendChild(bar);
      bindSearch(bar.querySelector("input"), bar.firstElementChild, false);
    }
    bar.classList.remove("hidden"); bar.querySelector("input").focus();
  }
  function closeSearch() { if (bar) { bar.classList.add("hidden"); bar.querySelector("input").value = ""; } hidePanel(); }

  /* ---------- nav: icons, search box, profile ---------- */
  function setupNav() {
    const inline = document.querySelector('input[type="search"], input[placeholder*="earch" i]');
    if (inline) bindSearch(inline, inline.parentElement, true);
    if (!document.querySelector("[data-open-cart]")) {
      const nav = document.querySelector("nav, header");
      if (nav && nav.lastElementChild) {
        const g = document.createElement("div");
        g.className = "flex items-center gap-1";
        const btn = (attr, icon, extra) => '<button type="button" ' + attr + ' class="relative flex h-11 w-11 items-center justify-center rounded-full text-slate-700 transition hover:bg-orange-50 hover:text-orange-600"><i class="' + icon + ' text-xl"></i>' + extra + "</button>";
        const badge = (a, c) => '<span ' + a + ' class="absolute -right-0.5 -top-0.5 hidden h-5 min-w-5 rounded-full ' + c + ' px-1 text-center text-xs font-bold leading-5 text-white">0</span>';
        g.innerHTML = (inline ? "" : btn('data-ff-open-search aria-label="Search"', "fa-solid fa-magnifying-glass", "")) +
          '<a href="' + PROFILE_PAGE + '#favourites" aria-label="Favourites" class="relative flex h-11 w-11 items-center justify-center rounded-full text-slate-700 transition hover:bg-orange-50 hover:text-red-500"><i class="fa-regular fa-heart text-xl"></i>' + badge("data-fav-count", "bg-red-500") + "</a>" +
          btn('data-open-cart aria-label="Open cart"', "fa-solid fa-cart-shopping", badge("data-cart-count", "bg-orange-600"));
        nav.lastElementChild.prepend(g);
      }
    }
    // static pages hard-code a user name: show the logged-in one and link to the profile page
    let p; try { p = JSON.parse(localStorage.getItem("ff_profile")); } catch (e) {}
    const nav = document.querySelector("nav");
    if (p && p.name && nav) {
      const parts = p.name.trim().split(/\s+/);
      const ini = (parts.length > 1 ? parts[0][0] + parts[parts.length - 1][0] : parts[0].slice(0, 2)).toUpperCase();
      const circle = nav.querySelector(".rounded-full.bg-orange-100"), nm = nav.querySelector("p.text-base.font-bold"), rl = nav.querySelector("p.text-sm.text-gray-500");
      if (circle) circle.textContent = ini; if (nm) nm.textContent = p.name; if (rl && p.role) rl.textContent = p.role;
      const box = circle && circle.parentElement;
      if (box) { box.style.cursor = "pointer"; box.title = "My profile"; box.addEventListener("click", () => { location.href = PROFILE_PAGE; }); }
    }
  }

  /* ---------- profile page helper: render the favourites list ---------- */
  function mountFavourites(sel) {
    const el = document.querySelector(sel);
    if (!el) return;
    function render() {
      const items = Object.values(getFavs());
      el.innerHTML = items.length ? items.map(it => reg(it) && (
        '<div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3">' + imgTag(it, "h-16 w-16 shrink-0") +
        '<div class="min-w-0 flex-1"><p class="truncate font-bold text-slate-900">' + esc(it.name) + '</p><p class="text-sm text-gray-500">' + esc(it.cat || "") + '</p><p class="font-extrabold text-orange-600">' + rupee(it.price) + "</p></div>" +
        '<div class="flex flex-col items-end gap-1"><button type="button" data-ff-add="' + esc(it.id) + '" class="rounded-lg bg-orange-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-orange-700">Add</button>' +
        '<button type="button" data-ff-heart="' + esc(it.id) + '" class="text-xs font-semibold text-red-500 hover:underline"><i class="fa-solid fa-heart"></i> Remove</button></div></div>'
      )).join("") : '<div class="col-span-full rounded-2xl border border-dashed border-orange-200 bg-white p-8 text-center text-gray-500"><i class="fa-regular fa-heart mb-2 text-3xl text-orange-300"></i><p class="font-bold text-slate-800">No favourites yet</p><p class="text-sm">Tap the heart on any dish to save it here.</p></div>';
      syncUI();
    }
    document.addEventListener("ff:change", render);
    render();
  }

  /* ---------- clicks (capture phase, so old inline onclick="toggleFavourite" is replaced) ---------- */
  document.addEventListener("click", e => {
    const t = e.target.closest("[data-ff-add],[data-ff-heart],[data-open-cart],[data-close-cart],[data-ff-inc],[data-ff-dec],[data-ff-remove],[data-ff-clear],[data-ff-checkout],[data-ff-open-search],[data-ff-close-search]");
    if (!t) {
      if (e.target.id === "ff-overlay") closeCart();
      if (panel && !panel.contains(e.target) && !(bar && bar.contains(e.target)) && !e.target.closest('input[type="search"], input[placeholder*="earch" i]')) hidePanel();
      return;
    }
    e.preventDefault(); e.stopImmediatePropagation();
    const d = t.dataset;
    if (d.ffAdd) {
      addToCart(REG[d.ffAdd]); toast(REG[d.ffAdd].name + " added to cart");
      if (t.tagName === "BUTTON" && !t.querySelector("i")) {
        const label = t.dataset.ffLabel || (t.dataset.ffLabel = t.textContent);
        t.textContent = "Added ✓"; setTimeout(() => { t.textContent = label; }, 900);
      }
    }
    else if (d.ffHeart) toggleFav(REG[d.ffHeart]);
    else if (d.ffInc) addToCart(REG[d.ffInc] || getCart()[d.ffInc], 1);
    else if (d.ffDec) addToCart(REG[d.ffDec] || getCart()[d.ffDec], -1);
    else if (d.ffRemove) removeFromCart(d.ffRemove);
    else if (t.hasAttribute("data-ff-clear")) { write(K_CART, {}); changed(); }
    else if (t.hasAttribute("data-open-cart")) openCart();
    else if (t.hasAttribute("data-close-cart")) closeCart();
    else if (t.hasAttribute("data-ff-open-search")) openSearch();
    else if (t.hasAttribute("data-ff-close-search")) closeSearch();
    else if (t.hasAttribute("data-ff-checkout")) {
      if (window.FF_CHECKOUT_URL) location.href = window.FF_CHECKOUT_URL; else toast("Checkout page isn't connected yet");
    }
  }, true);
  document.addEventListener("keydown", e => {
    if (e.key === "Escape") { closeCart(); closeSearch(); }
    if (e.key === "/" && !/INPUT|TEXTAREA/.test(document.activeElement.tagName)) {
      const inline = document.querySelector('input[type="search"], input[placeholder*="earch" i]');
      e.preventDefault(); inline && !bar ? inline.focus() : openSearch();
    }
  });
  window.addEventListener("storage", e => { if (e.key === K_CART || e.key === K_FAV) changed(); });
  window.addEventListener("resize", hidePanel);

  /* ---------- go ---------- */
  function init() { buildDrawer(); setupNav(); syncUI(); }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();

  window.FF = { addToCart, toggleFav, openCart, syncUI, mountFavourites, initSearch: function () {} };   // initSearch kept for the earlier customer.php
})();
