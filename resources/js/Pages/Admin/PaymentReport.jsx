import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import StatsCard from '@/Components/StatsCard';
import Badge from '@/Components/Badge';
import { Head } from '@inertiajs/react';

const formatBRL = (value) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

const methodLabels = { pix: 'PIX', cash: 'Dinheiro', card: 'Cartao', transfer: 'Transferencia', other: 'Outro' };

const methodColors = {
    pix: 'bg-indigo-100 text-indigo-700 border border-indigo-200',
    cash: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    card: 'bg-sky-100 text-sky-700 border border-sky-200',
    transfer: 'bg-amber-100 text-amber-700 border border-amber-200',
    other: 'bg-gray-100 text-gray-700 border border-gray-200',
};

function CurrencyIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
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

function UsersIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round">
            <path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
    );
}

/* Revenue bar chart using plain divs */
function RevenueChart({ data }) {
    if (!data || data.length === 0) {
        return (
            <div className="text-center text-sm text-gray-500 py-8">
                Nenhum dado de receita disponivel.
            </div>
        );
    }

    const maxTotal = Math.max(...data.map((d) => d.total));

    return (
        <div className="flex items-end gap-2 h-48 px-2">
            {data.map((item, idx) => {
                const heightPct = maxTotal > 0 ? (item.total / maxTotal) * 100 : 0;
                return (
                    <div key={idx} className="flex-1 flex flex-col items-center justify-end h-full min-w-0">
                        <span className="text-xs font-medium text-gray-700 mb-1 truncate w-full text-center">
                            {formatBRL(item.total)}
                        </span>
                        <div
                            className="w-full bg-indigo-500 rounded-t-lg transition-all duration-300 min-h-[4px]"
                            style={{ height: `${Math.max(heightPct, 2)}%` }}
                        />
                        <span className="text-xs text-gray-500 mt-2 truncate w-full text-center">
                            {item.month}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

export default function PaymentReport({
    total_revenue,
    revenue_by_month,
    by_method,
    paid_packages_count,
    unpaid_packages_count,
    total_students,
    recent_payments,
}) {
    return (
        <AppLayout title="Relatorio Financeiro">
            <Head title="Relatorio Financeiro" />

            <PageHeader title="Relatorio Financeiro" subtitle="Visao geral das receitas e pagamentos" />

            {/* Stats cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                <StatsCard
                    title="Total Receita"
                    value={formatBRL(total_revenue ?? 0)}
                    icon={<CurrencyIcon className="w-5 h-5" />}
                    iconBg="bg-emerald-50"
                    iconColor="text-emerald-600"
                />
                <StatsCard
                    title="Pacotes Pagos"
                    value={paid_packages_count ?? 0}
                    icon={<CheckCircleIcon className="w-5 h-5" />}
                    iconBg="bg-indigo-50"
                    iconColor="text-indigo-600"
                />
                <StatsCard
                    title="Pacotes Nao Pagos"
                    value={unpaid_packages_count ?? 0}
                    icon={<XCircleIcon className="w-5 h-5" />}
                    iconBg="bg-rose-50"
                    iconColor="text-rose-600"
                />
                <StatsCard
                    title="Total Alunos"
                    value={total_students ?? 0}
                    icon={<UsersIcon className="w-5 h-5" />}
                    iconBg="bg-sky-50"
                    iconColor="text-sky-600"
                />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                {/* Revenue by month */}
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h3 className="text-sm font-semibold text-gray-900 mb-6">Receita por Mes</h3>
                    <RevenueChart data={revenue_by_month} />
                </div>

                {/* Payment method breakdown */}
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <h3 className="text-sm font-semibold text-gray-900 mb-6">Por Metodo de Pagamento</h3>
                    {by_method && by_method.length > 0 ? (
                        <div className="space-y-4">
                            {by_method.map((item) => {
                                const maxMethodTotal = Math.max(...by_method.map((m) => m.total));
                                const widthPct = maxMethodTotal > 0 ? (item.total / maxMethodTotal) * 100 : 0;
                                return (
                                    <div key={item.method}>
                                        <div className="flex items-center justify-between mb-1.5">
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    label={methodLabels[item.method] ?? item.method}
                                                    className={methodColors[item.method] ?? methodColors.other}
                                                />
                                                <span className="text-xs text-gray-500">{item.count} pagamentos</span>
                                            </div>
                                            <span className="text-sm font-semibold text-gray-900">{formatBRL(item.total)}</span>
                                        </div>
                                        <div className="w-full bg-gray-100 rounded-full h-2.5">
                                            <div
                                                className="bg-indigo-500 h-2.5 rounded-full transition-all duration-300"
                                                style={{ width: `${Math.max(widthPct, 2)}%` }}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="text-center text-sm text-gray-500 py-8">
                            Nenhum pagamento registrado.
                        </div>
                    )}
                </div>
            </div>

            {/* Recent payments table */}
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-100">
                    <h3 className="text-sm font-semibold text-gray-900">Pagamentos Recentes</h3>
                </div>
                {recent_payments && recent_payments.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100">
                                    <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                                    <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                                    <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Metodo</th>
                                    <th className="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {recent_payments.map((payment) => (
                                    <tr key={payment.id} className="hover:bg-gray-50/50 transition-colors">
                                        <td className="px-6 py-4 font-medium text-gray-900">{payment.student_name}</td>
                                        <td className="px-6 py-4 text-gray-700">{formatBRL(payment.amount)}</td>
                                        <td className="px-6 py-4">
                                            <Badge
                                                label={methodLabels[payment.method] ?? payment.method}
                                                className={methodColors[payment.method] ?? methodColors.other}
                                            />
                                        </td>
                                        <td className="px-6 py-4 text-gray-500">
                                            {payment.paid_at ? new Date(payment.paid_at).toLocaleDateString('pt-BR') : '\u2014'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="px-6 py-12 text-center text-sm text-gray-500">
                        Nenhum pagamento registrado.
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
