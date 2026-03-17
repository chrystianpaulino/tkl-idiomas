import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Avatar from '@/Components/Avatar';
import Badge from '@/Components/Badge';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

function UsersIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
    );
}

function BookOpenIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
        </svg>
    );
}

function PaperClipIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
        </svg>
    );
}

function DownloadIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
        </svg>
    );
}

function ClipboardIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
        </svg>
    );
}

function TabButton({ active, children, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`px-4 py-2.5 text-sm font-medium rounded-xl transition-colors ${
                active
                    ? 'bg-indigo-600 text-white shadow-sm'
                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
            }`}
        >
            {children}
        </button>
    );
}

export default function ClassesShow({ turmaClass, can, availableStudents }) {
    const [activeTab, setActiveTab] = useState('students');
    const enrollForm = useForm({ student_id: '' });

    function submitEnroll(e) {
        e.preventDefault();
        enrollForm.post(`/admin/classes/${turmaClass.id}/enroll`, { onSuccess: () => enrollForm.reset() });
    }

    const students = turmaClass.students ?? [];
    const lessons = turmaClass.lessons ?? [];
    const materials = turmaClass.materials ?? [];
    const exerciseListsCount = turmaClass.exercise_lists_count ?? 0;

    return (
        <AppLayout title={turmaClass.name}>
            <Head title={turmaClass.name} />

            {/* Hero Section */}
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div className="flex-1">
                        <h1 className="text-2xl font-bold text-gray-900 mb-2">{turmaClass.name}</h1>
                        {turmaClass.description && (
                            <p className="text-sm text-gray-500 mb-4 max-w-2xl">{turmaClass.description}</p>
                        )}
                        {turmaClass.professor && (
                            <div className="flex items-center gap-3">
                                <Avatar name={turmaClass.professor.name} size="md" />
                                <div>
                                    <p className="text-sm font-medium text-gray-900">{turmaClass.professor.name}</p>
                                    <p className="text-xs text-gray-500">Professor</p>
                                </div>
                            </div>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can?.registerLesson && (
                            <Link href={`/classes/${turmaClass.id}/lessons/create`} className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                                <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                Registrar Aula
                            </Link>
                        )}
                        {can?.uploadMaterial && (
                            <Link href={`/classes/${turmaClass.id}/materials/create`} className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                                <PaperClipIcon className="w-4 h-4" />
                                Adicionar Material
                            </Link>
                        )}
                        {can?.enroll && (
                            <Link href={`#enroll`} onClick={(e) => { e.preventDefault(); setActiveTab('students'); }} className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                                <UsersIcon className="w-4 h-4" />
                                Matricular Aluno
                            </Link>
                        )}
                        {can?.edit && (
                            <Link href={`/classes/${turmaClass.id}/edit`} className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                                <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"><path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" /></svg>
                                Editar
                            </Link>
                        )}
                    </div>
                </div>
            </div>

            {/* Tabs */}
            <div className="flex items-center gap-1 bg-gray-100 rounded-xl p-1 mb-6 w-fit">
                <TabButton active={activeTab === 'students'} onClick={() => setActiveTab('students')}>
                    Alunos ({students.length})
                </TabButton>
                <TabButton active={activeTab === 'lessons'} onClick={() => setActiveTab('lessons')}>
                    Aulas ({lessons.length})
                </TabButton>
                <TabButton active={activeTab === 'materials'} onClick={() => setActiveTab('materials')}>
                    Materiais ({materials.length})
                </TabButton>
                <TabButton active={activeTab === 'exercises'} onClick={() => setActiveTab('exercises')}>
                    Exercicios ({exerciseListsCount})
                </TabButton>
            </div>

            {/* Tab Content */}
            {activeTab === 'students' && (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div className="px-6 py-4 border-b border-gray-100">
                        <h3 className="text-sm font-semibold text-gray-900">Alunos Matriculados</h3>
                    </div>

                    {students.length === 0 ? (
                        <div className="py-16 text-center">
                            <div className="w-12 h-12 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-3">
                                <UsersIcon className="w-6 h-6" />
                            </div>
                            <p className="text-sm text-gray-500">Nenhum aluno matriculado.</p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-gray-100">
                            {students.map((s) => {
                                const remaining = s.remaining_lessons ?? s.pivot?.remaining_lessons ?? 0;
                                return (
                                    <li key={s.id} className="px-6 py-4 flex items-center gap-4 hover:bg-gray-50/50 transition-colors">
                                        <Avatar name={s.name} size="md" />
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-900">{s.name}</p>
                                            {s.email && <p className="text-xs text-gray-500">{s.email}</p>}
                                        </div>
                                        <Badge
                                            label={`${remaining} aulas restantes`}
                                            className={
                                                remaining > 0
                                                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                    : 'bg-rose-50 text-rose-700 border border-rose-200'
                                            }
                                        />
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    {/* Enroll form */}
                    {can?.enroll && availableStudents?.length > 0 && (
                        <div className="px-6 py-4 border-t border-gray-100 bg-gray-50/50 rounded-b-2xl">
                            <form onSubmit={submitEnroll} className="flex items-center gap-3">
                                <select
                                    value={enrollForm.data.student_id}
                                    onChange={(e) => enrollForm.setData('student_id', e.target.value)}
                                    className="flex-1 border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                                >
                                    <option value="">Selecionar aluno para matricular...</option>
                                    {availableStudents.map((s) => (
                                        <option key={s.id} value={s.id}>{s.name}</option>
                                    ))}
                                </select>
                                <button
                                    type="submit"
                                    disabled={enrollForm.processing || !enrollForm.data.student_id}
                                    className="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium disabled:opacity-50 transition-colors"
                                >
                                    Matricular
                                </button>
                            </form>
                        </div>
                    )}
                </div>
            )}

            {activeTab === 'lessons' && (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-gray-900">Historico de Aulas</h3>
                        <Link href={`/classes/${turmaClass.id}/lessons`} className="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                            Ver todas
                        </Link>
                    </div>

                    {lessons.length === 0 ? (
                        <div className="py-16 text-center">
                            <div className="w-12 h-12 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-3">
                                <BookOpenIcon className="w-6 h-6" />
                            </div>
                            <p className="text-sm text-gray-500">Nenhuma aula registrada.</p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-gray-100">
                            {lessons.map((l) => (
                                <li key={l.id} className="px-6 py-4 flex items-center gap-4 hover:bg-gray-50/50 transition-colors">
                                    <div className="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0 text-xs font-bold">
                                        {l.conducted_at ? new Date(l.conducted_at).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' }).replace('. de ', '/').replace('.', '') : '--'}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">{l.title}</p>
                                        <p className="text-xs text-gray-500 mt-0.5">
                                            {l.student?.name} &middot; Prof. {l.professor?.name}
                                        </p>
                                    </div>
                                    <span className="text-xs text-gray-400 flex-shrink-0">
                                        {l.conducted_at ? new Date(l.conducted_at).toLocaleDateString('pt-BR') : '\u2014'}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {activeTab === 'exercises' && (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-sm font-semibold text-gray-900">Listas de Exercicios</h3>
                        <Link
                            href={`/classes/${turmaClass.id}/exercise-lists`}
                            className="text-xs text-indigo-600 hover:text-indigo-700 font-medium"
                        >
                            Ver todas
                        </Link>
                    </div>
                    {exerciseListsCount === 0 ? (
                        <div className="py-12 text-center">
                            <div className="w-12 h-12 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-3">
                                <ClipboardIcon className="w-6 h-6" />
                            </div>
                            <p className="text-sm text-gray-500 mb-4">Nenhuma lista de exercicios.</p>
                            {can?.createExerciseList && (
                                <Link
                                    href={`/classes/${turmaClass.id}/exercise-lists/create`}
                                    className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                                >
                                    Criar Lista
                                </Link>
                            )}
                        </div>
                    ) : (
                        <div className="text-center">
                            <Link
                                href={`/classes/${turmaClass.id}/exercise-lists`}
                                className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                            >
                                <ClipboardIcon className="w-4 h-4" />
                                Ver {exerciseListsCount} {exerciseListsCount === 1 ? 'Lista' : 'Listas'}
                            </Link>
                        </div>
                    )}
                </div>
            )}

            {activeTab === 'materials' && (
                <div>
                    {materials.length === 0 ? (
                        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm py-16 text-center">
                            <div className="w-12 h-12 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-3">
                                <PaperClipIcon className="w-6 h-6" />
                            </div>
                            <p className="text-sm text-gray-500 mb-4">Nenhum material enviado.</p>
                            {can?.uploadMaterial && (
                                <Link href={`/classes/${turmaClass.id}/materials/create`} className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                                    Adicionar Material
                                </Link>
                            )}
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            {materials.map((m) => {
                                const ext = m.file_name?.split('.').pop()?.toLowerCase() ?? '';
                                const iconColors = {
                                    pdf: 'bg-rose-50 text-rose-600',
                                    mp4: 'bg-violet-50 text-violet-600',
                                    mp3: 'bg-amber-50 text-amber-600',
                                    doc: 'bg-sky-50 text-sky-600',
                                    docx: 'bg-sky-50 text-sky-600',
                                    zip: 'bg-gray-100 text-gray-600',
                                };
                                const iconBg = iconColors[ext] ?? 'bg-indigo-50 text-indigo-600';

                                return (
                                    <div key={m.id} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex flex-col">
                                        <div className="flex items-start gap-3 mb-3">
                                            <div className={`w-10 h-10 rounded-xl ${iconBg} flex items-center justify-center flex-shrink-0`}>
                                                <PaperClipIcon className="w-5 h-5" />
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-semibold text-gray-900 truncate">{m.title}</p>
                                                {m.description && <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">{m.description}</p>}
                                            </div>
                                        </div>

                                        {m.uploader && (
                                            <div className="flex items-center gap-2 mb-3">
                                                <Avatar name={m.uploader.name} size="sm" />
                                                <span className="text-xs text-gray-500">{m.uploader.name}</span>
                                            </div>
                                        )}

                                        <div className="mt-auto pt-3 border-t border-gray-100 flex items-center gap-3">
                                            <a
                                                href={m.download_url ?? `/materials/${m.id}/download`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-700 transition-colors"
                                            >
                                                <DownloadIcon className="w-4 h-4" />
                                                Download
                                            </a>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}
        </AppLayout>
    );
}
