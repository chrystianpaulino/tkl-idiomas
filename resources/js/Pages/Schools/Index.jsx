import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import { Head, Link, router, usePage } from '@inertiajs/react';

function PlusIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 4.5v15m7.5-7.5h-15" />
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

export default function SchoolsIndex({ schools = [] }) {
    const { auth } = usePage().props;
    const base = auth?.user?.role === 'super_admin' ? '/platform/schools' : '/admin/schools';

    function handleDelete(school) {
        if (!confirm(`Remover a escola "${school.name}"? Esta ação não pode ser desfeita.`)) return;
        router.delete(`${base}/${school.id}`);
    }

    return (
        <AppLayout title="Escolas">
            <Head title="Escolas" />
            <PageHeader title="Escolas" subtitle="Gerenciar escolas do sistema">
                <Link
                    href={`${base}/create`}
                    className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                >
                    <PlusIcon className="w-4 h-4" />
                    Nova Escola
                </Link>
            </PageHeader>

            {schools.length === 0 ? (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm py-16 text-center">
                    <div className="w-12 h-12 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-3">
                        <BuildingIcon className="w-6 h-6" />
                    </div>
                    <p className="text-sm text-gray-500 mb-4">Nenhuma escola cadastrada.</p>
                    <Link
                        href={`${base}/create`}
                        className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                    >
                        Criar Escola
                    </Link>
                </div>
            ) : (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 bg-gray-50/50">
                                <th className="text-left px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Escola</th>
                                <th className="text-left px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Slug</th>
                                <th className="text-left px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Usuários</th>
                                <th className="text-left px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                                <th className="px-6 py-3.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {schools.map((school) => (
                                <tr key={school.id} className="hover:bg-gray-50/50 transition-colors">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-3">
                                            <div className="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0 font-bold text-sm">
                                                {school.name.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <p className="font-medium text-gray-900">{school.name}</p>
                                                {school.email && <p className="text-xs text-gray-500 mt-0.5">{school.email}</p>}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4">
                                        <code className="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded-lg">{school.slug}</code>
                                    </td>
                                    <td className="px-6 py-4 text-gray-600">
                                        {school.users_count ?? 0}
                                    </td>
                                    <td className="px-6 py-4">
                                        {school.active ? (
                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                Ativa
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                                Inativa
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-3 justify-end">
                                            <Link
                                                href={`${base}/${school.id}/edit`}
                                                className="text-sm font-medium text-indigo-600 hover:text-indigo-700 transition-colors"
                                            >
                                                Editar
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() => handleDelete(school)}
                                                className="text-sm text-rose-500 hover:text-rose-700 transition-colors"
                                            >
                                                Remover
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AppLayout>
    );
}
