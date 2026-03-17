import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Avatar from '@/Components/Avatar';
import Badge from '@/Components/Badge';
import { Head, Link } from '@inertiajs/react';

function ProgressBar({ used, total }) {
    const pct = total > 0 ? Math.min((used / total) * 100, 100) : 0;
    const remaining = total - used;
    const remainingPct = total > 0 ? (remaining / total) * 100 : 0;
    const barColor = remainingPct < 20 ? 'bg-rose-500' : remainingPct < 50 ? 'bg-amber-500' : 'bg-indigo-600';

    return (
        <div>
            <div className="flex items-center justify-between mb-1.5">
                <span className="text-xs text-gray-500">{used} de {total} usadas</span>
                <span className="text-xs font-semibold text-gray-700">{remaining} restantes</span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2">
                <div className={`${barColor} h-2 rounded-full transition-all duration-500`} style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}

function getPackageStatus(pkg) {
    if (!pkg.is_active && pkg.remaining <= 0) {
        return { label: 'Esgotado', className: 'bg-gray-100 text-gray-600 border border-gray-200' };
    }
    if (!pkg.is_active) {
        return { label: 'Expirado', className: 'bg-rose-50 text-rose-700 border border-rose-200' };
    }
    return { label: 'Ativo', className: 'bg-emerald-50 text-emerald-700 border border-emerald-200' };
}

function BookOpenIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
        </svg>
    );
}

export default function UsersShow({ user, packages, recentLessons, enrolledClasses }) {
    return (
        <AppLayout title={user.name}>
            <Head title={user.name} />
            <PageHeader title="Perfil do Usuario">
                <div className="flex items-center gap-2">
                    <Link href="/admin/users" className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                        <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 19l-7-7 7-7" /></svg>
                        Voltar
                    </Link>
                    {user.role === 'aluno' && (
                        <Link href={`/admin/users/${user.id}/packages`} className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                            <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            Adicionar Pacote
                        </Link>
                    )}
                </div>
            </PageHeader>

            {/* Profile Hero */}
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 mb-6">
                <div className="flex items-center gap-5">
                    <Avatar name={user.name} size="xl" />
                    <div>
                        <h2 className="text-xl font-bold text-gray-900">{user.name}</h2>
                        <p className="text-sm text-gray-500 mt-0.5">{user.email}</p>
                        <div className="mt-2">
                            <Badge role={user.role} />
                        </div>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Sidebar: Enrolled Classes */}
                <div className="space-y-6">
                    {enrolledClasses?.length > 0 && (
                        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
                            <div className="px-6 py-4 border-b border-gray-100">
                                <h3 className="text-sm font-semibold text-gray-900">Turmas Matriculadas</h3>
                            </div>
                            <ul className="divide-y divide-gray-100">
                                {enrolledClasses.map((c) => (
                                    <li key={c.id}>
                                        <Link href={`/classes/${c.id}`} className="px-6 py-3.5 flex items-center gap-3 hover:bg-gray-50/50 transition-colors block">
                                            <div className="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
                                                <BookOpenIcon className="w-4 h-4" />
                                            </div>
                                            <span className="text-sm font-medium text-gray-900">{c.name}</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>

                {/* Main content */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Packages */}
                    {packages?.length > 0 && (
                        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
                            <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                                <h3 className="text-sm font-semibold text-gray-900">Pacotes de Aulas</h3>
                                {user.role === 'aluno' && (
                                    <Link href={`/admin/users/${user.id}/packages`} className="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                                        Gerenciar
                                    </Link>
                                )}
                            </div>
                            <div className="p-6 space-y-4">
                                {packages.map((pkg) => {
                                    const status = getPackageStatus(pkg);
                                    return (
                                        <div key={pkg.id} className="p-4 bg-gray-50 rounded-xl">
                                            <div className="flex items-center justify-between mb-3">
                                                <span className="text-sm font-semibold text-gray-900">{pkg.total_lessons} aulas</span>
                                                <Badge label={status.label} className={status.className} />
                                            </div>
                                            <ProgressBar used={pkg.used_lessons ?? 0} total={pkg.total_lessons} />
                                            <div className="flex items-center gap-4 mt-3">
                                                <span className="text-xs text-gray-400">
                                                    Compra: {pkg.created_at ? new Date(pkg.created_at).toLocaleDateString('pt-BR') : '\u2014'}
                                                </span>
                                                <span className="text-xs text-gray-400">
                                                    {pkg.expires_at ? `Vence: ${new Date(pkg.expires_at).toLocaleDateString('pt-BR')}` : 'Sem vencimento'}
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Recent Lessons */}
                    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
                        <div className="px-6 py-4 border-b border-gray-100">
                            <h3 className="text-sm font-semibold text-gray-900">Aulas Recentes</h3>
                        </div>
                        {recentLessons?.length === 0 || !recentLessons ? (
                            <div className="py-12 text-center">
                                <div className="w-12 h-12 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-3">
                                    <BookOpenIcon className="w-6 h-6" />
                                </div>
                                <p className="text-sm text-gray-500">Nenhuma aula registrada.</p>
                            </div>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {recentLessons.map((l) => (
                                    <li key={l.id} className="px-6 py-4 flex items-center gap-4 hover:bg-gray-50/50 transition-colors">
                                        <div className="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
                                            <div className="text-center leading-tight">
                                                <span className="text-xs font-bold block">
                                                    {l.conducted_at ? new Date(l.conducted_at).toLocaleDateString('pt-BR', { day: '2-digit' }) : '--'}
                                                </span>
                                                <span className="text-[10px] uppercase">
                                                    {l.conducted_at ? new Date(l.conducted_at).toLocaleDateString('pt-BR', { month: 'short' }).replace('.', '') : ''}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-900 truncate">{l.title}</p>
                                            <p className="text-xs text-gray-500 mt-0.5">
                                                {l.turma_class?.name} &middot; {l.conducted_at ? new Date(l.conducted_at).toLocaleDateString('pt-BR') : '\u2014'}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
