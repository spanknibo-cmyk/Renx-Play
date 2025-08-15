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

    // ====== DROPDOWN DO USUÁRIO ======
    const dropdownBtn = document.querySelector('.user-dropdown > button');
    const dropdown = document.getElementById('userDropdown');
    if (dropdownBtn && dropdown) {
        dropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.user-dropdown')) {
                dropdown.classList.remove('show');
            }
        });
    }

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

// ====== THEME TOGGLE ======
function applyTheme(theme) {
    const root = document.documentElement;

    // Remove dark before applying
    root.classList.remove('dark');

    // Apply selected theme class
    if (theme === 'dark') {
        root.classList.add('dark');
    }

    localStorage.setItem('theme', theme);

    // Update icon
    const icon = document.querySelector('.theme-toggle i');
    if (icon) {
        icon.className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
    }
}

function toggleTheme() {
    const current = localStorage.getItem('theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    applyTheme(next);
}

// Apply saved theme (default to light)
window.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
    applyTheme(saved);
});

// ====== DROPDOWN (user menu) ======
function toggleDropdown() {
    const dropdown = document.getElementById('userDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

// Close dropdown on outside click
window.addEventListener('click', (e) => {
    const isInside = e.target.closest && e.target.closest('.user-dropdown');
    if (!isInside) {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown) dropdown.classList.remove('show');
    }
});

// ====== PREVIEW DE IMAGENS ======
function preview(inpId, outId, multi = false) {
    const inp = document.getElementById(inpId);
    const out = document.getElementById(outId);
    if (!inp || !out) return;
    
    // Allow removing before submit
    const removedIndices = new Set();
    if (inp.form) {
        let hidden = inp.form.querySelector('input[name="exclude_indices"]');
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'exclude_indices';
            inp.form.appendChild(hidden);
        }
        const updateHidden = () => {
            hidden.value = Array.from(removedIndices).sort((a,b)=>a-b).join(',');
        };

        inp.addEventListener('change', e => {
            out.innerHTML = '';
            removedIndices.clear();
            const files = Array.from(e.target.files).slice(0, 30);
            files.forEach((f, i) => {
                if (!f.type.startsWith('image/')) return;
                const r = new FileReader();
                r.onload = ev => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'screenshot-thumb';
                    wrapper.innerHTML = `
                        <img src='${ev.target.result}' alt='' />
                        <button type='button' class='remove-thumb' aria-label='Remover'>&times;</button>
                    `;
                    wrapper.querySelector('.remove-thumb').addEventListener('click', () => {
                        wrapper.classList.add('removed');
                        removedIndices.add(i);
                        updateHidden();
                    });
                    out.appendChild(wrapper);
                };
                r.readAsDataURL(f);
            });
            updateHidden();
        });
    }
}

// Inicializar preview quando DOM carregar
document.addEventListener('DOMContentLoaded', () => {
    preview('coverInput', 'coverPreview');
    preview('screenshotsInput', 'screenshotsPreview', true);
    preview('editCoverInput', 'editCoverPreview');
    preview('editScreenshotsInput', 'editScreenshotsPreview', true);
});

// ====== LIGHTBOX FOR SCREENSHOTS ======
let currentGallery = [];
let currentIndex = 0;

function openModal(imgEl, index) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    if (!modal || !modalImg) return;

    const gallery = imgEl.closest('.screenshot-gallery');
    if (gallery) {
        currentGallery = Array.from(gallery.querySelectorAll('img.screenshot')).map(i => ({
            full: i.getAttribute('data-full') || i.src,
            fallback: i.getAttribute('data-fallback') || ''
        }));
    } else {
        currentGallery = [{
            full: imgEl.getAttribute('data-full') || imgEl.src,
            fallback: imgEl.getAttribute('data-fallback') || ''
        }];
    }
    currentIndex = index || 0;

    const current = currentGallery[currentIndex];
    const isAvif = /\.avif($|\?)/i.test(current.full);
    modalImg.src = isAvif && current.fallback ? current.fallback : current.full;
    modal.style.display = 'block';
}

function closeModal() {
    const modal = document.getElementById('imageModal');
    if (modal) modal.style.display = 'none';
}

function nextImage() {
    if (!currentGallery.length) return;
    currentIndex = (currentIndex + 1) % currentGallery.length;
    const modalImg = document.getElementById('modalImage');
    if (modalImg) {
        const item = currentGallery[currentIndex];
        const isAvif = /\.avif($|\?)/i.test(item.full);
        modalImg.src = isAvif && item.fallback ? item.fallback : item.full;
    }
}

function previousImage() {
    if (!currentGallery.length) return;
    currentIndex = (currentIndex - 1 + currentGallery.length) % currentGallery.length;
    const modalImg = document.getElementById('modalImage');
    if (modalImg) {
        const item = currentGallery[currentIndex];
        const isAvif = /\.avif($|\?)/i.test(item.full);
        modalImg.src = isAvif && item.fallback ? item.fallback : item.full;
    }
}
