import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import Avatar from '@/Components/Avatar';
import { Head, Link, usePage, router } from '@inertiajs/react';

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

function TrashIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
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

function getFileIcon(fileName) {
    const ext = fileName?.split('.').pop()?.toLowerCase() ?? '';
    const config = {
        pdf: { bg: 'bg-rose-50', color: 'text-rose-600', label: 'PDF' },
        mp4: { bg: 'bg-violet-50', color: 'text-violet-600', label: 'Video' },
        mp3: { bg: 'bg-amber-50', color: 'text-amber-600', label: 'Audio' },
        doc: { bg: 'bg-sky-50', color: 'text-sky-600', label: 'DOC' },
        docx: { bg: 'bg-sky-50', color: 'text-sky-600', label: 'DOC' },
        ppt: { bg: 'bg-orange-50', color: 'text-orange-600', label: 'PPT' },
        pptx: { bg: 'bg-orange-50', color: 'text-orange-600', label: 'PPT' },
        zip: { bg: 'bg-gray-100', color: 'text-gray-600', label: 'ZIP' },
    };
    return config[ext] ?? { bg: 'bg-indigo-50', color: 'text-indigo-600', label: 'FILE' };
}

export default function MaterialsIndex({ turmaClass, materials }) {
    const { auth } = usePage().props;
    const canManage = auth?.user?.role === 'admin' || auth?.user?.role === 'professor';
    const list = materials ?? [];

    return (
        <AppLayout title={`Materiais - ${turmaClass.name}`}>
            <Head title={`Materiais - ${turmaClass.name}`} />
            <PageHeader title="Materiais" subtitle={turmaClass.name}>
                <div className="flex items-center gap-2">
                    <Link href={`/classes/${turmaClass.id}`} className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                        <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 19l-7-7 7-7" /></svg>
                        Voltar
                    </Link>
                    {canManage && (
                        <Link href={`/classes/${turmaClass.id}/materials/create`} className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                            <PlusIcon className="w-4 h-4" />
                            Adicionar Material
                        </Link>
                    )}
                </div>
            </PageHeader>

            {list.length === 0 ? (
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
                    <div className="w-16 h-16 rounded-2xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-4">
                        <PaperClipIcon className="w-8 h-8" />
                    </div>
                    <h3 className="text-base font-semibold text-gray-900 mb-1">Nenhum material enviado</h3>
                    <p className="text-sm text-gray-500 mb-6">Envie materiais de apoio para os alunos desta turma.</p>
                    {canManage && (
                        <Link href={`/classes/${turmaClass.id}/materials/create`} className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-colors">
                            <PlusIcon className="w-4 h-4" />
                            Enviar primeiro material
                        </Link>
                    )}
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    {list.map((m) => {
                        const fileInfo = getFileIcon(m.file_name);
                        return (
                            <div key={m.id} className="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow flex flex-col">
                                <div className="p-5 flex-1">
                                    <div className="flex items-start gap-3 mb-4">
                                        <div className={`w-11 h-11 rounded-xl ${fileInfo.bg} ${fileInfo.color} flex items-center justify-center flex-shrink-0`}>
                                            <span className="text-[10px] font-bold uppercase">{fileInfo.label}</span>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <h3 className="text-sm font-semibold text-gray-900 truncate">{m.title}</h3>
                                            {m.description && (
                                                <p className="text-xs text-gray-500 mt-1 line-clamp-2">{m.description}</p>
                                            )}
                                        </div>
                                    </div>

                                    {m.uploader && (
                                        <div className="flex items-center gap-2">
                                            <Avatar name={m.uploader.name} size="sm" />
                                            <span className="text-xs text-gray-500">{m.uploader.name}</span>
                                        </div>
                                    )}
                                </div>

                                <div className="px-5 py-3.5 border-t border-gray-100 flex items-center justify-between">
                                    <a
                                        href={m.download_url ?? `/materials/${m.id}/download`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-700 transition-colors"
                                    >
                                        <DownloadIcon className="w-4 h-4" />
                                        Download
                                    </a>
                                    {canManage && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (confirm('Remover este material?')) {
                                                    router.delete(`/classes/${turmaClass.id}/materials/${m.id}`);
                                                }
                                            }}
                                            className="text-gray-400 hover:text-rose-600 transition-colors"
                                            aria-label="Remover material"
                                        >
                                            <TrashIcon className="w-4 h-4" />
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </AppLayout>
    );
}
