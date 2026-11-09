        </main>
    </div>
</div>

<!-- JAVASCRIPT GLOBAL UNTUK RESPONSIF, INTERAKTIVITAS CEPAT, & SIDEBAR -->
<script>
// Toggle Drawer Sidebar Mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar) sidebar.classList.toggle('open');
    if (backdrop) backdrop.classList.toggle('active');
}

// Toggle Section Accordion
function toggleSection(sectionId) {
    const sec = document.getElementById(sectionId);
    if (sec) {
        sec.classList.toggle('collapsed');
        sec.classList.toggle('active-section');
    }
}

// Toggle Notifikasi Dropdown
function toggleNotificationDropdown() {
    const dd = document.getElementById('notifDropdown');
    const userDd = document.getElementById('userDropdown');
    if (userDd) userDd.classList.remove('show');
    if (dd) dd.classList.toggle('show');
}

// Toggle User Role Switcher Dropdown
function toggleUserDropdown() {
    const dd = document.getElementById('userDropdown');
    const notifDd = document.getElementById('notifDropdown');
    if (notifDd) notifDd.classList.remove('show');
    if (dd) dd.classList.toggle('show');
}

// Tutup dropdown jika klik di luar
document.addEventListener('click', function(e) {
    const notifWrapper = document.querySelector('.notification-wrapper');
    const notifDropdown = document.getElementById('notifDropdown');
    if (notifWrapper && notifDropdown && !notifWrapper.contains(e.target)) {
        notifDropdown.classList.remove('show');
    }

    const userWrapper = document.querySelector('.user-profile-wrapper');
    const userDropdown = document.getElementById('userDropdown');
    if (userWrapper && userDropdown && !userWrapper.contains(e.target)) {
        userDropdown.classList.remove('show');
    }
});

// Auto-hide alert boxes setelah 5 detik
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(el => {
        el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        el.style.opacity = '0';
        el.style.transform = 'translateY(-6px)';
        setTimeout(() => el.remove(), 400);
    });
}, 5000);
</script>

</body>
</html>
