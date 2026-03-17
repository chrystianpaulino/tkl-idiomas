import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import { Head, useForm, Link } from '@inertiajs/react';
import { useRef, useState } from 'react';

function UploadIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
        </svg>
    );
}

function DocumentIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
    );
}

export default function MaterialsCreate({ turmaClass }) {
    const { data, setData, post, processing, errors, progress } = useForm({
        title: '',
        description: '',
        file: null,
    });

    const fileInputRef = useRef(null);
    const [isDragging, setIsDragging] = useState(false);

    function submit(e) {
        e.preventDefault();
        post(`/classes/${turmaClass.id}/materials`, { forceFormData: true });
    }

    function handleDrop(e) {
        e.preventDefault();
        setIsDragging(false);
        const file = e.dataTransfer.files[0];
        if (file) setData('file', file);
    }

    function handleDragOver(e) {
        e.preventDefault();
        setIsDragging(true);
    }

    function handleDragLeave() {
        setIsDragging(false);
    }

    function formatFileSize(bytes) {
        if (!bytes) return '';
        const mb = bytes / (1024 * 1024);
        return mb >= 1 ? `${mb.toFixed(1)} MB` : `${(bytes / 1024).toFixed(0)} KB`;
    }

    return (
        <AppLayout title="Enviar Material">
            <Head title="Enviar Material" />
            <PageHeader title="Enviar Material" subtitle={turmaClass.name}>
                <Link href={`/classes/${turmaClass.id}/materials`} className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 19l-7-7 7-7" /></svg>
                    Voltar
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 max-w-2xl">
                <div className="space-y-6">
                    {/* File Dropzone */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1.5">Arquivo</label>
                        <div
                            onDrop={handleDrop}
                            onDragOver={handleDragOver}
                            onDragLeave={handleDragLeave}
                            onClick={() => fileInputRef.current?.click()}
                            className={`relative border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-colors ${
                                isDragging
                                    ? 'border-indigo-400 bg-indigo-50'
                                    : data.file
                                        ? 'border-emerald-300 bg-emerald-50'
                                        : 'border-gray-300 hover:border-gray-400 hover:bg-gray-50'
                            }`}
                            role="button"
                            tabIndex={0}
                            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInputRef.current?.click(); } }}
                            aria-label="Selecionar arquivo para upload"
                        >
                            <input
                                ref={fileInputRef}
                                type="file"
                                onChange={(e) => setData('file', e.target.files[0])}
                                accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.mp3,.zip"
                                className="sr-only"
                            />

                            {data.file ? (
                                <div className="flex flex-col items-center gap-2">
                                    <div className="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                        <DocumentIcon className="w-6 h-6" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">{data.file.name}</p>
                                        <p className="text-xs text-gray-500 mt-0.5">{formatFileSize(data.file.size)}</p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={(e) => { e.stopPropagation(); setData('file', null); }}
                                        className="text-xs text-rose-600 hover:text-rose-700 font-medium mt-1"
                                    >
                                        Remover arquivo
                                    </button>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center gap-3">
                                    <div className="w-12 h-12 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center">
                                        <UploadIcon className="w-6 h-6" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-700">
                                            Clique ou arraste o arquivo
                                        </p>
                                        <p className="text-xs text-gray-500 mt-1">
                                            PDF, DOC, PPT, MP4, MP3, ZIP - maximo 50MB
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>
                        {errors.file && <p className="mt-1.5 text-xs text-rose-600">{errors.file}</p>}
                        {progress && (
                            <div className="mt-3">
                                <div className="flex items-center justify-between mb-1">
                                    <span className="text-xs text-gray-500">Enviando...</span>
                                    <span className="text-xs font-medium text-indigo-600">{progress.percentage}%</span>
                                </div>
                                <div className="w-full bg-gray-200 rounded-full h-1.5">
                                    <div className="bg-indigo-600 h-1.5 rounded-full transition-all" style={{ width: `${progress.percentage}%` }} />
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Title */}
                    <div>
                        <label htmlFor="title" className="block text-sm font-medium text-gray-700 mb-1.5">Titulo</label>
                        <input
                            id="title"
                            type="text"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder="Ex: Apostila Unidade 3"
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow"
                        />
                        {errors.title && <p className="mt-1.5 text-xs text-rose-600">{errors.title}</p>}
                    </div>

                    {/* Description */}
                    <div>
                        <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1.5">
                            Descricao <span className="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            rows={3}
                            placeholder="Descreva brevemente o conteudo do material..."
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow resize-none"
                        />
                    </div>
                </div>

                <div className="flex items-center gap-3 mt-8 pt-6 border-t border-gray-100">
                    <button
                        type="submit"
                        disabled={processing || !data.file}
                        className="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium disabled:opacity-50 transition-colors"
                    >
                        {processing ? 'Enviando...' : 'Enviar Material'}
                    </button>
                    <Link href={`/classes/${turmaClass.id}/materials`} className="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                        Cancelar
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
