import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Badge from '@/Components/Badge';
import { Head, Link, router } from '@inertiajs/react';

function ClockIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 6v6l4.5 2.25M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
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

function CalendarIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
    );
}

export default function SchedulesIndex({ schedules, can }) {
    const list = schedules ?? [];

    function destroy(id) {
        if (confirm('Tem certeza que deseja excluir esta regra de agendamento?')) {
            router.delete(`/schedules/${id}`);
        }
    }

    return (
        <AppLayout title="Regras de Agenda">
            <Head title="Regras de Agenda" />

            <PageHeader
                title="Regras de Agenda"
                subtitle={`${list.length} regra${list.length !== 1 ? 's' : ''} cadastrada${list.length !== 1 ? 's' : ''}`}
            >
                <Link
                    href="/scheduled-lessons"
                    className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                >
                    <CalendarIcon className="w-4 h-4" />
                    Ver agendamentos
                </Link>
                {can?.create && (
                    <Link
                        href="/schedules/create"
                        className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                    >
                        <PlusIcon className="w-4 h-4" />
                        Nova regra
                    </Link>
                )}
            </PageHeader>

            {list.length === 0 ? (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 px-6 text-center">
                    <div className="w-16 h-16 rounded-2xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-4">
                        <ClockIcon className="w-8 h-8" />
                    </div>
                    <h3 className="text-base font-semibold text-gray-900 mb-1">Nenhuma regra cadastrada</h3>
                    <p className="text-sm text-gray-500 mb-6 max-w-sm mx-auto">
                        Crie uma regra recorrente para que aulas sejam agendadas automaticamente toda semana.
                    </p>
                    {can?.create && (
                        <Link
                            href="/schedules/create"
                            className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-colors"
                        >
                            <PlusIcon className="w-4 h-4" />
                            Criar primeira regra
                        </Link>
                    )}
                </div>
            ) : (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-100">
                        <thead className="bg-gray-50">
                            <tr>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Professor</th>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dia</th>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Horário</th>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duração</th>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-100">
                            {list.map((s) => (
                                <tr key={s.id} className="hover:bg-gray-50/60 transition-colors">
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {s.class?.name ?? '—'}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {s.class?.professor?.name ?? '—'}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        {s.weekday_name}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-700 tabular-nums">
                                        {s.start_time}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {s.duration_minutes} min
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        {s.active ? (
                                            <Badge label="Ativa" className="bg-emerald-50 text-emerald-700 border border-emerald-200" />
                                        ) : (
                                            <Badge label="Pausada" className="bg-gray-50 text-gray-600 border border-gray-200" />
                                        )}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <div className="flex items-center justify-end gap-3">
                                            {s.can?.update && (
                                                <Link
                                                    href={`/schedules/${s.id}/edit`}
                                                    className="text-indigo-600 hover:text-indigo-700 font-medium"
                                                >
                                                    Editar
                                                </Link>
                                            )}
                                            {s.can?.delete && (
                                                <button
                                                    type="button"
                                                    onClick={() => destroy(s.id)}
                                                    className="text-rose-500 hover:text-rose-700 font-medium"
                                                >
                                                    Excluir
                                                </button>
                                            )}
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
