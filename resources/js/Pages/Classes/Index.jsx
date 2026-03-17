import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Avatar from '@/Components/Avatar';
import Badge from '@/Components/Badge';
import { Head, Link, router, useForm } from '@inertiajs/react';

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

function SearchIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
    );
}

export default function ClassesIndex({ classes, can, filters }) {
    const list = classes?.data ?? classes ?? [];

    const { data, setData } = useForm({ search: filters?.search ?? '' });

    function applySearch(e) {
        if (e) e.preventDefault();
        router.get('/classes', { search: data.search || undefined }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Turmas">
            <Head title="Turmas" />
            <PageHeader title="Turmas" subtitle={`${list.length} turma${list.length !== 1 ? 's' : ''} cadastrada${list.length !== 1 ? 's' : ''}`}>
                {can?.create && (
                    <Link href="/classes/create" className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                        <PlusIcon className="w-4 h-4" />
                        Nova Turma
                    </Link>
                )}
            </PageHeader>

            {/* Search bar */}
            {filters !== undefined && (
                <form onSubmit={applySearch} className="mb-6">
                    <div className="relative max-w-md">
                        <SearchIcon className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Buscar turmas..."
                            value={data.search}
                            onChange={(e) => setData('search', e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applySearch()}
                            className="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                        />
                    </div>
                </form>
            )}

            {list.length === 0 ? (
                /* Empty State */
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 px-6 text-center">
                    <div className="w-16 h-16 rounded-2xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-4">
                        <BookOpenIcon className="w-8 h-8" />
                    </div>
                    <h3 className="text-base font-semibold text-gray-900 mb-1">Nenhuma turma encontrada</h3>
                    <p className="text-sm text-gray-500 mb-6 max-w-sm mx-auto">
                        Comece criando uma nova turma para organizar seus alunos e aulas.
                    </p>
                    {can?.create && (
                        <Link href="/classes/create" className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-colors">
                            <PlusIcon className="w-4 h-4" />
                            Criar primeira turma
                        </Link>
                    )}
                </div>
            ) : (
                /* Card Grid */
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    {list.map((cls) => (
                        <div key={cls.id} className="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow flex flex-col">
                            <div className="p-6 flex-1">
                                <div className="flex items-start justify-between mb-3">
                                    <h3 className="text-base font-semibold text-gray-900 leading-snug">{cls.name}</h3>
                                    {cls.students_count !== undefined && (
                                        <Badge
                                            label={`${cls.students_count ?? cls.students?.length ?? 0} alunos`}
                                            className="bg-indigo-50 text-indigo-700 border border-indigo-200 flex-shrink-0 ml-2"
                                        />
                                    )}
                                </div>

                                {cls.professor && (
                                    <div className="flex items-center gap-2 mb-3">
                                        <Avatar name={cls.professor.name} size="sm" />
                                        <span className="text-sm text-gray-600">{cls.professor.name}</span>
                                    </div>
                                )}

                                {cls.description && (
                                    <p className="text-sm text-gray-500 line-clamp-2">{cls.description}</p>
                                )}
                            </div>

                            <div className="px-6 py-4 border-t border-gray-100 flex items-center gap-2">
                                <Link
                                    href={`/classes/${cls.id}`}
                                    className="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-700 transition-colors"
                                >
                                    Ver detalhes
                                    <svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 5l7 7-7 7" /></svg>
                                </Link>

                                {can?.edit && (
                                    <Link
                                        href={`/classes/${cls.id}/edit`}
                                        className="ml-auto text-sm text-gray-500 hover:text-gray-700 transition-colors"
                                    >
                                        Editar
                                    </Link>
                                )}

                                {can?.delete && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (confirm('Tem certeza que deseja excluir esta turma?')) {
                                                router.delete(`/classes/${cls.id}`);
                                            }
                                        }}
                                        className="text-sm text-rose-500 hover:text-rose-700 transition-colors"
                                    >
                                        Excluir
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
