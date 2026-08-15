# 🔧 Sistema de Gestão de Oficina Automóvel

Aplicação full-stack para gestão de uma oficina automóvel: utilizadores, veículos, peças e histórico de manutenção, com autenticação e controlo de permissões.

Projeto desenvolvido para praticar bases de dados relacionais, APIs REST, autenticação JWT e operações CRUD completas.

## 🛠️ Tecnologias

**Front-end:** Angular, Bootstrap
**Back-end:** PHP (API REST, sem framework)
**Base de dados:** MySQL

## 📦 Estrutura do repositório

```
oficina-sistema/
├── backend/     → API REST em PHP
├── frontend/    → Aplicação Angular
└── database.sql → Schema da base de dados
```

## ✅ Funcionalidades

### Módulo 1 — Autenticação
- [x] Registo de utilizadores
- [x] Login com JWT
- [x] Logout
- [x] Recuperação de password
- [x] Rotas protegidas (Angular Guards + validação de token no backend)

### Módulo 2 — Gestão de utilizadores
- [ ] CRUD de utilizadores
- [ ] Permissões por tipo de utilizador (Administrador / Funcionário / Visitante)
- [ ] Ativar / desativar contas

### Módulo 3 — Gestão de veículos
- [ ] CRUD de veículos
- [ ] Pesquisa e filtros

### Módulo 4 — Gestão de peças
- [ ] CRUD de peças
- [ ] Controlo de inventário

### Módulo 5 — Histórico de manutenção
- [ ] Registo de serviços associados a veículos

### Módulo 6 — Dashboard
- [ ] Estatísticas gerais (veículos, clientes, peças, stock baixo)

## 🚀 Como correr o projeto localmente

### Pré-requisitos
- XAMPP (PHP 8+ e MySQL)
- Node.js + Angular CLI
- Composer

### Backend
1. Copia a pasta `backend/` para `C:\xampp\htdocs\oficina-backend`
2. Corre `composer install` dentro dessa pasta
3. Importa o `database.sql` no phpMyAdmin
4. Confirma que Apache e MySQL estão ativos no XAMPP

### Frontend
```bash
cd frontend
npm install
ng serve
```
Abre `http://localhost:4200`

## 📄 Base de dados

Modelo relacional com as tabelas: `roles`, `users`, `clients`, `vehicles`, `parts`, `maintenance`, `maintenance_parts`.

```
roles ──< users

clients ──< vehicles ──< maintenance >── users (funcionário)
                              │
                              └──< maintenance_parts >── parts
```