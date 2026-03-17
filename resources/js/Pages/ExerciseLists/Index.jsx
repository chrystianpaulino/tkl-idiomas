import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Badge from '@/Components/Badge';
import { Head, Link } from '@inertiajs/react';

function ClipboardIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
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

function formatDate(dateString) {
    if (!dateString) return null;
    return new Date(dateString).toLocaleDateString('pt-BR');
}

export default function ExerciseListsIndex({ turmaClass, exerciseLists = [], can }) {
    return (
        <AppLayout title="Listas de Exercicios">
            <Head title={`Exercicios - ${turmaClass.name}`} />
            <PageHeader title="Listas de Exercicios" subtitle={turmaClass.name}>
                <Link href={`/classes/${turmaClass.id}`} className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 19l-7-7 7-7" /></svg>
                    Voltar
                </Link>
                {can?.create && (
                    <Link href={`/classes/${turmaClass.id}/exercise-lists/create`} className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                        <PlusIcon className="w-4 h-4" />
                        Nova Lista
                    </Link>
                )}
            </PageHeader>

            {exerciseLists.length === 0 ? (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm py-16 text-center">
                    <div className="w-12 h-12 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-3">
                        <ClipboardIcon className="w-6 h-6" />
                    </div>
                    <p className="text-sm text-gray-500 mb-4">Nenhuma lista de exercicios.</p>
                    {can?.create && (
                        <Link href={`/classes/${turmaClass.id}/exercise-lists/create`} className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                            Criar Lista
                        </Link>
                    )}
                </div>
            ) : (
                <div className="space-y-4">
                    {exerciseLists.map((list) => (
                        <Link
                            key={list.id}
                            href={`/classes/${turmaClass.id}/exercise-lists/${list.id}`}
                            className="block bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition-shadow"
                        >
                            <div className="flex items-start justify-between gap-4">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 mb-1">
                                        <h3 className="text-sm font-semibold text-gray-900 truncate">{list.title}</h3>
                                        {list.is_overdue && (
                                            <Badge label="Atrasada" className="bg-rose-50 text-rose-700 border border-rose-200" />
                                        )}
                                        {list.my_submission?.completed && (
                                            <Badge label="Concluida" className="bg-emerald-50 text-emerald-700 border border-emerald-200" />
                                        )}
                                    </div>
                                    {list.description && (
                                        <p className="text-xs text-gray-500 line-clamp-2 mb-2">{list.description}</p>
                                    )}
                                    <div className="flex items-center gap-4 text-xs text-gray-400">
                                        <span>{list.exercises_count} {list.exercises_count === 1 ? 'exercicio' : 'exercicios'}</span>
                                        {list.due_date && (
                                            <span>Prazo: {formatDate(list.due_date)}</span>
                                        )}
                                        {list.creator && (
                                            <span>Por {list.creator.name}</span>
                                        )}
                                        {list.submissions_count !== undefined && !list.my_submission && (
                                            <span>{list.submissions_count} {list.submissions_count === 1 ? 'entrega' : 'entregas'}</span>
                                        )}
                                    </div>
                                </div>
                                <div className="flex-shrink-0">
                                    <svg className="w-5 h-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                    </svg>
                                </div>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
