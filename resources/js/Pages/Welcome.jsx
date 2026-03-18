import { Head, Link } from '@inertiajs/react';

function BookOpenIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
        </svg>
    );
}

function UsersIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
    );
}

function CurrencyIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

function ChartIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
        </svg>
    );
}

function CheckIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M5 13l4 4L19 7" />
        </svg>
    );
}

function BuildingIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
        </svg>
    );
}

const features = [
    {
        icon: BuildingIcon,
        title: 'Multi-escola',
        desc: 'Gerencie múltiplas escolas de idiomas na mesma plataforma. Cada escola tem seus dados completamente isolados.',
        color: 'bg-violet-50 text-violet-600',
    },
    {
        icon: UsersIcon,
        title: 'Gestão de Pessoas',
        desc: 'Cadastre professores e alunos, controle matrículas em turmas e acompanhe o histórico de cada estudante.',
        color: 'bg-sky-50 text-sky-600',
    },
    {
        icon: BookOpenIcon,
        title: 'Controle de Aulas',
        desc: 'Registre aulas realizadas, gerencie materiais e listas de exercícios por turma.',
        color: 'bg-indigo-50 text-indigo-600',
    },
    {
        icon: CurrencyIcon,
        title: 'Pacotes e Pagamentos',
        desc: 'Controle pacotes de aulas por aluno, registre pagamentos e acompanhe a inadimplência.',
        color: 'bg-emerald-50 text-emerald-600',
    },
    {
        icon: ChartIcon,
        title: 'Relatórios',
        desc: 'Veja a receita da escola, pacotes não pagos e atividade recente direto no dashboard.',
        color: 'bg-amber-50 text-amber-600',
    },
];

const roles = [
    {
        role: 'Dono da Plataforma',
        badge: 'bg-violet-100 text-violet-700',
        items: ['Cria e gerencia escolas', 'Define o admin de cada escola', 'Visão global de receita e alunos'],
    },
    {
        role: 'Admin da Escola',
        badge: 'bg-rose-100 text-rose-700',
        items: ['Cadastra professores e alunos', 'Gerencia pacotes e pagamentos', 'Visualiza relatório financeiro'],
    },
    {
        role: 'Professor',
        badge: 'bg-sky-100 text-sky-700',
        items: ['Cria e gerencia suas turmas', 'Registra aulas ministradas', 'Faz upload de materiais'],
    },
    {
        role: 'Aluno',
        badge: 'bg-emerald-100 text-emerald-700',
        items: ['Acompanha seu progresso', 'Acessa materiais e exercícios', 'Visualiza histórico de aulas'],
    },
];

export default function Welcome() {
    return (
        <>
            <Head title="EduGest — Plataforma para Escolas de Idiomas" />

            <div className="min-h-screen bg-white">

                {/* ── Nav ─────────────────────────────────────────────── */}
                <header className="border-b border-gray-100 bg-white/80 backdrop-blur sticky top-0 z-10">
                    <div className="max-w-6xl mx-auto px-6 flex items-center justify-between h-16">
                        <div className="flex items-center gap-2.5">
                            <div className="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                                <span className="text-white font-bold text-sm">E</span>
                            </div>
                            <span className="font-bold text-gray-900 text-lg">EduGest</span>
                        </div>
                        <Link
                            href="/login"
                            className="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-sm font-medium transition-colors"
                        >
                            Entrar
                        </Link>
                    </div>
                </header>

                {/* ── Hero ────────────────────────────────────────────── */}
                <section className="max-w-6xl mx-auto px-6 pt-20 pb-16 text-center">
                    <span className="inline-block bg-indigo-50 text-indigo-700 text-xs font-semibold px-3 py-1 rounded-full mb-6 tracking-wide uppercase">
                        Plataforma SaaS para Escolas de Idiomas
                    </span>
                    <h1 className="text-5xl font-bold text-gray-900 leading-tight mb-6 max-w-3xl mx-auto">
                        Gerencie sua escola de idiomas com{' '}
                        <span className="text-indigo-600">simplicidade</span>
                    </h1>
                    <p className="text-lg text-gray-500 mb-10 max-w-xl mx-auto">
                        Turmas, professores, alunos, pacotes de aulas e pagamentos. Tudo em um só lugar, isolado por escola.
                    </p>
                    <div className="flex items-center justify-center gap-4">
                        <Link
                            href="/login"
                            className="bg-indigo-600 hover:bg-indigo-700 text-white px-7 py-3 rounded-xl text-base font-medium transition-colors shadow-lg shadow-indigo-200"
                        >
                            Acessar o Sistema
                        </Link>
                    </div>
                </section>

                {/* ── Features ────────────────────────────────────────── */}
                <section className="bg-gray-50 py-16">
                    <div className="max-w-6xl mx-auto px-6">
                        <h2 className="text-2xl font-bold text-gray-900 text-center mb-10">
                            Tudo que sua escola precisa
                        </h2>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            {features.map((f) => (
                                <div key={f.title} className="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                                    <div className={`w-11 h-11 rounded-xl ${f.color} flex items-center justify-center mb-4`}>
                                        <f.icon className="w-5 h-5" />
                                    </div>
                                    <h3 className="font-semibold text-gray-900 mb-2">{f.title}</h3>
                                    <p className="text-sm text-gray-500 leading-relaxed">{f.desc}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ── Roles ───────────────────────────────────────────── */}
                <section className="py-16">
                    <div className="max-w-6xl mx-auto px-6">
                        <h2 className="text-2xl font-bold text-gray-900 text-center mb-3">
                            Um acesso para cada perfil
                        </h2>
                        <p className="text-gray-500 text-center mb-10 text-sm">
                            Cada usuário vê apenas o que precisa, sem informações de outras escolas.
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                            {roles.map((r) => (
                                <div key={r.role} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                                    <span className={`inline-block text-xs font-semibold px-2.5 py-1 rounded-full mb-4 ${r.badge}`}>
                                        {r.role}
                                    </span>
                                    <ul className="space-y-2.5">
                                        {r.items.map((item) => (
                                            <li key={item} className="flex items-start gap-2 text-sm text-gray-600">
                                                <CheckIcon className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                                                {item}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ── CTA ─────────────────────────────────────────────── */}
                <section className="bg-indigo-600 py-14">
                    <div className="max-w-2xl mx-auto px-6 text-center">
                        <h2 className="text-2xl font-bold text-white mb-3">
                            Pronto para começar?
                        </h2>
                        <p className="text-indigo-200 mb-8 text-sm">
                            Entre com suas credenciais e acesse o painel da sua escola.
                        </p>
                        <Link
                            href="/login"
                            className="bg-white text-indigo-600 hover:bg-indigo-50 px-8 py-3 rounded-xl text-sm font-semibold transition-colors shadow"
                        >
                            Fazer Login
                        </Link>
                    </div>
                </section>

                {/* ── Footer ──────────────────────────────────────────── */}
                <footer className="border-t border-gray-100 py-8">
                    <div className="max-w-6xl mx-auto px-6 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="w-6 h-6 bg-indigo-600 rounded-md flex items-center justify-center">
                                <span className="text-white font-bold text-xs">E</span>
                            </div>
                            <span className="text-sm font-semibold text-gray-700">EduGest</span>
                        </div>
                        <p className="text-xs text-gray-400">Plataforma de gestao para escolas de idiomas</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
