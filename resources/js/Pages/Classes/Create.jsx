import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import { Head, useForm, Link } from '@inertiajs/react';

export default function ClassesCreate({ professors }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        professor_id: '',
        description: '',
    });

    function submit(e) {
        e.preventDefault();
        post('/classes');
    }

    return (
        <AppLayout title="Nova Turma">
            <Head title="Nova Turma" />
            <PageHeader title="Nova Turma" subtitle="Preencha as informacoes da nova turma">
                <Link href="/classes" className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 19l-7-7 7-7" /></svg>
                    Voltar
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 max-w-2xl">
                <div className="space-y-6">
                    <div>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1.5">Nome da Turma</label>
                        <input
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Ex: Turma A - Iniciante"
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow"
                        />
                        {errors.name && <p className="mt-1.5 text-xs text-rose-600">{errors.name}</p>}
                    </div>

                    <div>
                        <label htmlFor="professor_id" className="block text-sm font-medium text-gray-700 mb-1.5">Professor</label>
                        <select
                            id="professor_id"
                            value={data.professor_id}
                            onChange={(e) => setData('professor_id', e.target.value)}
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow bg-white"
                        >
                            <option value="">Selecione um professor</option>
                            {professors?.map((p) => (
                                <option key={p.id} value={p.id}>{p.name}</option>
                            ))}
                        </select>
                        {errors.professor_id && <p className="mt-1.5 text-xs text-rose-600">{errors.professor_id}</p>}
                    </div>

                    <div>
                        <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1.5">
                            Descricao <span className="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            rows={4}
                            placeholder="Descreva o objetivo desta turma..."
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow resize-none"
                        />
                    </div>
                </div>

                <div className="flex items-center gap-3 mt-8 pt-6 border-t border-gray-100">
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium disabled:opacity-50 transition-colors"
                    >
                        {processing ? 'Criando...' : 'Criar Turma'}
                    </button>
                    <Link href="/classes" className="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                        Cancelar
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
