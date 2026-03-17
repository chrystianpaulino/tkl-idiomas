import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, Link, useForm } from '@inertiajs/react';

function PlusIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 4.5v15m7.5-7.5h-15" />
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

export default function ExerciseListCreate({ turmaClass, lessons }) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        due_date: '',
        lesson_id: '',
        exercises: [{ question: '', type: 'text' }],
    });

    function addExercise() {
        setData('exercises', [...data.exercises, { question: '', type: 'text' }]);
    }

    function removeExercise(index) {
        if (data.exercises.length <= 1) return;
        setData('exercises', data.exercises.filter((_, i) => i !== index));
    }

    function updateExercise(index, field, value) {
        const updated = data.exercises.map((ex, i) =>
            i === index ? { ...ex, [field]: value } : ex
        );
        setData('exercises', updated);
    }

    function submit(e) {
        e.preventDefault();
        post(`/classes/${turmaClass.id}/exercise-lists`);
    }

    return (
        <AppLayout title="Nova Lista de Exercicios">
            <Head title={`Nova Lista — ${turmaClass.name}`} />
            <PageHeader
                title="Nova Lista de Exercicios"
                subtitle={turmaClass.name}
            >
                <Link
                    href={`/classes/${turmaClass.id}/exercise-lists`}
                    className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                >
                    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 19l-7-7 7-7" /></svg>
                    Voltar
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="space-y-6 max-w-3xl">
                {/* Basic info */}
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
                    <h2 className="text-sm font-semibold text-gray-900">Informacoes da Lista</h2>

                    <div>
                        <InputLabel htmlFor="title" value="Titulo *" />
                        <TextInput
                            id="title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            className="mt-1 block w-full"
                            placeholder="Ex: Lista de exercicios — Capitulo 3"
                            required
                        />
                        <InputError message={errors.title} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="description" value="Descricao (opcional)" />
                        <textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            rows={3}
                            className="mt-1 block w-full border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"
                            placeholder="Instrucoes ou contexto sobre a lista..."
                        />
                        <InputError message={errors.description} className="mt-1" />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel htmlFor="due_date" value="Prazo de entrega (opcional)" />
                            <TextInput
                                id="due_date"
                                type="date"
                                value={data.due_date}
                                onChange={(e) => setData('due_date', e.target.value)}
                                className="mt-1 block w-full"
                            />
                            <InputError message={errors.due_date} className="mt-1" />
                        </div>

                        {lessons.length > 0 && (
                            <div>
                                <InputLabel htmlFor="lesson_id" value="Associar a uma aula (opcional)" />
                                <select
                                    id="lesson_id"
                                    value={data.lesson_id}
                                    onChange={(e) => setData('lesson_id', e.target.value)}
                                    className="mt-1 block w-full border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                                >
                                    <option value="">Nenhuma aula especifica</option>
                                    {lessons.map((l) => (
                                        <option key={l.id} value={l.id}>{l.title}</option>
                                    ))}
                                </select>
                                <InputError message={errors.lesson_id} className="mt-1" />
                            </div>
                        )}
                    </div>
                </div>

                {/* Exercises */}
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-gray-900">Exercicios</h2>
                        <span className="text-xs text-gray-400">{data.exercises.length} {data.exercises.length === 1 ? 'exercicio' : 'exercicios'}</span>
                    </div>

                    <InputError message={errors.exercises} className="-mt-2" />

                    <div className="space-y-3">
                        {data.exercises.map((exercise, index) => (
                            <div key={index} className="border border-gray-200 rounded-xl p-4 space-y-3">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                        Exercicio {index + 1}
                                    </span>
                                    {data.exercises.length > 1 && (
                                        <button
                                            type="button"
                                            onClick={() => removeExercise(index)}
                                            className="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                        >
                                            <TrashIcon className="w-4 h-4" />
                                        </button>
                                    )}
                                </div>

                                <div>
                                    <InputLabel htmlFor={`exercise-${index}-question`} value="Enunciado *" />
                                    <textarea
                                        id={`exercise-${index}-question`}
                                        value={exercise.question}
                                        onChange={(e) => updateExercise(index, 'question', e.target.value)}
                                        rows={3}
                                        className="mt-1 block w-full border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"
                                        placeholder="Escreva a pergunta ou exercicio..."
                                        required
                                    />
                                    <InputError message={errors[`exercises.${index}.question`]} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor={`exercise-${index}-type`} value="Tipo de resposta" />
                                    <select
                                        id={`exercise-${index}-type`}
                                        value={exercise.type}
                                        onChange={(e) => updateExercise(index, 'type', e.target.value)}
                                        className="mt-1 block w-full border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                                    >
                                        <option value="text">Texto (resposta escrita)</option>
                                        <option value="file">Arquivo (upload de documento)</option>
                                    </select>
                                    <InputError message={errors[`exercises.${index}.type`]} className="mt-1" />
                                </div>
                            </div>
                        ))}
                    </div>

                    <button
                        type="button"
                        onClick={addExercise}
                        className="inline-flex items-center gap-2 border border-dashed border-indigo-300 hover:border-indigo-500 hover:bg-indigo-50 text-indigo-600 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors w-full justify-center"
                    >
                        <PlusIcon className="w-4 h-4" />
                        Adicionar Exercicio
                    </button>
                </div>

                {/* Submit */}
                <div className="flex items-center justify-end gap-3">
                    <Link
                        href={`/classes/${turmaClass.id}/exercise-lists`}
                        className="border border-gray-300 hover:bg-gray-50 px-5 py-2.5 rounded-xl text-sm font-medium transition-colors"
                    >
                        Cancelar
                    </Link>
                    <PrimaryButton disabled={processing}>
                        {processing ? 'Salvando...' : 'Criar Lista'}
                    </PrimaryButton>
                </div>
            </form>
        </AppLayout>
    );
}
