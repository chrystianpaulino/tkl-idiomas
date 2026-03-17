# TKL Idiomas — Sistema de Gestão de Idiomas

Sistema de gestão para escolas de idiomas, construído com Laravel 11 + Inertia.js + React.
Em migração para um modelo **multi-tenant SaaS** onde cada escola é um tenant isolado.

## Stack

- **Backend:** Laravel 11, SQLite (dev/test), Breeze (auth)
- **Frontend:** Inertia.js + React (JSX), Tailwind CSS
- **Arquitetura:** Actions + FormRequests + Policies

## Setup

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed   # admin@tkl.com / password
npm install && npm run build
composer run dev              # inicia servidor + vite + queue
```

## Comandos

```bash
composer run dev                             # desenvolvimento (servidor + vite + queue)
php artisan migrate:fresh --seed             # reset do banco com dados de exemplo
php artisan test                             # todos os testes
php artisan test --filter NomeDoTest         # teste específico
./vendor/bin/pint                            # formatação de código
```

## Credenciais de desenvolvimento

| Papel       | Email              | Senha    |
|-------------|--------------------|----------|
| Admin       | admin@tkl.com      | password |

## Funcionalidades

- Gestão de turmas, professores e alunos
- Registro de aulas com débito automático de pacotes (transação atômica)
- Cancelamento de aulas com estorno de créditos
- Pacotes de aulas por aluno com controle de saldo
- Upload de materiais por turma
- Registro e rastreamento de pagamentos
- Agendamento de aulas recorrentes com geração de instâncias
- Listas de exercícios/lição de casa com submissão de respostas (texto e arquivo)
- Acompanhamento de progresso dos alunos (streak, milestones)
- **Gestão de escolas** (base do multi-tenant)

## Multi-Tenancy (em progresso)

A fundação de dados está completa — todas as tabelas principais têm `school_id`. O próximo passo é implementar os Global Scopes e o middleware de resolução de tenant.

Ver `docs/architecture-review.md` para o plano completo.

## Arquitetura

Ver `CLAUDE.md` para detalhes de arquitetura, padrões de código e convenções de teste.
