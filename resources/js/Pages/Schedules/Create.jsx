import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import { Head, Link, useForm } from '@inertiajs/react';

const WEEKDAYS = [
    { value: 0, label: 'Domingo' },
    { value: 1, label: 'Segunda-feira' },
    { value: 2, label: 'Terça-feira' },
    { value: 3, label: 'Quarta-feira' },
    { value: 4, label: 'Quinta-feira' },
    { value: 5, label: 'Sexta-feira' },
    { value: 6, label: 'Sábado' },
];

export default function SchedulesCreate({ classes }) {
    const { data, setData, post, processing, errors } = useForm({
        class_id: '',
        weekday: 1,
        start_time: '19:00',
        duration_minutes: 60,
        active: true,
    });

    function submit(e) {
        e.preventDefault();
        post('/schedules');
    }

    return (
        <AppLayout title="Nova Regra de Agenda">
            <Head title="Nova Regra de Agenda" />

            <PageHeader title="Nova Regra de Agenda" subtitle="Defina quando uma turma terá aulas recorrentes">
                <Link
                    href="/schedules"
                    className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                >
                    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M15 19l-7-7 7-7" />
                    </svg>
                    Voltar
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 max-w-3xl">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="md:col-span-2">
                        <label htmlFor="class_id" className="block text-sm font-medium text-gray-700 mb-1.5">
                            Turma
                        </label>
                        <select
                            id="class_id"
                            value={data.class_id}
                            onChange={(e) => setData('class_id', e.target.value)}
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow bg-white"
                        >
                            <option value="">Selecione uma turma</option>
                            {classes?.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                    {c.professor?.name ? ` — Prof. ${c.professor.name}` : ''}
                                </option>
                            ))}
                        </select>
                        {errors.class_id && <p className="mt-1.5 text-xs text-rose-600">{errors.class_id}</p>}
                    </div>

                    <div>
                        <label htmlFor="weekday" className="block text-sm font-medium text-gray-700 mb-1.5">Dia da semana</label>
                        <select
                            id="weekday"
                            value={data.weekday}
                            onChange={(e) => setData('weekday', Number(e.target.value))}
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow bg-white"
                        >
                            {WEEKDAYS.map((w) => (
                                <option key={w.value} value={w.value}>{w.label}</option>
                            ))}
                        </select>
                        {errors.weekday && <p className="mt-1.5 text-xs text-rose-600">{errors.weekday}</p>}
                    </div>

                    <div>
                        <label htmlFor="start_time" className="block text-sm font-medium text-gray-700 mb-1.5">Horário de início</label>
                        <input
                            id="start_time"
                            type="time"
                            value={data.start_time}
                            onChange={(e) => setData('start_time', e.target.value)}
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow"
                        />
                        {errors.start_time && <p className="mt-1.5 text-xs text-rose-600">{errors.start_time}</p>}
                    </div>

                    <div>
                        <label htmlFor="duration_minutes" className="block text-sm font-medium text-gray-700 mb-1.5">Duração (minutos)</label>
                        <input
                            id="duration_minutes"
                            type="number"
                            min={15}
                            max={240}
                            step={5}
                            value={data.duration_minutes}
                            onChange={(e) => setData('duration_minutes', Number(e.target.value))}
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow"
                        />
                        {errors.duration_minutes && <p className="mt-1.5 text-xs text-rose-600">{errors.duration_minutes}</p>}
                    </div>

                    <div className="flex items-center">
                        <label className="inline-flex items-center gap-2 text-sm text-gray-700 mt-6">
                            <input
                                type="checkbox"
                                checked={!!data.active}
                                onChange={(e) => setData('active', e.target.checked)}
                                className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            Ativa (gera aulas automaticamente)
                        </label>
                        {errors.active && <p className="mt-1.5 text-xs text-rose-600">{errors.active}</p>}
                    </div>
                </div>

                <div className="flex items-center gap-3 mt-8 pt-6 border-t border-gray-100">
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium disabled:opacity-50 transition-colors"
                    >
                        {processing ? 'Salvando...' : 'Criar regra'}
                    </button>
                    <Link href="/schedules" className="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                        Cancelar
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
