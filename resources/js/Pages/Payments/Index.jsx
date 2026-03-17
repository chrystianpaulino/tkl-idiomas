import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import StatsCard from '@/Components/StatsCard';
import Badge from '@/Components/Badge';
import { Head, router, Link } from '@inertiajs/react';
import { useState } from 'react';

const methodLabels = { pix: 'PIX', cash: 'Dinheiro', card: 'Cartao', transfer: 'Transferencia', other: 'Outro' };

const formatBRL = (value) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

function CubeIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round">
            <path d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
        </svg>
    );
}

function CheckCircleIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round">
            <path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

function XCircleIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round">
            <path d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

function PaymentForm({ studentId, pkg }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [form, setForm] = useState({
        amount: pkg.price ?? '',
        method: 'pix',
        paid_at: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.post(
            route('admin.payments.store', { student: studentId, package: pkg.id }),
            form,
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setOpen(false);
                },
            }
        );
    };

    if (!open) {
        return (
            <button
                onClick={() => setOpen(true)}
                className="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-xl text-xs font-medium transition-colors"
            >
                Registrar Pagamento
            </button>
        );
    }

    return (
        <form onSubmit={handleSubmit} className="mt-3 bg-gray-50 rounded-xl p-4 space-y-3 border border-gray-200">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">Valor (R$)</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        value={form.amount}
                        onChange={(e) => setForm({ ...form, amount: e.target.value })}
                        className="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">Metodo</label>
                    <select
                        value={form.method}
                        onChange={(e) => setForm({ ...form, method: e.target.value })}
                        className="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="pix">PIX</option>
                        <option value="cash">Dinheiro</option>
                        <option value="card">Cartao</option>
                        <option value="transfer">Transferencia</option>
                        <option value="other">Outro</option>
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">Data do Pagamento</label>
                    <input
                        type="date"
                        value={form.paid_at}
                        onChange={(e) => setForm({ ...form, paid_at: e.target.value })}
                        className="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">Observacoes</label>
                    <textarea
                        value={form.notes}
                        onChange={(e) => setForm({ ...form, notes: e.target.value })}
                        className="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        rows={1}
                    />
                </div>
            </div>
            <div className="flex items-center gap-2">
                <button
                    type="submit"
                    disabled={processing}
                    className="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-medium transition-colors disabled:opacity-50"
                >
                    {processing ? 'Salvando...' : 'Confirmar'}
                </button>
                <button
                    type="button"
                    onClick={() => setOpen(false)}
                    className="text-xs text-gray-500 hover:text-gray-700"
                >
                    Cancelar
                </button>
            </div>
        </form>
    );
}

function StatusBadge({ pkg }) {
    if (pkg.is_active) {
        return <Badge label="Ativo" className="bg-emerald-50 text-emerald-700 border border-emerald-200" />;
    }
    if (pkg.remaining === 0) {
        return <Badge label="Esgotado" className="bg-rose-50 text-rose-700 border border-rose-200" />;
    }
    return <Badge label="Expirado" className="bg-amber-50 text-amber-700 border border-amber-200" />;
}

export default function PaymentsIndex({ student, packages }) {
    const totalPackages = packages.length;
    const paidPackages = packages.filter((p) => p.is_paid).length;
    const unpaidPackages = totalPackages - paidPackages;

    return (
        <AppLayout title={`Pagamentos - ${student.name}`}>
            <Head title={`Pagamentos - ${student.name}`} />

            <PageHeader
                title={`Pagamentos de ${student.name}`}
                subtitle={student.email}
            >
                <Link
                    href={route('admin.users.show', student.id)}
                    className="text-sm text-indigo-600 hover:text-indigo-700 font-medium"
                >
                    Voltar ao aluno
                </Link>
            </PageHeader>

            {/* Summary cards */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
                <StatsCard
                    title="Total de Pacotes"
                    value={totalPackages}
                    icon={<CubeIcon className="w-5 h-5" />}
                    iconBg="bg-indigo-50"
                    iconColor="text-indigo-600"
                />
                <StatsCard
                    title="Pacotes Pagos"
                    value={paidPackages}
                    icon={<CheckCircleIcon className="w-5 h-5" />}
                    iconBg="bg-emerald-50"
                    iconColor="text-emerald-600"
                />
                <StatsCard
                    title="Pacotes Nao Pagos"
                    value={unpaidPackages}
                    icon={<XCircleIcon className="w-5 h-5" />}
                    iconBg="bg-rose-50"
                    iconColor="text-rose-600"
                />
            </div>

            {/* Packages table */}
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-100">
                    <h3 className="text-sm font-semibold text-gray-900">Pacotes e Pagamentos</h3>
                </div>

                {packages.length === 0 ? (
                    <div className="px-6 py-12 text-center text-sm text-gray-500">
                        Nenhum pacote encontrado para este aluno.
                    </div>
                ) : (
                    <div className="divide-y divide-gray-100">
                        {packages.map((pkg) => (
                            <div key={pkg.id} className="px-6 py-5">
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    {/* Package info */}
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-3 mb-2">
                                            <p className="text-sm font-medium text-gray-900">
                                                {pkg.total_lessons} aulas ({pkg.used_lessons} usadas, {pkg.remaining} restantes)
                                            </p>
                                            <StatusBadge pkg={pkg} />
                                            {pkg.is_paid ? (
                                                <Badge label="Pago" className="bg-emerald-50 text-emerald-700 border border-emerald-200" />
                                            ) : (
                                                <Badge label="Pendente" className="bg-amber-50 text-amber-700 border border-amber-200" />
                                            )}
                                        </div>
                                        <div className="flex flex-wrap gap-x-6 gap-y-1 text-xs text-gray-500">
                                            {pkg.price != null && (
                                                <span>Valor: {formatBRL(pkg.price)}</span>
                                            )}
                                            {pkg.purchased_at && (
                                                <span>Compra: {new Date(pkg.purchased_at).toLocaleDateString('pt-BR')}</span>
                                            )}
                                            {pkg.expires_at && (
                                                <span>Expira: {new Date(pkg.expires_at).toLocaleDateString('pt-BR')}</span>
                                            )}
                                        </div>
                                    </div>

                                    {/* Payment details or register button */}
                                    <div className="flex-shrink-0">
                                        {pkg.payment ? (
                                            <div className="text-right text-xs text-gray-600 space-y-0.5">
                                                <p className="font-medium text-gray-900">{formatBRL(pkg.payment.amount)}</p>
                                                <p>{methodLabels[pkg.payment.method] ?? pkg.payment.method}</p>
                                                <p>{pkg.payment.paid_at ? new Date(pkg.payment.paid_at).toLocaleDateString('pt-BR') : '\u2014'}</p>
                                            </div>
                                        ) : (
                                            <PaymentForm studentId={student.id} pkg={pkg} />
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
