import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Badge from '@/Components/Badge';
import Modal from '@/Components/Modal';
import { Head, Link, router, useForm } from '@inertiajs/react';

function CalendarIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
    );
}

const WEEKDAY_NAMES = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

function formatScheduledAt(iso) {
    if (!iso) return { date: '—', time: '', weekday: '' };
    const d = new Date(iso);
    return {
        date: d.toLocaleDateString('pt-BR'),
        time: d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
        weekday: WEEKDAY_NAMES[d.getDay()],
    };
}

function statusBadge(status) {
    if (status === 'confirmed') {
        return <Badge label="Confirmada" className="bg-emerald-50 text-emerald-700 border border-emerald-200" />;
    }
    if (status === 'cancelled') {
        return <Badge label="Cancelada" className="bg-rose-50 text-rose-700 border border-rose-200" />;
    }
    return <Badge label="Agendada" className="bg-sky-50 text-sky-700 border border-sky-200" />;
}

function ConfirmModal({ scheduledLesson, onClose }) {
    const { data, setData, post, processing, errors, reset } = useForm({ notes: '' });

    function submit(e) {
        e.preventDefault();
        post(`/scheduled-lessons/${scheduledLesson.id}/confirm`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    }

    const { date, time } = formatScheduledAt(scheduledLesson.scheduled_at);

    return (
        <Modal show={!!scheduledLesson} onClose={onClose} maxWidth="lg">
            <form onSubmit={submit} className="p-6">
                <h3 className="text-lg font-semibold text-gray-900">Confirmar aula</h3>
                <p className="mt-1 text-sm text-gray-500">
                    {scheduledLesson.class?.name} &middot; {date} às {time}
                </p>
                <p className="mt-3 text-sm text-gray-600">
                    Ao confirmar, será registrada uma aula para cada aluno matriculado e o pacote
                    de cada um será debitado em uma unidade.
                </p>

                <div className="mt-5">
                    <label htmlFor="confirm-notes" className="block text-sm font-medium text-gray-700 mb-1.5">
                        Observações <span className="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <textarea
                        id="confirm-notes"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        rows={3}
                        maxLength={500}
                        placeholder="Anotações da aula (visíveis na ficha de cada aluno)"
                        className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow resize-none"
                    />
                    {errors.notes && <p className="mt-1.5 text-xs text-rose-600">{errors.notes}</p>}
                </div>

                <div className="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-sm text-gray-500 hover:text-gray-700 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium disabled:opacity-50 transition-colors"
                    >
                        {processing ? 'Confirmando...' : 'Confirmar aula'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

function CancelModal({ scheduledLesson, onClose }) {
    const { data, setData, post, processing, errors, reset } = useForm({ reason: '' });

    function submit(e) {
        e.preventDefault();
        post(`/scheduled-lessons/${scheduledLesson.id}/cancel`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    }

    const { date, time } = formatScheduledAt(scheduledLesson.scheduled_at);

    return (
        <Modal show={!!scheduledLesson} onClose={onClose} maxWidth="lg">
            <form onSubmit={submit} className="p-6">
                <h3 className="text-lg font-semibold text-gray-900">Cancelar agendamento</h3>
                <p className="mt-1 text-sm text-gray-500">
                    {scheduledLesson.class?.name} &middot; {date} às {time}
                </p>
                <p className="mt-3 text-sm text-gray-600">
                    O cancelamento não debita pacotes. Você pode opcionalmente registrar o motivo.
                </p>

                <div className="mt-5">
                    <label htmlFor="cancel-reason" className="block text-sm font-medium text-gray-700 mb-1.5">
                        Motivo <span className="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <textarea
                        id="cancel-reason"
                        value={data.reason}
                        onChange={(e) => setData('reason', e.target.value)}
                        rows={3}
                        maxLength={500}
                        placeholder="Ex: feriado, professor de licença..."
                        className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow resize-none"
                    />
                    {errors.reason && <p className="mt-1.5 text-xs text-rose-600">{errors.reason}</p>}
                </div>

                <div className="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-sm text-gray-500 hover:text-gray-700 transition-colors"
                    >
                        Voltar
                    </button>
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-rose-600 hover:bg-rose-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium disabled:opacity-50 transition-colors"
                    >
                        {processing ? 'Cancelando...' : 'Cancelar agendamento'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

export default function ScheduledLessonsIndex({ scheduledLessons, filters }) {
    const [confirmTarget, setConfirmTarget] = useState(null);
    const [cancelTarget, setCancelTarget] = useState(null);

    const list = scheduledLessons ?? [];
    const period = filters?.period ?? 'upcoming';
    const status = filters?.status ?? 'all';

    function applyFilter(name, value) {
        router.get('/scheduled-lessons', { period, status, [name]: value }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    return (
        <AppLayout title="Agendamentos">
            <Head title="Agendamentos" />

            <PageHeader
                title="Agendamentos"
                subtitle={`${list.length} aula${list.length !== 1 ? 's' : ''} no filtro atual`}
            >
                <Link
                    href="/schedules"
                    className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                >
                    Regras de Agenda
                </Link>
            </PageHeader>

            {/* Filters */}
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6 flex flex-wrap items-center gap-3">
                <div className="flex items-center gap-2">
                    <label htmlFor="period-filter" className="text-xs font-medium text-gray-500 uppercase tracking-wider">Período</label>
                    <select
                        id="period-filter"
                        value={period}
                        onChange={(e) => applyFilter('period', e.target.value)}
                        className="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option value="upcoming">Próximas</option>
                        <option value="past">Passadas</option>
                        <option value="all">Todas</option>
                    </select>
                </div>
                <div className="flex items-center gap-2">
                    <label htmlFor="status-filter" className="text-xs font-medium text-gray-500 uppercase tracking-wider">Status</label>
                    <select
                        id="status-filter"
                        value={status}
                        onChange={(e) => applyFilter('status', e.target.value)}
                        className="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option value="all">Todos</option>
                        <option value="scheduled">Agendadas</option>
                        <option value="confirmed">Confirmadas</option>
                        <option value="cancelled">Canceladas</option>
                    </select>
                </div>
            </div>

            {list.length === 0 ? (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
                    <div className="w-16 h-16 rounded-2xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-4">
                        <CalendarIcon className="w-8 h-8" />
                    </div>
                    <h3 className="text-base font-semibold text-gray-900 mb-1">Nenhum agendamento</h3>
                    <p className="text-sm text-gray-500">
                        Não há aulas agendadas para o filtro selecionado.
                    </p>
                </div>
            ) : (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <ul className="divide-y divide-gray-100">
                        {list.map((sl) => {
                            const { date, time, weekday } = formatScheduledAt(sl.scheduled_at);
                            const isScheduled = sl.status === 'scheduled';

                            return (
                                <li key={sl.id} className="px-6 py-4 flex items-center gap-4 hover:bg-gray-50/50 transition-colors">
                                    <div className="w-14 h-14 rounded-xl bg-indigo-50 text-indigo-700 flex flex-col items-center justify-center flex-shrink-0">
                                        <span className="text-[10px] uppercase tracking-wider">{weekday.slice(0, 3)}</span>
                                        <span className="text-base font-bold leading-none">{date.split('/')[0]}</span>
                                        <span className="text-[10px]">{date.split('/').slice(1).join('/')}</span>
                                    </div>

                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">
                                            {sl.class?.name ?? 'Turma desconhecida'}
                                        </p>
                                        <p className="text-xs text-gray-500 mt-0.5">
                                            {time}
                                            {sl.duration_minutes && (<> &middot; {sl.duration_minutes} min</>)}
                                            {sl.class?.professor?.name && (<> &middot; Prof. {sl.class.professor.name}</>)}
                                        </p>
                                        {sl.cancelled_reason && (
                                            <p className="text-xs text-rose-600 mt-1 italic">Motivo: {sl.cancelled_reason}</p>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-2 flex-shrink-0">
                                        {statusBadge(sl.status)}

                                        {isScheduled && sl.can?.confirm && (
                                            <button
                                                type="button"
                                                onClick={() => setConfirmTarget(sl)}
                                                className="text-xs font-medium px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors"
                                            >
                                                Confirmar
                                            </button>
                                        )}

                                        {isScheduled && sl.can?.cancel && (
                                            <button
                                                type="button"
                                                onClick={() => setCancelTarget(sl)}
                                                className="text-xs font-medium px-3 py-1.5 rounded-lg border border-rose-300 text-rose-600 hover:bg-rose-50 transition-colors"
                                            >
                                                Cancelar
                                            </button>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}

            {confirmTarget && (
                <ConfirmModal scheduledLesson={confirmTarget} onClose={() => setConfirmTarget(null)} />
            )}
            {cancelTarget && (
                <CancelModal scheduledLesson={cancelTarget} onClose={() => setCancelTarget(null)} />
            )}
        </AppLayout>
    );
}
