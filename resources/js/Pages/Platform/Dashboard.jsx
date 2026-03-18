import AppLayout from '@/Layouts/AppLayout';
import StatsCard from '@/Components/StatsCard';
import { Head, Link } from '@inertiajs/react';

function BuildingIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
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

function CheckCircleIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

export default function PlatformDashboard({ stats }) {
    const fmt = (value) =>
        new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value ?? 0);

    return (
        <AppLayout title="Painel da Plataforma">
            <Head title="Painel da Plataforma" />

            <div className="mb-8">
                <h1 className="text-2xl font-bold text-gray-900">Painel da Plataforma</h1>
                <p className="text-sm text-gray-500 mt-1">Visao geral de todas as escolas cadastradas</p>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                <StatsCard
                    title="Total de Escolas"
                    value={stats?.total_schools}
                    icon={<BuildingIcon className="w-5 h-5" />}
                    iconBg="bg-violet-50"
                    iconColor="text-violet-600"
                />
                <StatsCard
                    title="Escolas Ativas"
                    value={stats?.active_schools}
                    icon={<CheckCircleIcon className="w-5 h-5" />}
                    iconBg="bg-emerald-50"
                    iconColor="text-emerald-600"
                />
                <StatsCard
                    title="Total de Alunos"
                    value={stats?.total_students}
                    icon={<UsersIcon className="w-5 h-5" />}
                    iconBg="bg-sky-50"
                    iconColor="text-sky-600"
                />
                <StatsCard
                    title="Receita Total"
                    value={fmt(stats?.total_revenue)}
                    icon={<CurrencyIcon className="w-5 h-5" />}
                    iconBg="bg-amber-50"
                    iconColor="text-amber-600"
                />
            </div>

            {/* Quick Actions */}
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h3 className="text-sm font-semibold text-gray-900 mb-4">Acesso Rapido</h3>
                <div className="flex flex-wrap gap-3">
                    <Link
                        href="/platform/schools"
                        className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                    >
                        <BuildingIcon className="w-4 h-4" />
                        Gerenciar Escolas
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
