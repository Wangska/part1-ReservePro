<?php
// expects $user from session context
if (!isset($user) || empty($user['id'])) return;

$rp_notify_api_prefix = (dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')) === '/') ? '' : '../';
?>
<div class="rp-notify" id="rpNotify">
    <div class="rp-notify-head">
        <div style="display:flex; align-items:center; gap:10px;">
            <span class="rp-notify-ico"><i class="fa-solid fa-bell" aria-hidden="true"></i></span>
            <div>
                <div class="rp-notify-title">Notifications</div>
                <div class="rp-notify-sub"><span id="rpNotifyUnread">0</span> unread</div>
            </div>
        </div>
        <button type="button" class="rp-notify-btn" id="rpNotifyMarkAll">Mark all read</button>
    </div>
    <div class="rp-notify-list" id="rpNotifyList">
        <div class="rp-notify-empty">Loading…</div>
    </div>
</div>

<style>
    .rp-notify{margin-bottom:16px;border-radius:18px;padding:14px 14px;background:rgba(17,24,39,0.78);border:1px solid rgba(148,163,184,0.16)}
    .rp-notify-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px}
    .rp-notify-ico{width:38px;height:38px;border-radius:14px;display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.10);color:#FDE68A}
    .rp-notify-title{font-weight:900;color:#fff;font-size:14px;letter-spacing:-0.01em}
    .rp-notify-sub{color:#94A3B8;font-size:12px;font-weight:800}
    .rp-notify-btn{border-radius:12px;padding:10px 12px;border:1px solid rgba(255,255,255,0.14);background:rgba(255,255,255,0.06);color:#E2E8F0;font-weight:900;font-size:12px;cursor:pointer}
    .rp-notify-list{display:flex;flex-direction:column;gap:8px}
    .rp-notify-item{display:flex;gap:10px;align-items:flex-start;padding:10px 10px;border-radius:14px;border:1px solid rgba(148,163,184,0.14);background:rgba(255,255,255,0.04)}
    .rp-notify-item.unread{border-color:rgba(212,165,116,0.35);background:rgba(212,165,116,0.07)}
    .rp-notify-item strong{display:block;color:#F1F5F9;font-weight:900;font-size:13px;margin-bottom:2px}
    .rp-notify-item small{display:block;color:#94A3B8;font-weight:700;font-size:12px;line-height:1.45}
    .rp-notify-actions{display:flex;gap:10px;align-items:center;margin-left:auto;flex-shrink:0}
    .rp-notify-link{color:#FDE68A;text-decoration:none;font-weight:900;font-size:12px}
    .rp-notify-mark{border:0;background:transparent;color:#CBD5E1;font-weight:900;font-size:12px;cursor:pointer}
    .rp-notify-empty{padding:10px 10px;color:#94A3B8;font-weight:800;font-size:12px}
    body.light-mode .rp-notify{background:#fff;border-color:#E2E8F0}
    body.light-mode .rp-notify-title{color:#0f172a}
    body.light-mode .rp-notify-sub{color:#475569}
    body.light-mode .rp-notify-item{background:#F8FAFC;border-color:#E2E8F0}
    body.light-mode .rp-notify-item.unread{background:rgba(212,165,116,0.12);border-color:rgba(212,165,116,0.45)}
    body.light-mode .rp-notify-item strong{color:#0f172a}
    body.light-mode .rp-notify-item small{color:#475569}
    body.light-mode .rp-notify-btn{background:#fff;color:#0f172a;border-color:#E2E8F0}
</style>

<script>
(function(){
    var unreadEl = document.getElementById('rpNotifyUnread');
    var listEl = document.getElementById('rpNotifyList');
    var markAllBtn = document.getElementById('rpNotifyMarkAll');
    if (!unreadEl || !listEl) return;

    function esc(s){ var d=document.createElement('div'); d.textContent = String(s||''); return d.innerHTML; }

    function render(items){
        if (!items || !items.length){
            listEl.innerHTML = '<div class="rp-notify-empty">No notifications yet.</div>';
            return;
        }
        listEl.innerHTML = items.map(function(n){
            var unread = String(n.is_read) === '0';
            var link = n.link ? String(n.link) : '';
            var body = n.body ? String(n.body) : '';
            var right = '<div class="rp-notify-actions">' +
                (link ? '<a class="rp-notify-link" href="' + esc(link) + '">Open</a>' : '') +
                (unread ? '<button class="rp-notify-mark" data-mark="' + esc(n.id) + '">Mark read</button>' : '') +
                '</div>';
            return '<div class="rp-notify-item ' + (unread ? 'unread' : '') + '">' +
                '<div><strong>' + esc(n.title) + '</strong>' +
                (body ? '<small>' + esc(body) + '</small>' : '') +
                '</div>' + right + '</div>';
        }).join('');
    }

    function load(){
        fetch('<?php echo $rp_notify_api_prefix; ?>api/notifications-list.php?limit=8', { credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(data){
                if (!data || !data.ok) return;
                unreadEl.textContent = String(data.unread || 0);
                render(data.items || []);
            })
            .catch(function(){ listEl.innerHTML = '<div class="rp-notify-empty">Failed to load notifications.</div>'; });
    }

    function mark(id){
        var fd = new FormData();
        if (id) fd.append('id', String(id));
        fetch('<?php echo $rp_notify_api_prefix; ?>api/notifications-mark-read.php', { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(data){ if (data && data.ok) load(); })
            .catch(function(){});
    }

    listEl.addEventListener('click', function(e){
        var btn = e.target && e.target.closest && e.target.closest('[data-mark]');
        if (!btn) return;
        var id = parseInt(btn.getAttribute('data-mark'), 10);
        if (!id) return;
        mark(id);
    });
    if (markAllBtn){
        markAllBtn.addEventListener('click', function(){ mark(0); });
    }

    load();
})();
</script>

