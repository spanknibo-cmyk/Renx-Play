# 🎮 Site de Jogos PHP - Modernizado

Projeto de site de jogos em PHP com design moderno inspirado no React e Tailwind CSS.

## ✨ Características

- **Design Moderno**: Interface inspirada no design do projeto React
- **Responsivo**: Totalmente adaptável para mobile, tablet e desktop
- **Autenticação**: Sistema completo de login e registro
- **Painel Admin**: Dashboard para gerenciar jogos e usuários
- **Upload de Arquivos**: Sistema para capas e screenshots
- **Busca em Tempo Real**: Pesquisa instantânea de jogos e usuários
- **Sistema de Roles**: Diferentes níveis de acesso (USER, ADMIN, SUPER_ADMIN, DEV)

## 🚀 Tecnologias

- **PHP 7.4+**
- **MySQL/MariaDB**
- **CSS3** com variáveis customizadas (theme.css)
- **JavaScript** vanilla para interatividade
- **SVG Icons** para ícones vetoriais

## 📁 Estrutura dos Arquivos

```
Site PhP/
├── index.php           # Página principal
├── auth.php           # Login e registro
├── dashboard.php      # Painel administrativo
├── profile.php        # Perfil do usuário
├── config.php         # Configurações e funções
├── theme.css          # Estilos modernos
├── script.js          # JavaScript
├── search_games.php   # API de busca de jogos
├── search_users.php   # API de busca de usuários
└── uploads/           # Arquivos enviados
    ├── covers/        # Capas dos jogos
    └── screenshots/   # Screenshots dos jogos
```

## ⚙️ Instalação

1. **Configure o banco de dados** no arquivo `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'seu_banco');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
```

2. **Crie as tabelas necessárias**:
- `users` (id, username, email, password, role, created_by, created_at)
- `games` (id, title, slug, description, cover_image, screenshots, languages, platforms, version, category, translator, posted_by, download_links, downloads_count, created_at)

3. **Configure as permissões das pastas**:
```bash
chmod 755 uploads/
chmod 755 uploads/covers/
chmod 755 uploads/screenshots/
```

## 🎨 Design System

O projeto utiliza um sistema de design baseado em variáveis CSS que suporta modo escuro e claro:

### Cores Principais
- `--primary`: Cor principal do tema
- `--background`: Cor de fundo
- `--foreground`: Cor do texto
- `--card`: Cor dos cartões
- `--border`: Cor das bordas
- `--muted`: Cores suavizadas

### Componentes
- **Cards**: `.card`, `.card-header`, `.card-content`
- **Botões**: `.btn`, `.btn-primary`, `.btn-secondary`
- **Formulários**: Inputs com foco estilizado
- **Navegação**: Header responsivo com menu hambúrguer

## 👥 Sistema de Roles

- **USER**: Visualização básica
- **ADMIN**: Gerenciar jogos próprios
- **SUPER_ADMIN**: Gerenciar ADMINs e seus jogos
- **DEV**: Acesso total ao sistema

## 📱 Responsividade

O design é totalmente responsivo com breakpoints:
- **Desktop**: 1200px+
- **Tablet**: 768px - 1199px
- **Mobile**: < 768px

### Features Mobile
- Menu hambúrguer
- Cards empilhados
- Tabelas com scroll horizontal
- Formulários otimizados para toque

## 🔧 Funcionalidades

### Jogos
- Listagem com paginação
- Upload de capas e screenshots
- Sistema de downloads
- Categorização
- Busca em tempo real

### Usuários
- Registro e login
- Perfis personalizáveis
- Sistema hierárquico de permissões
- Busca e filtros por role

### Dashboard
- Estatísticas em tempo real
- Gerenciamento de conteúdo
- Interface intuitiva
- Ações em lote

## 🎯 Melhorias Implementadas

1. **UI/UX Moderna**: Design limpo e profissional
2. **Performance**: CSS otimizado e JavaScript eficiente
3. **Acessibilidade**: Contraste adequado e navegação por teclado
4. **SEO**: Meta tags e estrutura semântica
5. **Segurança**: Validação e sanitização de dados

## 📄 Licença

Este projeto é open source e está disponível sob a licença MIT.

## 🤝 Contribuição

Contribuições são bem-vindas! Sinta-se à vontade para:
- Reportar bugs
- Sugerir melhorias
- Enviar pull requests

---

*Projeto modernizado com design inspirado em React e Tailwind CSS*