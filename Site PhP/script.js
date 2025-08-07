// ====== THEME SYSTEM - Replicando exatamente do App.tsx ======
let isDark = false;

// Função para aplicar tema - seguindo App.tsx
function applyTheme(theme) {
    const root = document.documentElement;
    const themeIcons = document.querySelectorAll('.theme-icon');
    const themeTexts = document.querySelectorAll('.theme-text');
    
    if (theme === 'dark') {
        root.classList.add('dark');
        root.classList.remove('light');
        isDark = true;
        
        // Atualizar ícones e textos
        themeIcons.forEach(icon => {
            icon.className = 'theme-icon fas fa-sun h-4 w-4 mr-2';
        });
        themeTexts.forEach(text => {
            text.textContent = 'Tema Claro';
        });
    } else {
        root.classList.remove('dark');
        root.classList.add('light');
        isDark = false;
        
        // Atualizar ícones e textos
        themeIcons.forEach(icon => {
            icon.className = 'theme-icon fas fa-moon h-4 w-4 mr-2';
        });
        themeTexts.forEach(text => {
            text.textContent = 'Tema Escuro';
        });
    }
    
    localStorage.setItem('theme', theme);
}

// Toggle theme - replicando App.tsx
function toggleTheme() {
    const newTheme = isDark ? 'light' : 'dark';
    applyTheme(newTheme);
}

// Inicializar tema - seguindo App.tsx
function initializeTheme() {
    const savedTheme = localStorage.getItem('theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    const shouldUseDark = savedTheme === 'dark' || (!savedTheme && systemPrefersDark);
    
    applyTheme(shouldUseDark ? 'dark' : 'light');
}

// ====== DROPDOWN SYSTEM - Replicando shadcn/ui ======
function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdownMenu');
    const trigger = document.querySelector('.dropdown-trigger');
    
    if (!dropdown || !trigger) return;
    
    const isOpen = dropdown.classList.contains('show');
    
    if (isOpen) {
        dropdown.classList.remove('show');
        trigger.setAttribute('aria-expanded', 'false');
    } else {
        dropdown.classList.add('show');
        trigger.setAttribute('aria-expanded', 'true');
    }
}

// Fechar dropdown ao clicar fora
function handleClickOutside(event) {
    const dropdown = document.getElementById('userDropdownMenu');
    const wrapper = event.target.closest('.dropdown-menu-wrapper');
    
    if (!wrapper && dropdown) {
        dropdown.classList.remove('show');
        const trigger = document.querySelector('.dropdown-trigger');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
    }
}

// ====== SEARCH FUNCTIONALITY - Melhorado ======
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    
    if (!searchInput || !searchResults) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Hide results if query is too short
        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }
        
        // Debounce search
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-wrapper')) {
            searchResults.style.display = 'none';
        }
    });
}

function performSearch(query) {
    const searchResults = document.getElementById('searchResults');
    
    fetch(`search_games.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(games => {
            if (games.length === 0) {
                searchResults.innerHTML = `
                    <div class="p-4 text-center text-muted-foreground">
                        <i class="fas fa-search h-4 w-4 mb-2"></i>
                        <p>Nenhum jogo encontrado</p>
                    </div>
                `;
            } else {
                searchResults.innerHTML = games.map(game => `
                    <a href="?game=${encodeURIComponent(game.slug)}" 
                       class="block p-3 hover:bg-accent transition-colors border-b border-border last:border-b-0">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-8 bg-muted rounded overflow-hidden flex-shrink-0">
                                <img src="uploads/covers/${game.cover_image}" 
                                     alt="${game.title}" 
                                     class="w-full h-full object-cover"
                                     loading="lazy">
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-sm truncate">${game.title}</h4>
                                <p class="text-xs text-muted-foreground truncate">
                                    ${game.engine || 'REN\'PY'} • ${game.downloads_count || 0} downloads
                                </p>
                            </div>
                        </div>
                    </a>
                `).join('');
            }
            searchResults.style.display = 'block';
        })
        .catch(error => {
            console.error('Erro na busca:', error);
            searchResults.innerHTML = `
                <div class="p-4 text-center text-destructive">
                    <i class="fas fa-exclamation-triangle h-4 w-4 mb-2"></i>
                    <p>Erro na busca. Tente novamente.</p>
                </div>
            `;
            searchResults.style.display = 'block';
        });
}

// ====== CARD ANIMATIONS - Melhorias Criativas ======
function initializeCardAnimations() {
    const cards = document.querySelectorAll('.game-card');
    
    cards.forEach(card => {
        // Hover effect com delay staggered
        card.addEventListener('mouseenter', function() {
            const img = this.querySelector('.game-card-image img');
            if (img) {
                img.style.transform = 'scale(1.05)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            const img = this.querySelector('.game-card-image img');
            if (img) {
                img.style.transform = 'scale(1)';
            }
        });
        
        // Loading state para imagens
        const img = card.querySelector('img');
        if (img) {
            if (!img.complete) {
                img.addEventListener('load', function() {
                    this.style.opacity = '1';
                });
                img.style.opacity = '0';
                img.style.transition = 'opacity 0.3s ease';
            }
        }
    });
}

// ====== INTERSECTION OBSERVER - Lazy Loading Melhorado ======
function initializeLazyLoading() {
    const images = document.querySelectorAll('img[loading="lazy"]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    
                    // Add loading state
                    img.style.opacity = '0';
                    img.style.transition = 'opacity 0.3s ease';
                    
                    // Load image
                    const loadImage = () => {
                        img.style.opacity = '1';
                        observer.unobserve(img);
                    };
                    
                    if (img.complete) {
                        loadImage();
                    } else {
                        img.addEventListener('load', loadImage);
                        img.addEventListener('error', () => {
                            img.style.opacity = '0.5';
                            observer.unobserve(img);
                        });
                    }
                }
            });
        }, {
            rootMargin: '50px 0px',
            threshold: 0.1
        });
        
        images.forEach(img => imageObserver.observe(img));
    }
}

// ====== KEYBOARD NAVIGATION ======
function initializeKeyboardNavigation() {
    document.addEventListener('keydown', function(e) {
        // ESC para fechar dropdown
        if (e.key === 'Escape') {
            const dropdown = document.getElementById('userDropdownMenu');
            if (dropdown && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                const trigger = document.querySelector('.dropdown-trigger');
                if (trigger) {
                    trigger.setAttribute('aria-expanded', 'false');
                    trigger.focus();
                }
            }
        }
        
        // / para focar no search
        if (e.key === '/' && !e.target.matches('input, textarea')) {
            e.preventDefault();
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // Ctrl/Cmd + K para toggle theme
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            toggleTheme();
        }
    });
}

// ====== LOADING STATES ======
function showLoading(element) {
    if (element) {
        element.classList.add('animate-pulse');
        element.style.opacity = '0.6';
    }
}

function hideLoading(element) {
    if (element) {
        element.classList.remove('animate-pulse');
        element.style.opacity = '1';
    }
}

// ====== SMOOTH SCROLL ======
function initializeSmoothScroll() {
    // Smooth scroll para links internos
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// ====== PERFORMANCE MONITORING ======
function initializePerformanceMonitoring() {
    // Monitor de performance para desenvolvimento
    if (window.performance && window.performance.mark) {
        window.performance.mark('app-start');
        
        window.addEventListener('load', () => {
            window.performance.mark('app-loaded');
            window.performance.measure('app-load-time', 'app-start', 'app-loaded');
            
            const measure = window.performance.getEntriesByName('app-load-time')[0];
            if (measure && measure.duration > 3000) {
                console.warn('Slow page load detected:', measure.duration + 'ms');
            }
        });
    }
}

// ====== PARTICLE SYSTEM ======
function createParticleSystem() {
    const particlesContainer = document.createElement('div');
    particlesContainer.className = 'particles-bg';
    document.body.appendChild(particlesContainer);
    
    function createParticle() {
        const particle = document.createElement('div');
        particle.className = 'particle';
        
        // Random size
        const size = Math.random() * 4 + 2;
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';
        
        // Random position
        particle.style.left = Math.random() * 100 + '%';
        
        // Random animation duration
        particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
        particle.style.animationDelay = Math.random() * 10 + 's';
        
        particlesContainer.appendChild(particle);
        
        // Remove particle after animation
        setTimeout(() => {
            if (particle.parentNode) {
                particle.parentNode.removeChild(particle);
            }
        }, 20000);
    }
    
    // Create particles periodically
    setInterval(createParticle, 3000);
}

// ====== ADVANCED CARD INTERACTIONS ======
function initializeAdvancedCardEffects() {
    const cards = document.querySelectorAll('.game-card');
    
    cards.forEach(card => {
        // Add ripple effect
        card.classList.add('ripple-effect');
        
        // Add tilt effect on mouse move
        card.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 10;
            const rotateY = (centerX - x) / 10;
            
            this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.02)`;
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale(1)';
        });
        
        // Add stagger animation
        const index = Array.from(cards).indexOf(card);
        card.style.animationDelay = (index * 0.1) + 's';
        card.classList.add('page-transition');
    });
}

// ====== ADVANCED SEARCH WITH HIGHLIGHTS ======
function initializeAdvancedSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    
    if (!searchInput || !searchResults) return;
    
    let searchTimeout;
    let currentQuery = '';
    
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        currentQuery = query;
        
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }
        
        // Add loading state
        searchResults.innerHTML = `
            <div class="p-4 text-center">
                <div class="skeleton-loader" style="height: 20px; width: 60%; margin: 0 auto 10px;"></div>
                <div class="skeleton-loader" style="height: 16px; width: 80%; margin: 0 auto;"></div>
            </div>
        `;
        searchResults.style.display = 'block';
        
        searchTimeout = setTimeout(() => {
            performAdvancedSearch(query);
        }, 300);
    });
    
    function performAdvancedSearch(query) {
        fetch(`search_games.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(games => {
                if (currentQuery !== query) return; // Ignore outdated requests
                
                if (games.length === 0) {
                    searchResults.innerHTML = `
                        <div class="p-4 text-center text-muted-foreground">
                            <i class="fas fa-search h-4 w-4 mb-2"></i>
                            <p>Nenhum jogo encontrado para "<strong>${query}</strong>"</p>
                        </div>
                    `;
                } else {
                    searchResults.innerHTML = games.map(game => {
                        const highlightedTitle = highlightText(game.title, query);
                        return `
                            <a href="?game=${encodeURIComponent(game.slug)}" 
                               class="block p-3 hover:bg-accent transition-colors border-b border-border last:border-b-0 focus-ring">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-8 bg-muted rounded overflow-hidden flex-shrink-0">
                                        <img src="uploads/covers/${game.cover_image}" 
                                             alt="${game.title}" 
                                             class="w-full h-full object-cover"
                                             loading="lazy">
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-medium text-sm truncate">${highlightedTitle}</h4>
                                        <p class="text-xs text-muted-foreground truncate">
                                            ${game.engine || 'REN\'PY'} • ${game.downloads_count || 0} downloads
                                        </p>
                                    </div>
                                </div>
                            </a>
                        `;
                    }).join('');
                }
                searchResults.style.display = 'block';
            })
            .catch(error => {
                console.error('Erro na busca:', error);
                searchResults.innerHTML = `
                    <div class="p-4 text-center text-destructive">
                        <i class="fas fa-exclamation-triangle h-4 w-4 mb-2"></i>
                        <p>Erro na busca. Tente novamente.</p>
                    </div>
                `;
                searchResults.style.display = 'block';
            });
    }
    
    function highlightText(text, query) {
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark style="background: hsl(var(--primary) / 0.2); color: hsl(var(--primary));">$1</mark>');
    }
}

// ====== PAGE LOADING PROGRESS ======
function initializeLoadingProgress() {
    // Create progress bar
    const progressBar = document.createElement('div');
    progressBar.className = 'progress-bar';
    progressBar.style.position = 'fixed';
    progressBar.style.top = '0';
    progressBar.style.left = '0';
    progressBar.style.width = '100%';
    progressBar.style.zIndex = '9999';
    progressBar.style.height = '3px';
    
    const progressFill = document.createElement('div');
    progressFill.className = 'progress-fill';
    progressFill.style.width = '0%';
    
    progressBar.appendChild(progressFill);
    document.body.appendChild(progressBar);
    
    // Simulate loading progress
    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 90) progress = 90;
        progressFill.style.width = progress + '%';
    }, 100);
    
    window.addEventListener('load', () => {
        clearInterval(interval);
        progressFill.style.width = '100%';
        setTimeout(() => {
            progressBar.style.opacity = '0';
            setTimeout(() => {
                if (progressBar.parentNode) {
                    progressBar.parentNode.removeChild(progressBar);
                }
            }, 300);
        }, 200);
    });
}

// ====== INICIALIZAÇÃO PRINCIPAL ======
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tema primeiro para evitar flash
    initializeTheme();
    
    // Inicializar funcionalidades principais
    initializeAdvancedSearch();
    initializeCardAnimations();
    initializeAdvancedCardEffects();
    initializeLazyLoading();
    initializeKeyboardNavigation();
    initializeSmoothScroll();
    initializePerformanceMonitoring();
    initializeLoadingProgress();
    
    // Efeitos visuais avançados
    setTimeout(() => {
        createParticleSystem();
    }, 1000);
    
    // Event listeners globais
    document.addEventListener('click', handleClickOutside);
    
    // Adicionar classe para indicar que JS carregou
    document.body.classList.add('js-loaded', 'page-transition');
    
    console.log('🎮 Renx-Play inicializado com sucesso!');
    console.log('✨ Efeitos visuais ativados!');
});

// ====== UTILS ======
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// ====== EASTER EGGS ======
let konamiCode = [];
const konamiSequence = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65]; // ↑↑↓↓←→←→BA

document.addEventListener('keydown', function(e) {
    konamiCode.push(e.keyCode);
    konamiCode = konamiCode.slice(-konamiSequence.length);
    
    if (konamiCode.join('') === konamiSequence.join('')) {
        // Easter egg ativado!
        document.body.style.animation = 'rainbow 2s infinite';
        setTimeout(() => {
            document.body.style.animation = '';
        }, 4000);
        console.log('🌈 Konami Code ativado! Você encontrou um easter egg!');
    }
});

// CSS para o easter egg
const style = document.createElement('style');
style.textContent = `
    @keyframes rainbow {
        0% { filter: hue-rotate(0deg); }
        100% { filter: hue-rotate(360deg); }
    }
`;
document.head.appendChild(style);
