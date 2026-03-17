import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, Link, useForm } from '@inertiajs/react';

function formatDate(dateString) {
    if (!dateString) return null;
    return new Date(dateString).toLocaleDateString('pt-BR');
}

export default function ExerciseListSubmit({ turmaClass, exerciseList, existingSubmission }) {
    const exercises = exerciseList.exercises ?? [];

    // Build initial answers map from existing submission
    const buildInitialAnswers = () => {
        const map = {};
        exercises.forEach((ex) => {
            const existing = existingSubmission?.answers?.find((a) => a.exercise_id === ex.id);
            map[ex.id] = {
                answer_text: existing?.answer_text ?? '',
                file: null,
            };
        });
        return map;
    };

    const { data, setData, post, processing, errors } = useForm({
        answers: buildInitialAnswers(),
    });

    function updateAnswer(exerciseId, field, value) {
        setData('answers', {
            ...data.answers,
            [exerciseId]: {
                ...data.answers[exerciseId],
                [field]: value,
            },
        });
    }

    function submit(e) {
        e.preventDefault();
        post(`/classes/${turmaClass.id}/exercise-lists/${exerciseList.id}/submit`, {
            forceFormData: true,
        });
    }

    return (
        <AppLayout title="Responder Exercicios">
            <Head title={`Responder — ${exerciseList.title}`} />
            <PageHeader title={exerciseList.title} subtitle={turmaClass.name}>
                <Link
                    href={`/classes/${turmaClass.id}/exercise-lists/${exerciseList.id}`}
                    className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                >
                    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 19l-7-7 7-7" /></svg>
                    Voltar
                </Link>
            </PageHeader>

            {/* List info */}
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
                <div className="flex flex-wrap gap-4 text-xs text-gray-400">
                    <span>{exercises.length} {exercises.length === 1 ? 'exercicio' : 'exercicios'}</span>
                    {exerciseList.due_date && (
                        <span className={exerciseList.is_overdue ? 'text-rose-500 font-medium' : ''}>
                            Prazo: {formatDate(exerciseList.due_date)}
                            {exerciseList.is_overdue && ' (atrasado)'}
                        </span>
                    )}
                    {exerciseList.description && (
                        <span className="block w-full text-gray-600 text-sm mt-1">{exerciseList.description}</span>
                    )}
                </div>
            </div>

            <form onSubmit={submit} className="space-y-4 max-w-3xl">
                {exercises.map((exercise, index) => {
                    const existingAnswer = existingSubmission?.answers?.find((a) => a.exercise_id === exercise.id);
                    const answerData = data.answers[exercise.id] ?? { answer_text: '', file: null };

                    return (
                        <div key={exercise.id} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                            <div className="flex items-start gap-3">
                                <div className="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0 text-xs font-bold">
                                    {index + 1}
                                </div>
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-gray-900 mb-3">{exercise.question}</p>

                                    {/* Text answer */}
                                    <div className="space-y-3">
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 mb-1">
                                                Sua resposta
                                            </label>
                                            <textarea
                                                value={answerData.answer_text}
                                                onChange={(e) => updateAnswer(exercise.id, 'answer_text', e.target.value)}
                                                rows={4}
                                                className="block w-full border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"
                                                placeholder="Escreva sua resposta aqui..."
                                            />
                                            <InputError message={errors[`answers.${exercise.id}.answer_text`]} className="mt-1" />
                                        </div>

                                        {/* File upload */}
                                        <div>
                                            <label className="block text-xs font-medium text-gray-500 mb-1">
                                                Arquivo (opcional)
                                                {exercise.type === 'file' && (
                                                    <span className="ml-1 text-indigo-600">(recomendado)</span>
                                                )}
                                            </label>
                                            {existingAnswer?.file_url && !answerData.file && (
                                                <div className="mb-2">
                                                    <a
                                                        href={existingAnswer.file_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1.5 text-xs text-indigo-600 hover:text-indigo-700"
                                                    >
                                                        <svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                                        Arquivo atual (clique para ver)
                                                    </a>
                                                    <span className="text-xs text-gray-400 ml-2">— envie um novo para substituir</span>
                                                </div>
                                            )}
                                            <input
                                                type="file"
                                                accept=".pdf,.doc,.docx,.jpg,.png"
                                                onChange={(e) => updateAnswer(exercise.id, 'file', e.target.files?.[0] ?? null)}
                                                className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 cursor-pointer"
                                            />
                                            <p className="text-xs text-gray-400 mt-1">PDF, DOC, DOCX, JPG, PNG — max 10 MB</p>
                                            <InputError message={errors[`answers.${exercise.id}.file`]} className="mt-1" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    );
                })}

                {exercises.length === 0 && (
                    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm py-16 text-center">
                        <p className="text-sm text-gray-500">Esta lista nao possui exercicios.</p>
                    </div>
                )}

                {exercises.length > 0 && (
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link
                            href={`/classes/${turmaClass.id}/exercise-lists/${exerciseList.id}`}
                            className="border border-gray-300 hover:bg-gray-50 px-5 py-2.5 rounded-xl text-sm font-medium transition-colors"
                        >
                            Cancelar
                        </Link>
                        <PrimaryButton disabled={processing}>
                            {processing ? 'Enviando...' : 'Enviar Respostas'}
                        </PrimaryButton>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
