(function(){
    var btn = document.getElementById('admNotifBtn');
    var dropdown = document.getElementById('admNotifDropdown');
    var badge = document.getElementById('admNotifBadge');
    var list = document.getElementById('admNotifList');
    var markAllBtn = document.getElementById('admNotifMarkAll');
    if (!btn || !dropdown) return;

    function esc(s){ var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

    function render(items){
        if (!items || !items.length){
            list.innerHTML = '<div class="adm-notif-empty">No notifications yet.</div>';
            return;
        }
        list.innerHTML = items.map(function(n){
            var unread = String(n.is_read)==='0';
            var link = n.link ? String(n.link) : '';
            var body = n.body ? String(n.body) : '';
            var attrs = ' style="cursor:pointer"';
            if (link) attrs += ' data-link="'+esc(link)+'"';
            if (unread) attrs += ' data-mark="'+esc(n.id)+'"';
            return '<div class="adm-notif-item'+(unread?' unread':'')+'"'+attrs+'>'+ 
                '<div class="adm-notif-item-body"><strong>'+esc(n.title)+'</strong>'+ (body?'<small>'+esc(n.body)+'</small>':'')+'</div>'+ '<div class="adm-notif-item-actions"></div></div>';
        }).join('');
    }

    function load(){
        fetch('../api/notifications-list.php?limit=8', {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(data){
                if (!data||!data.ok) return;
                var unread = parseInt(data.unread||0, 10);
                var items = data.items||[];
                if (items.length > 0) {
                    if (unread > 0) {
                        badge.textContent = unread > 99 ? '99+' : String(unread);
                        badge.hidden = false;
                    } else {
                        badge.hidden = true;
                    }
                } else {
                    badge.hidden = true;
                }
                render(items);
            })
            .catch(function(){ list.innerHTML='<div class="adm-notif-empty">Failed to load.</div>'; badge.hidden = true; });
    }

    function mark(id){
        var fd = new FormData();
        if (id) fd.append('id', String(id));
        fetch('../api/notifications-mark-read.php',{method:'POST',body:fd,credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(data){ if(data&&data.ok) load(); })
            .catch(function(){});
    }

    list.addEventListener('click', function(e){
        var item = e.target && e.target.closest && e.target.closest('.adm-notif-item');
        if (!item) return;
        
        var hasMarkAttr = item.hasAttribute('data-mark');
        var hasLinkAttr = item.hasAttribute('data-link');
        
        if (hasMarkAttr) {
            var id = parseInt(item.getAttribute('data-mark'), 10);
            if (id) {
                var url = hasLinkAttr ? item.getAttribute('data-link') : null;
                var fd = new FormData();
                fd.append('id', String(id));
                fetch('../api/notifications-mark-read.php',{method:'POST',body:fd,credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(data){ 
                        if(data&&data.ok) {
                            if (url) window.location.href = url;
                            else load();
                        }
                    })
                    .catch(function(){});
                return;
            }
        }
        
        if (hasLinkAttr) {
            var url = item.getAttribute('data-link');
            if (url) window.location.href = url;
        }
    });

    markAllBtn.addEventListener('click', function(){ mark(0); });

    btn.addEventListener('click', function(e){
        e.stopPropagation();
        var open = !dropdown.hidden;
        dropdown.hidden = open;
        btn.setAttribute('aria-expanded', String(!open));
        if (!open) load();
    });

    document.addEventListener('click', function(e){
        if (!document.getElementById('admNotifWrap').contains(e.target)){
            dropdown.hidden = true;
            btn.setAttribute('aria-expanded','false');
        }
    });

    load();
})();
