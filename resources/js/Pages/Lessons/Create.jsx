import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Badge from '@/Components/Badge';
import { Head, useForm, Link } from '@inertiajs/react';

function ExclamationIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
    );
}

export default function LessonsCreate({ turmaClass, students }) {
    const { data, setData, post, processing, errors } = useForm({
        student_id: '',
        title: '',
        notes: '',
        conducted_at: new Date().toISOString().split('T')[0],
    });

    const selectedStudent = students?.find((s) => String(s.id) === String(data.student_id));
    const hasActivePackage = selectedStudent?.active_package;
    const remaining = hasActivePackage?.remaining ?? 0;

    function submit(e) {
        e.preventDefault();
        post(`/classes/${turmaClass.id}/lessons`);
    }

    return (
        <AppLayout title="Registrar Aula">
            <Head title="Registrar Aula" />
            <PageHeader title="Registrar Aula" subtitle={turmaClass.name}>
                <Link href={`/classes/${turmaClass.id}`} className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 19l-7-7 7-7" /></svg>
                    Voltar
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 max-w-2xl">
                <div className="space-y-6">
                    {/* Student Select */}
                    <div>
                        <label htmlFor="student_id" className="block text-sm font-medium text-gray-700 mb-1.5">Aluno</label>
                        <select
                            id="student_id"
                            value={data.student_id}
                            onChange={(e) => setData('student_id', e.target.value)}
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow bg-white"
                        >
                            <option value="">Selecione um aluno</option>
                            {students?.map((s) => (
                                <option key={s.id} value={s.id} disabled={!s.active_package}>
                                    {s.name} {s.active_package ? `(${s.active_package.remaining} aulas restantes)` : '(sem pacote ativo)'}
                                </option>
                            ))}
                        </select>
                        {errors.student_id && <p className="mt-1.5 text-xs text-rose-600">{errors.student_id}</p>}

                        {/* Package balance indicator */}
                        {selectedStudent && hasActivePackage && (
                            <div className="mt-3 p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-3">
                                <Badge
                                    label={`${remaining} aulas restantes`}
                                    className="bg-emerald-100 text-emerald-700 border border-emerald-300"
                                />
                                <span className="text-xs text-emerald-700">Pacote ativo com {hasActivePackage.total_lessons} aulas no total</span>
                            </div>
                        )}

                        {/* No package warning */}
                        {selectedStudent && !hasActivePackage && (
                            <div className="mt-3 p-3 bg-rose-50 border border-rose-200 rounded-xl flex items-center gap-3">
                                <ExclamationIcon className="w-5 h-5 text-rose-500 flex-shrink-0" />
                                <div>
                                    <p className="text-sm font-medium text-rose-800">Aluno sem pacote ativo</p>
                                    <p className="text-xs text-rose-600 mt-0.5">Este aluno nao possui um pacote de aulas ativo. A aula nao podera ser registrada.</p>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Title */}
                    <div>
                        <label htmlFor="title" className="block text-sm font-medium text-gray-700 mb-1.5">Titulo da Aula</label>
                        <input
                            id="title"
                            type="text"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder="Ex: Aula de conversacao - tema viagem"
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow"
                        />
                        {errors.title && <p className="mt-1.5 text-xs text-rose-600">{errors.title}</p>}
                    </div>

                    {/* Date */}
                    <div>
                        <label htmlFor="conducted_at" className="block text-sm font-medium text-gray-700 mb-1.5">Data da Aula</label>
                        <input
                            id="conducted_at"
                            type="date"
                            value={data.conducted_at}
                            onChange={(e) => setData('conducted_at', e.target.value)}
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow"
                        />
                        {errors.conducted_at && <p className="mt-1.5 text-xs text-rose-600">{errors.conducted_at}</p>}
                    </div>

                    {/* Notes */}
                    <div>
                        <label htmlFor="notes" className="block text-sm font-medium text-gray-700 mb-1.5">
                            Observacoes <span className="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={4}
                            placeholder="Notas sobre o conteudo abordado..."
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow resize-none"
                        />
                    </div>
                </div>

                <div className="flex items-center gap-3 mt-8 pt-6 border-t border-gray-100">
                    <button
                        type="submit"
                        disabled={processing || !data.student_id || !hasActivePackage}
                        className="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium disabled:opacity-50 transition-colors"
                    >
                        {processing ? 'Registrando...' : 'Registrar Aula'}
                    </button>
                    <Link href={`/classes/${turmaClass.id}`} className="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                        Cancelar
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
