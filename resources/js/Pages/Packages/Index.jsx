import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Badge from '@/Components/Badge';
import { Head, Link, useForm, router } from '@inertiajs/react';

function CubeIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
        </svg>
    );
}

function TrashIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
        </svg>
    );
}

function PlusIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
    );
}

function ProgressBar({ used, total }) {
    const pct = total > 0 ? Math.min((used / total) * 100, 100) : 0;
    const remaining = total - used;
    const remainingPct = total > 0 ? (remaining / total) * 100 : 0;
    const barColor = remainingPct < 20 ? 'bg-rose-500' : remainingPct < 50 ? 'bg-amber-500' : 'bg-emerald-500';

    return (
        <div>
            <div className="flex items-center justify-between mb-1.5">
                <span className="text-xs text-gray-500">{used} de {total} usadas</span>
                <span className="text-xs font-semibold text-gray-700">{remaining} restantes</span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2.5">
                <div className={`${barColor} h-2.5 rounded-full transition-all duration-500`} style={{ width: `${pct}%` }} />
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

export default function PackagesIndex({ student, packages }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        total_lessons: 10,
        expires_at: '',
    });

    function submit(e) {
        e.preventDefault();
        post(`/admin/users/${student.id}/packages`, { onSuccess: () => reset() });
    }

    const list = packages ?? [];

    return (
        <AppLayout title={`Pacotes - ${student.name}`}>
            <Head title={`Pacotes - ${student.name}`} />
            <PageHeader title="Pacotes de Aulas" subtitle={student.name}>
                <Link href={`/admin/users/${student.id}`} className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 19l-7-7 7-7" /></svg>
                    Voltar
                </Link>
            </PageHeader>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Add Package Form */}
                <div className="lg:order-2">
                    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sticky top-6">
                        <div className="flex items-center gap-3 mb-5">
                            <div className="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                <PlusIcon className="w-5 h-5" />
                            </div>
                            <h3 className="text-sm font-semibold text-gray-900">Adicionar Pacote</h3>
                        </div>

                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label htmlFor="total_lessons" className="block text-sm font-medium text-gray-700 mb-1.5">Numero de Aulas</label>
                                <input
                                    id="total_lessons"
                                    type="number"
                                    min="1"
                                    value={data.total_lessons}
                                    onChange={(e) => setData('total_lessons', e.target.value)}
                                    className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow"
                                />
                                {errors.total_lessons && <p className="mt-1.5 text-xs text-rose-600">{errors.total_lessons}</p>}
                            </div>

                            <div>
                                <label htmlFor="expires_at" className="block text-sm font-medium text-gray-700 mb-1.5">
                                    Vencimento <span className="text-gray-400 font-normal">(opcional)</span>
                                </label>
                                <input
                                    id="expires_at"
                                    type="date"
                                    value={data.expires_at}
                                    onChange={(e) => setData('expires_at', e.target.value)}
                                    className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow"
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium disabled:opacity-50 transition-colors"
                            >
                                {processing ? 'Adicionando...' : 'Adicionar Pacote'}
                            </button>
                        </form>
                    </div>
                </div>

                {/* Packages List */}
                <div className="lg:col-span-2 lg:order-1">
                    {list.length === 0 ? (
                        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
                            <div className="w-16 h-16 rounded-2xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-4">
                                <CubeIcon className="w-8 h-8" />
                            </div>
                            <h3 className="text-base font-semibold text-gray-900 mb-1">Nenhum pacote cadastrado</h3>
                            <p className="text-sm text-gray-500">Adicione o primeiro pacote de aulas para este aluno.</p>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {list.map((pkg) => {
                                const status = getPackageStatus(pkg);
                                return (
                                    <div key={pkg.id} className="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-6">
                                        <div className="flex items-start justify-between mb-4">
                                            <div className="flex items-center gap-3">
                                                <div className={`w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 ${
                                                    pkg.is_active ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-400'
                                                }`}>
                                                    <CubeIcon className="w-5 h-5" />
                                                </div>
                                                <div>
                                                    <p className="text-base font-semibold text-gray-900">{pkg.total_lessons} aulas</p>
                                                    <p className="text-xs text-gray-500 mt-0.5">
                                                        Compra: {pkg.created_at ? new Date(pkg.created_at).toLocaleDateString('pt-BR') : '\u2014'}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Badge label={status.label} className={status.className} />
                                                {!pkg.is_active && (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            if (confirm('Remover este pacote?')) {
                                                                router.delete(`/admin/users/${student.id}/packages/${pkg.id}`);
                                                            }
                                                        }}
                                                        className="text-gray-400 hover:text-rose-600 transition-colors"
                                                        aria-label="Remover pacote"
                                                    >
                                                        <TrashIcon className="w-4 h-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </div>

                                        <ProgressBar used={pkg.used_lessons ?? 0} total={pkg.total_lessons} />

                                        <p className="text-xs text-gray-400 mt-3">
                                            {pkg.expires_at ? `Vence em ${new Date(pkg.expires_at).toLocaleDateString('pt-BR')}` : 'Sem vencimento'}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
