document.addEventListener('DOMContentLoaded', function () {
    var viewSiteLinks = document.querySelectorAll('.sidebar-nav a.nav-item[href="../home.php"]');
    if (!viewSiteLinks.length) {
        return;
    }

    var modal = document.createElement('div');
    modal.className = 'admin-view-site-modal';
    modal.setAttribute('hidden', 'hidden');
    modal.innerHTML = [
        '<div class="admin-view-site-modal__overlay" data-close="true"></div>',
        '<div class="admin-view-site-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="adminViewSiteTitle" aria-describedby="adminViewSiteDescription">',
        '  <div class="admin-view-site-modal__icon" aria-hidden="true">',
        '    <i class="fa-solid fa-arrow-up-right-from-square"></i>',
        '  </div>',
        '  <h2 id="adminViewSiteTitle">Leave Admin Panel?</h2>',
        '  <p id="adminViewSiteDescription">You are about to open the main site view. Choose Proceed to continue or Return to stay in the admin area.</p>',
        '  <div class="admin-view-site-modal__actions">',
        '    <button type="button" class="admin-view-site-modal__button admin-view-site-modal__button--secondary" data-action="cancel">Return</button>',
        '    <button type="button" class="admin-view-site-modal__button admin-view-site-modal__button--primary" data-action="proceed">Proceed</button>',
        '  </div>',
        '</div>'
    ].join('');

    document.body.appendChild(modal);

    var pendingHref = null;
    var proceedButton = modal.querySelector('[data-action="proceed"]');

    function openModal(href) {
        pendingHref = href;
        modal.removeAttribute('hidden');
        document.body.classList.add('admin-view-site-modal-open');
        proceedButton.focus();
    }

    function closeModal() {
        pendingHref = null;
        modal.setAttribute('hidden', 'hidden');
        document.body.classList.remove('admin-view-site-modal-open');
    }

    viewSiteLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            openModal(link.href);
        });
    });

    modal.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.dataset.close === 'true' || target.dataset.action === 'cancel') {
            closeModal();
            return;
        }

        if (target.dataset.action === 'proceed' && pendingHref) {
            window.location.href = pendingHref;
        }
    });

    document.addEventListener('keydown', function (event) {
        if (modal.hasAttribute('hidden')) {
            return;
        }
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    if (!document.getElementById('admin-view-site-modal-style')) {
        var style = document.createElement('style');
        style.id = 'admin-view-site-modal-style';
        style.textContent = [
            '.admin-view-site-modal[hidden] { display: none; }',
            '.admin-view-site-modal { position: fixed; inset: 0; z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 20px; }',
            '.admin-view-site-modal__overlay { position: absolute; inset: 0; background: rgba(2, 6, 23, 0.68); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); }',
            '.admin-view-site-modal__dialog { position: relative; z-index: 1; width: min(100%, 420px); padding: 28px; border-radius: 24px; background: linear-gradient(160deg, rgba(17, 24, 39, 0.96), rgba(30, 41, 59, 0.92)); border: 1px solid rgba(148, 163, 184, 0.18); box-shadow: 0 24px 60px rgba(0, 0, 0, 0.32); color: #E2E8F0; text-align: left; }',
            '.admin-view-site-modal__icon { width: 52px; height: 52px; margin-bottom: 18px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; background: rgba(212, 165, 116, 0.16); color: #F3D9B4; font-size: 20px; }',
            '.admin-view-site-modal__dialog h2 { margin: 0 0 10px; font-size: 24px; color: #FFFFFF; }',
            '.admin-view-site-modal__dialog p { margin: 0; font-size: 14px; line-height: 1.65; color: #CBD5E1; }',
            '.admin-view-site-modal__actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; }',
            '.admin-view-site-modal__button { min-height: 42px; padding: 10px 18px; border-radius: 12px; border: 1px solid transparent; font-size: 14px; font-weight: 700; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, color 0.2s ease, border-color 0.2s ease; }',
            '.admin-view-site-modal__button:hover { transform: translateY(-1px); }',
            '.admin-view-site-modal__button--secondary { background: rgba(255, 255, 255, 0.04); border-color: rgba(148, 163, 184, 0.2); color: #E2E8F0; }',
            '.admin-view-site-modal__button--secondary:hover { background: rgba(255, 255, 255, 0.08); }',
            '.admin-view-site-modal__button--primary { background: linear-gradient(135deg, #D4A574, #B8935F); color: #0F172A; box-shadow: 0 10px 24px rgba(212, 165, 116, 0.2); }',
            '.admin-view-site-modal__button--primary:hover { box-shadow: 0 14px 28px rgba(212, 165, 116, 0.24); }',
            'body.admin-view-site-modal-open { overflow: hidden; }',
            'body.light-mode .admin-view-site-modal__dialog { background: #FFFFFF; border-color: rgba(15, 23, 42, 0.08); box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16); color: #334155; }',
            'body.light-mode .admin-view-site-modal__icon { background: rgba(184, 147, 95, 0.12); color: #8B6F47; }',
            'body.light-mode .admin-view-site-modal__dialog h2 { color: #0F172A; }',
            'body.light-mode .admin-view-site-modal__dialog p { color: #475569; }',
            'body.light-mode .admin-view-site-modal__button--secondary { background: #FFFFFF; border-color: rgba(15, 23, 42, 0.1); color: #334155; }',
            'body.light-mode .admin-view-site-modal__button--secondary:hover { background: #F8FAFC; }'
        ].join('');
        document.head.appendChild(style);
    }
});