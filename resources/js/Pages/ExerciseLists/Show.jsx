import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Avatar from '@/Components/Avatar';
import Badge from '@/Components/Badge';
import { Head, Link, useForm } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';

function formatDate(dateString) {
    if (!dateString) return null;
    return new Date(dateString).toLocaleDateString('pt-BR');
}

function formatDatetime(dateString) {
    if (!dateString) return null;
    return new Date(dateString).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

function ClipboardListIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
        </svg>
    );
}

export default function ExerciseListShow({ turmaClass, exerciseList, submissions, mySubmission, can }) {
    const { auth } = usePage().props;
    const isStudent = auth.user?.role === 'aluno';
    const deleteForm = useForm({});

    function handleDelete(e) {
        e.preventDefault();
        if (!confirm('Tem certeza que deseja remover esta lista?')) return;
        deleteForm.delete(`/classes/${turmaClass.id}/exercise-lists/${exerciseList.id}`);
    }

    const exercises = exerciseList.exercises ?? [];

    return (
        <AppLayout title={exerciseList.title}>
            <Head title={`${exerciseList.title} — ${turmaClass.name}`} />
            <PageHeader title={exerciseList.title} subtitle={turmaClass.name}>
                <Link
                    href={`/classes/${turmaClass.id}/exercise-lists`}
                    className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                >
                    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 19l-7-7 7-7" /></svg>
                    Voltar
                </Link>
                {can?.submit && !mySubmission?.completed && (
                    <Link
                        href={`/classes/${turmaClass.id}/exercise-lists/${exerciseList.id}/submit`}
                        className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                    >
                        <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        {mySubmission ? 'Reeditar Respostas' : 'Responder'}
                    </Link>
                )}
                {can?.delete && (
                    <form onSubmit={handleDelete}>
                        <button
                            type="submit"
                            disabled={deleteForm.processing}
                            className="inline-flex items-center gap-2 border border-rose-200 text-rose-600 hover:bg-rose-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors disabled:opacity-50"
                        >
                            <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"><path d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                            Remover
                        </button>
                    </form>
                )}
            </PageHeader>

            {/* Details card */}
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6 space-y-3">
                <div className="flex flex-wrap items-center gap-2 mb-1">
                    {exerciseList.is_overdue && (
                        <Badge label="Atrasada" className="bg-rose-50 text-rose-700 border border-rose-200" />
                    )}
                    {mySubmission?.completed && (
                        <Badge label="Concluida" className="bg-emerald-50 text-emerald-700 border border-emerald-200" />
                    )}
                </div>

                {exerciseList.description && (
                    <p className="text-sm text-gray-600">{exerciseList.description}</p>
                )}

                <div className="flex flex-wrap gap-4 text-xs text-gray-400">
                    <span>{exercises.length} {exercises.length === 1 ? 'exercicio' : 'exercicios'}</span>
                    {exerciseList.due_date && (
                        <span className={exerciseList.is_overdue ? 'text-rose-500 font-medium' : ''}>
                            Prazo: {formatDate(exerciseList.due_date)}
                        </span>
                    )}
                    {exerciseList.creator && (
                        <span>Criada por {exerciseList.creator.name}</span>
                    )}
                    {exerciseList.lesson && (
                        <span>Aula: {exerciseList.lesson.title}</span>
                    )}
                </div>
            </div>

            {/* Student view — exercises + own submission */}
            {isStudent && (
                <div className="space-y-4">
                    <h2 className="text-sm font-semibold text-gray-900">Exercicios</h2>
                    {exercises.map((exercise, index) => {
                        const answer = mySubmission?.answers?.find((a) => a.exercise_id === exercise.id);
                        return (
                            <div key={exercise.id} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                                <div className="flex items-start gap-3">
                                    <div className="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0 text-xs font-bold">
                                        {index + 1}
                                    </div>
                                    <div className="flex-1">
                                        <p className="text-sm font-medium text-gray-900 mb-3">{exercise.question}</p>
                                        {answer ? (
                                            <div className="mt-2 p-3 bg-gray-50 rounded-xl">
                                                {answer.answer_text && (
                                                    <p className="text-sm text-gray-700 whitespace-pre-wrap">{answer.answer_text}</p>
                                                )}
                                                {answer.file_url && (
                                                    <a
                                                        href={answer.file_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-700 font-medium mt-2"
                                                    >
                                                        <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                                        Ver arquivo enviado
                                                    </a>
                                                )}
                                            </div>
                                        ) : (
                                            <p className="text-xs text-gray-400 italic">Sem resposta enviada.</p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })}

                    {!mySubmission?.completed && (
                        <div className="text-center pt-2">
                            <Link
                                href={`/classes/${turmaClass.id}/exercise-lists/${exerciseList.id}/submit`}
                                className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-colors"
                            >
                                {mySubmission ? 'Reeditar e Enviar Respostas' : 'Responder Exercicios'}
                            </Link>
                        </div>
                    )}
                </div>
            )}

            {/* Professor/admin view — submissions table */}
            {!isStudent && (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-gray-900">Entregas dos Alunos</h2>
                        <span className="text-xs text-gray-400">
                            {(submissions ?? []).length} {(submissions ?? []).length === 1 ? 'entrega' : 'entregas'}
                        </span>
                    </div>

                    {(submissions ?? []).length === 0 ? (
                        <div className="py-16 text-center">
                            <div className="w-12 h-12 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-3">
                                <ClipboardListIcon className="w-6 h-6" />
                            </div>
                            <p className="text-sm text-gray-500">Nenhuma entrega ainda.</p>
                        </div>
                    ) : (
                        <>
                            <ul className="divide-y divide-gray-100">
                                {(submissions ?? []).map((sub) => (
                                    <li key={sub.id} className="px-6 py-4 flex items-center gap-4">
                                        <Avatar name={sub.student?.name ?? '?'} size="md" />
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-900">{sub.student?.name}</p>
                                            <p className="text-xs text-gray-400 mt-0.5">
                                                {sub.submitted_at
                                                    ? `Enviado em ${formatDatetime(sub.submitted_at)}`
                                                    : 'Rascunho'}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            {sub.completed ? (
                                                <Badge label="Concluida" className="bg-emerald-50 text-emerald-700 border border-emerald-200" />
                                            ) : (
                                                <Badge label="Incompleta" className="bg-amber-50 text-amber-700 border border-amber-200" />
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>

                            {/* Exercises preview for professor */}
                            <div className="px-6 py-4 border-t border-gray-100">
                                <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Exercicios</h3>
                                <ol className="space-y-2 list-decimal list-inside">
                                    {exercises.map((ex) => (
                                        <li key={ex.id} className="text-sm text-gray-700">
                                            {ex.question}
                                            <span className="ml-2 text-xs text-gray-400">({ex.type === 'file' ? 'arquivo' : 'texto'})</span>
                                        </li>
                                    ))}
                                </ol>
                            </div>
                        </>
                    )}
                </div>
            )}
        </AppLayout>
    );
}
