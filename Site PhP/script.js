// ====== MENU HAMBÚRGUER ======
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('menuToggle');
    const navMenu = document.getElementById('navMenu');

    if (!toggleBtn || !navMenu) return;       // segurança

    toggleBtn.addEventListener('click', e => {
        e.stopPropagation();                  // evita propagação
        navMenu.classList.toggle('active');   // mostra/oculta menu
        toggleBtn.classList.toggle('open');   // gira botão

        // troca ícone ▸ X
        const icon = toggleBtn.querySelector('i');
        icon.classList.toggle('fa-bars');
        icon.classList.toggle('fa-times');
    });

    // fecha ao clicar fora
    document.addEventListener('click', e => {
        if (navMenu.classList.contains('active')
            && !navMenu.contains(e.target)
            && !toggleBtn.contains(e.target)) {
                navMenu.classList.remove('active');
                toggleBtn.classList.remove('open');
                const icon = toggleBtn.querySelector('i');
                icon.classList.add('fa-bars');
                icon.classList.remove('fa-times');
        }
    });

    // fecha após escolher um link
    navMenu.querySelectorAll('a').forEach(link =>
        link.addEventListener('click', () => {
            navMenu.classList.remove('active');
            toggleBtn.classList.remove('open');
            const icon = toggleBtn.querySelector('i');
            icon.classList.add('fa-bars');
            icon.classList.remove('fa-times');
        })
    );

    // ====== BUSCA AJAX USUÁRIOS ======
    const inp = document.getElementById('userSearchInput');
    const results = document.getElementById('userSearchResults');
    const roleBtns = document.querySelectorAll('.role-filter button');
    let roleSel = '';

    if (inp && results && roleBtns.length > 0) {
        console.log('✅ Elementos de busca encontrados');
        
        // Troca cargo
        roleBtns.forEach(btn => btn.addEventListener('click', () => {
            roleBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            roleSel = btn.dataset.role;
            console.log('Filtro selecionado:', roleSel);
            fetchUsers(inp.value.trim());
        }));

        // Pesquisa conforme digita
        inp.addEventListener('input', e => {
            const v = e.target.value.trim();
            console.log('Digitando:', v);
            if (v.length < 2 && v.length !== 0) {
                results.style.display = 'none';
                return;
            }
            fetchUsers(v);
        });

        // Carrega resultados
        function fetchUsers(query = '') {
            console.log('Fazendo fetch:', query, roleSel);
            fetch(`search_users.php?q=${encodeURIComponent(query)}&role=${encodeURIComponent(roleSel)}`)
                .then(r => {
                    console.log('Status:', r.status);
                    return r.json();
                })
                .then(data => {
                    console.log('Dados recebidos:', data);
                    renderList(data);
                })
                .catch(err => {
                    console.error('Erro:', err);
                    results.innerHTML = '<div class="nores">Erro na busca</div>';
                    results.style.display = 'block';
                });
        }

        function renderList(users) {
            if (!users.length) {
                results.innerHTML = '<div class="nores">Nenhum usuário encontrado</div>';
            } else {
                results.innerHTML = users.map(u => `
                    <a href="?action=users&search=${encodeURIComponent(u.username)}" class="res-item">
                        <strong>${u.username}</strong> 
                        <small>${u.email}</small> 
                        <span class="role role-${u.role}">${u.role}</span>
                    </a>`).join('');
            }
            results.style.display = 'block';
        }

        // Fecha ao clicar fora
        document.addEventListener('click', e => {
            if (!e.target.closest('.search-box')) {
                results.style.display = 'none';
            }
        });
    } else {
        console.log('❌ Elementos de busca não encontrados:', {
            inp: !!inp, 
            results: !!results, 
            buttons: roleBtns.length
        });
    }
});

// ====== USER DROPDOWN ======
function toggleUserDropdown() {
    // Find all dropdowns and toggles
    const dropdowns = document.querySelectorAll('.user-dropdown-menu');
    const toggles = document.querySelectorAll('.user-dropdown-toggle');
    
    // Close all dropdowns first
    dropdowns.forEach(dropdown => {
        dropdown.classList.remove('show');
    });
    toggles.forEach(toggle => {
        toggle.setAttribute('aria-expanded', 'false');
    });
    
    // Find the clicked toggle and its dropdown
    const clickedToggle = event.target.closest('.user-dropdown-toggle');
    if (!clickedToggle) return;
    
    const dropdown = clickedToggle.nextElementSibling;
    if (!dropdown) return;
    
    // Toggle the clicked dropdown
    dropdown.classList.add('show');
    clickedToggle.setAttribute('aria-expanded', 'true');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-dropdown')) {
        const dropdowns = document.querySelectorAll('.user-dropdown-menu');
        const toggles = document.querySelectorAll('.user-dropdown-toggle');
        
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
        toggles.forEach(toggle => {
            toggle.setAttribute('aria-expanded', 'false');
        });
    }
});

// ====== THEME TOGGLE ======
function applyTheme(theme) {
    const root = document.documentElement;
    
    // Remove existing theme classes
    root.classList.remove('light-mode', 'dark-mode', 'light', 'dark');
    
    // Add new theme class
    if (theme === 'light') {
        root.classList.add('light-mode');
        root.classList.remove('dark');
    } else {
        root.classList.add('dark-mode', 'dark');
    }
    
    localStorage.setItem('theme', theme);

    // Update all theme icons
    const icons = document.querySelectorAll('.theme-icon');
    icons.forEach(icon => {
        icon.className = theme === 'light' ? 'fas fa-sun theme-icon' : 'fas fa-moon theme-icon';
    });
}

function toggleTheme() {
    const root = document.documentElement;
    const newTheme = (root.classList.contains('light-mode') || root.classList.contains('light')) ? 'dark' : 'light';
    applyTheme(newTheme);
}

// Aplica tema salvo ou preferência do SO
window.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('theme');
    const prefers = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    applyTheme(saved || prefers);
});

// ====== PREVIEW DE IMAGENS ======
function preview(inpId, outId, multi = false) {
    const inp = document.getElementById(inpId);
    const out = document.getElementById(outId);
    if (!inp || !out) return;
    
    inp.addEventListener('change', e => {
        out.innerHTML = '';
        [...e.target.files].slice(0, 5).forEach((f, i) => {
            if (!f.type.startsWith('image/')) return;
            const r = new FileReader();
            r.onload = ev => {
                out.insertAdjacentHTML('beforeend',
                    `<img src='${ev.target.result}' style='width:100px;height:60px;object-fit:cover;border-radius:4px;border:2px solid var(--success);margin:2px'>`
                );
            };
            r.readAsDataURL(f);
        });
    });
}

// Inicializar preview quando DOM carregar
document.addEventListener('DOMContentLoaded', () => {
    preview('coverInput', 'coverPreview');
    preview('screenshotsInput', 'screenshotsPreview', true);
    preview('editCoverInput', 'editCoverPreview');
    preview('editScreenshotsInput', 'editScreenshotsPreview', true);
});
