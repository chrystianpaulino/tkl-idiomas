# TKL Idiomas — Handoff: Multi-Tenant Foundation

**Data:** 2026-03-17
**Fase:** Multi-Tenancy SaaS — Fundação de Dados (Completa)

---

## Estado atual do sistema

O sistema TKL Idiomas está funcional como monolito e está em processo de migração para multi-tenant SaaS.

### Funcionalidades completas

- Auth (Breeze): login, logout, reset de senha, verificação de email
- Roles: `admin`, `professor`, `aluno` (middleware + FormRequests + Policies)
- Turmas (CRUD) com ClassPolicy
- Registro de aulas com débito atômico de pacotes (`lockForUpdate()`)
- Cancelamento de aulas com estorno
- Pacotes de aulas por aluno
- Upload de materiais por turma
- Registro de pagamentos
- Agendamento recorrente de aulas (schedules + scheduled_lessons)
- Progresso de alunos (streak, milestones)
- Listas de exercícios / lição de casa (submissão texto e arquivo)
- **Gestão de escolas** — CRUD em `/admin/schools`

### Multi-tenancy: o que foi feito

#### Migrações criadas
| Arquivo | O que faz |
|---|---|
| `2026_03_16_200001_add_school_id_to_tenant_tables` | Adiciona `school_id` nullable em: classes, lesson_packages, lessons, materials, payments |
| `2026_03_16_200002_seed_default_school_and_populate_school_ids` | Cria escola "TKL Idiomas" (slug: tkl, id: 1) e popula todos os school_ids |
| `2026_03_16_200003_make_school_id_not_null_on_tenant_tables` | Torna school_id NOT NULL nas 5 tabelas (com guard de segurança) |

#### Estado das colunas
| Tabela | school_id | Nullable |
|---|---|---|
| `users` | ✅ | Sim (super_admin terá NULL no futuro) |
| `classes` | ✅ | NOT NULL |
| `lesson_packages` | ✅ | NOT NULL |
| `lessons` | ✅ | NOT NULL |
| `materials` | ✅ | NOT NULL |
| `payments` | ✅ | NOT NULL |

#### Factories atualizadas
- `SchoolFactory` criado (com estado `inactive()`)
- Todos os factories de modelos com `school_id` incluem `school_id => School::factory()` por padrão
- `LessonFactory` reestruturado para compartilhar uma escola entre professor, aluno, turma e pacote

#### Modelos atualizados (`school_id` em `$fillable`)
`TurmaClass`, `LessonPackage`, `Lesson`, `Material`, `Payment`

#### Backend de escolas
- `CreateSchoolAction`, `UpdateSchoolAction`
- `StoreSchoolRequest`, `UpdateSchoolRequest` (validação de slug: `/^[a-z0-9\-]+$/`)
- `SchoolController` com 6 rotas em `/admin/schools`

#### Frontend de escolas
- `Pages/Schools/Index.jsx` — tabela com status, slug, contagem de usuários
- `Pages/Schools/Create.jsx` — slug gerado automaticamente a partir do nome
- `Pages/Schools/Edit.jsx` — toggle de status ativo/inativo
- Sidebar atualizado com link "Escolas" para admin

---

## O que NÃO foi feito ainda (próximos passos)

### Step 3 — BelongsToSchool trait + Global Scope
Criar `app/Models/Concerns/BelongsToSchool.php` com:
- `addGlobalScope(new SchoolScope)` no boot
- Hook `creating` que auto-popula `school_id` do container

Criar `app/Models/Scopes/SchoolScope.php`:
- Aplica `WHERE school_id = ?` em todas as queries
- Bypass para `super_admin` e console sem tenant
- Resolve tenant via `app('current.school')`

Aplicar o trait em: `TurmaClass`, `LessonPackage`, `Lesson`, `Material`, `Payment`, `User`

### Step 4 — SetTenantContext middleware
Criar `app/Http/Middleware/SetTenantContext.php`:
- Resolve `school_id` do usuário logado
- Verifica se escola está ativa
- Registra `app()->instance('current.school', $school)`

Registrar em `bootstrap/app.php` APÓS o middleware de auth.

### Step 5 — Roles
Migração: adicionar `super_admin` e `school_admin` ao enum `users.role`
- `admin@tkl.com` → `super_admin`
- `admin` existentes → `school_admin`
- Atualizar helpers: `isSuperAdmin()`, `isSchoolAdmin()`
- Atualizar `EnsureUserHasRole` middleware

### Step 6 — Policies com cross-school check
Todas as Policies devem adicionar:
```php
if ($user->isSuperAdmin()) return true;
return $user->school_id === $model->school_id;
```

### Step 7 — Testes de isolamento
`tests/Unit/Tenancy/TenantIsolationTest.php`:
- User de escola A não vê dados da escola B
- Route model binding retorna 404 para recursos de outra escola
- Super admin vê dados de todas as escolas

---

## Credenciais e banco

- Admin: `admin@tkl.com` / `password`
- Banco: `database/database.sqlite`
- Default school: id=1, name="TKL Idiomas", slug="tkl"
- Seed: 3 professores, 10 alunos, 1 turma "Inglês Básico"

## Testes

**97 passando, 1 falha pré-existente** (ExampleTest — boilerplate Breeze que testa `/` retornando 200 mas o sistema redireciona para `/dashboard`).

```bash
php artisan test   # deve mostrar: 97 passed, 1 failed
```
