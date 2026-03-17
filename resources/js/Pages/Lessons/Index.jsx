import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Avatar from '@/Components/Avatar';
import { Head, Link, usePage, router } from '@inertiajs/react';

function BookOpenIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
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

function TrashIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
        </svg>
    );
}

export default function LessonsIndex({ turmaClass, lessons }) {
    const { auth } = usePage().props;
    const canManage = auth?.user?.role === 'admin' || auth?.user?.role === 'professor';
    const list = lessons?.data ?? lessons ?? [];

    return (
        <AppLayout title={`Aulas - ${turmaClass.name}`}>
            <Head title={`Aulas - ${turmaClass.name}`} />
            <PageHeader title="Aulas" subtitle={turmaClass.name}>
                <div className="flex items-center gap-2">
                    <Link href={`/classes/${turmaClass.id}`} className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                        <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 19l-7-7 7-7" /></svg>
                        Voltar
                    </Link>
                    {canManage && (
                        <Link href={`/classes/${turmaClass.id}/lessons/create`} className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                            <PlusIcon className="w-4 h-4" />
                            Registrar Aula
                        </Link>
                    )}
                </div>
            </PageHeader>

            {list.length === 0 ? (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
                    <div className="w-16 h-16 rounded-2xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-4">
                        <BookOpenIcon className="w-8 h-8" />
                    </div>
                    <h3 className="text-base font-semibold text-gray-900 mb-1">Nenhuma aula registrada</h3>
                    <p className="text-sm text-gray-500 mb-6">Registre a primeira aula desta turma.</p>
                    {canManage && (
                        <Link href={`/classes/${turmaClass.id}/lessons/create`} className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-colors">
                            <PlusIcon className="w-4 h-4" />
                            Registrar Aula
                        </Link>
                    )}
                </div>
            ) : (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <ul className="divide-y divide-gray-100">
                        {list.map((lesson) => (
                            <li key={lesson.id} className="px-6 py-4 flex items-center gap-4 hover:bg-gray-50/50 transition-colors">
                                <div className="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
                                    <div className="text-center leading-tight">
                                        <span className="text-xs font-bold block">
                                            {lesson.conducted_at ? new Date(lesson.conducted_at).toLocaleDateString('pt-BR', { day: '2-digit' }) : '--'}
                                        </span>
                                        <span className="text-[10px] uppercase">
                                            {lesson.conducted_at ? new Date(lesson.conducted_at).toLocaleDateString('pt-BR', { month: 'short' }).replace('.', '') : ''}
                                        </span>
                                    </div>
                                </div>

                                <Avatar name={lesson.student?.name ?? 'A'} size="sm" />

                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium text-gray-900 truncate">{lesson.title}</p>
                                    <p className="text-xs text-gray-500 mt-0.5">
                                        {lesson.student?.name} &middot; Prof. {lesson.professor?.name}
                                    </p>
                                    {lesson.notes && (
                                        <p className="text-xs text-gray-400 mt-1 truncate">{lesson.notes}</p>
                                    )}
                                </div>

                                <span className="text-xs text-gray-400 flex-shrink-0 hidden sm:block">
                                    {lesson.conducted_at ? new Date(lesson.conducted_at).toLocaleDateString('pt-BR') : '\u2014'}
                                </span>

                                {canManage && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (confirm('Remover esta aula?')) {
                                                router.delete(`/classes/${turmaClass.id}/lessons/${lesson.id}`);
                                            }
                                        }}
                                        className="text-gray-400 hover:text-rose-600 transition-colors flex-shrink-0"
                                        aria-label="Remover aula"
                                    >
                                        <TrashIcon className="w-4 h-4" />
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </AppLayout>
    );
}
