<?php
session_start();
require_once __DIR__ . '/_lib.php';
$uid = customer_guard();

$st = $pdo->prepare('SELECT full_name, phone, address FROM users WHERE user_id = ?');
$st->execute([$uid]);
$user = $st->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => '', 'phone' => '', 'address' => ''];
$active = 'checkout';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | FoodFlow</title>
    <link rel="icon" type="image/png" href="../favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="min-h-screen bg-[#fffaf5] text-slate-900">
<?php include __DIR__ . '/_header.php'; ?>

<main class="mx-auto max-w-6xl px-4 py-8">
    <h1 class="text-3xl font-extrabold">Checkout</h1>
    <p class="mt-1 text-gray-500">Confirm your delivery details and place the order.</p>

    <div id="error" class="mt-4 hidden rounded-xl bg-red-50 px-4 py-3 font-semibold text-red-600"></div>

    <div class="mt-6 grid gap-6 lg:grid-cols-5">
        <!-- Delivery + payment -->
        <section class="space-y-6 lg:col-span-3">
            <div class="rounded-3xl bg-white p-6 shadow">
                <h2 class="text-xl font-extrabold"><i class="fa-solid fa-location-dot mr-2 text-orange-500"></i>Delivery details</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="text-sm font-bold text-gray-600">Name</label>
                        <input value="<?= e($user['full_name']) ?>" disabled class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    </div>
                    <div>
                        <label for="phone" class="text-sm font-bold text-gray-600">Phone number</label>
                        <input id="phone" type="tel" maxlength="15" value="<?= e($user['phone']) ?>" placeholder="10-digit mobile number"
                               class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 outline-none focus:border-orange-500">
                    </div>
                    <div>
                        <label for="address" class="text-sm font-bold text-gray-600">Delivery address</label>
                        <textarea id="address" rows="3" maxlength="255" placeholder="House no, street, area, city, landmark"
                                  class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 outline-none focus:border-orange-500"><?= e($user['address']) ?></textarea>
                    </div>
                    <div>
                        <label for="note" class="text-sm font-bold text-gray-600">Note for the kitchen (optional)</label>
                        <input id="note" maxlength="255" placeholder="Less spicy, extra cheese..."
                               class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3 outline-none focus:border-orange-500">
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow">
                <h2 class="text-xl font-extrabold"><i class="fa-solid fa-wallet mr-2 text-orange-500"></i>Payment</h2>
                <div class="mt-4 space-y-3">
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-4 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50">
                        <input type="radio" name="payment" value="cod" checked class="accent-orange-600">
                        <span><b>Cash on delivery</b><br><span class="text-sm text-gray-500">Pay the rider when your food arrives.</span></span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-4 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50">
                        <input type="radio" name="payment" value="online" class="accent-orange-600">
                        <span><b>Pay online (demo)</b><br><span class="text-sm text-gray-500">Marks the order as paid. No real payment gateway is connected yet.</span></span>
                    </label>
                </div>
            </div>
        </section>

        <!-- Summary -->
        <aside class="lg:col-span-2">
            <div class="sticky top-24 rounded-3xl bg-white p-6 shadow">
                <h2 class="text-xl font-extrabold"><i class="fa-solid fa-bag-shopping mr-2 text-orange-500"></i>Your order</h2>
                <div id="lines" class="mt-4 divide-y"></div>
                <div class="mt-4 space-y-2 border-t pt-4">
                    <div class="flex justify-between text-gray-600"><span>Subtotal</span><span id="subtotal">₹0</span></div>
                    <div class="flex justify-between text-gray-600"><span>Delivery</span><span class="font-semibold text-green-600">Free</span></div>
                    <div class="flex justify-between text-xl font-extrabold"><span>Total</span><span id="total" class="text-orange-600">₹0</span></div>
                </div>
                <button id="placeBtn" disabled
                        class="mt-5 w-full rounded-xl bg-orange-600 py-3 font-bold text-white transition hover:bg-orange-700 disabled:cursor-not-allowed disabled:bg-gray-300">
                    Place order
                </button>
                <a href="../Menu/menuafterlogin.html" class="mt-3 block text-center text-sm font-semibold text-gray-500 hover:text-orange-600">← Add more items</a>
            </div>
        </aside>
    </div>
</main>

<script>
const UID  = <?= json_encode((string)$uid) ?>;
const CSRF = <?= json_encode($_SESSION['csrf']) ?>;
const KEY  = 'ff2_cart_' + UID;                       // same key foodflow.js uses
try { localStorage.setItem('ff_user', UID); } catch (e) {}

const $ = id => document.getElementById(id);
const rupee = n => '₹' + Number(n).toLocaleString('en-IN');
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

const getCart = () => { try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; } };
const saveCart = c => localStorage.setItem(KEY, JSON.stringify(c));
const cartArray = () => Object.values(getCart()).map(i => ({ id: i.id, name: i.name, qty: i.qty }));

function showError(msg) { const b = $('error'); b.textContent = msg || ''; b.classList.toggle('hidden', !msg); }

async function api(data) {
    const res = await fetch('order_api.php', { method: 'POST', body: new URLSearchParams({ ...data, csrf: CSRF }) });
    return res.json();
}

let canOrder = false;
async function refresh() {
    const cart = cartArray();
    if (!cart.length) {
        $('lines').innerHTML = '<p class="py-6 text-center text-gray-500">Your cart is empty. <a class="font-bold text-orange-600" href="../Menu/menuafterlogin.html">Browse the menu</a></p>';
        $('subtotal').textContent = $('total').textContent = rupee(0);
        $('placeBtn').disabled = true;
        return;
    }
    const q = await api({ action: 'quote', cart: JSON.stringify(cart) });
    if (!q.ok) { showError(q.error); return; }

    let html = '';
    q.lines.forEach(l => {
        html += '<div class="flex items-center gap-3 py-3">' +
            '<span class="h-3 w-3 shrink-0 rounded-sm border-2 ' + (l.veg ? 'border-green-600' : 'border-red-600') + '"></span>' +
            '<div class="min-w-0 flex-1"><p class="truncate font-bold">' + esc(l.name) + '</p><p class="text-sm text-gray-500">' + rupee(l.price) + ' each</p></div>' +
            '<div class="flex items-center gap-2">' +
              '<button data-dec="' + esc(l.cid) + '" class="h-8 w-8 rounded-full bg-gray-100 font-bold hover:bg-orange-100">−</button>' +
              '<span class="w-6 text-center font-bold">' + l.qty + '</span>' +
              '<button data-inc="' + esc(l.cid) + '" class="h-8 w-8 rounded-full bg-gray-100 font-bold hover:bg-orange-100">+</button>' +
            '</div>' +
            '<span class="w-16 text-right font-extrabold">' + rupee(l.line_total) + '</span></div>';
    });
    q.bad.forEach(b => {
        html += '<div class="flex items-center justify-between gap-3 py-3 text-sm font-semibold text-red-600">' +
            '<span>' + esc(b.msg) + '</span><button data-remove="' + esc(b.id) + '" class="rounded-lg bg-red-50 px-3 py-1 hover:bg-red-100">Remove</button></div>';
    });
    $('lines').innerHTML = html;
    $('subtotal').textContent = $('total').textContent = rupee(q.subtotal);
    canOrder = q.lines.length > 0 && q.bad.length === 0;
    $('placeBtn').disabled = !canOrder;
}

$('lines').addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    const cart = getCart();
    const id = b.dataset.inc || b.dataset.dec || b.dataset.remove;
    if (!cart[id]) return;
    if (b.dataset.inc) cart[id].qty = Math.min(20, cart[id].qty + 1);
    else if (b.dataset.dec) { cart[id].qty -= 1; if (cart[id].qty <= 0) delete cart[id]; }
    else delete cart[id];
    saveCart(cart);
    refresh();
});

$('placeBtn').addEventListener('click', async () => {
    showError('');
    const phone = $('phone').value.trim(), address = $('address').value.trim();
    if (!/^[0-9+\-\s]{10,15}$/.test(phone)) return showError('Enter a valid phone number.');
    if (address.length < 10) return showError('Enter your full delivery address.');

    const btn = $('placeBtn');
    btn.disabled = true; btn.textContent = 'Placing order…';
    try {
        const out = await api({
            action: 'place', cart: JSON.stringify(cartArray()), phone, address,
            note: $('note').value.trim(), payment: document.querySelector('input[name=payment]:checked').value
        });
        if (!out.ok) throw new Error(out.error || 'Could not place the order.');
        saveCart({});                                   // empty the cart
        location.href = 'track_order.php?id=' + out.order_id + '&placed=1';
    } catch (err) {
        showError(err.message);
        btn.textContent = 'Place order';
        refresh();
    }
});

refresh();
</script>
</body>
</html>
