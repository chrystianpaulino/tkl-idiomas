import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import { Head, useForm, Link, usePage } from '@inertiajs/react';

export default function SchoolsEdit({ school }) {
    const { auth } = usePage().props;
    const base = auth?.user?.role === 'super_admin' ? '/platform/schools' : '/admin/schools';

    const { data, setData, put, processing, errors } = useForm({
        name: school.name ?? '',
        slug: school.slug ?? '',
        email: school.email ?? '',
        active: school.active ?? true,
    });

    function submit(e) {
        e.preventDefault();
        put(`${base}/${school.id}`);
    }

    return (
        <AppLayout title="Editar Escola">
            <Head title="Editar Escola" />
            <PageHeader title="Editar Escola" subtitle={school.name}>
                <Link
                    href={base}
                    className="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                >
                    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M15 19l-7-7 7-7" />
                    </svg>
                    Voltar
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 max-w-2xl">
                <div className="space-y-6">
                    <div>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1.5">Nome da Escola</label>
                        <input
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Ex: Escola de Idiomas Centro"
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow"
                        />
                        {errors.name && <p className="mt-1.5 text-xs text-rose-600">{errors.name}</p>}
                    </div>

                    <div>
                        <label htmlFor="slug" className="block text-sm font-medium text-gray-700 mb-1.5">
                            Slug
                            <span className="ml-2 text-xs font-normal text-gray-400">(identificador único)</span>
                        </label>
                        <div className="flex items-center border border-gray-300 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500 transition-shadow">
                            <span className="px-3.5 py-2.5 bg-gray-50 border-r border-gray-200 text-sm text-gray-400 select-none flex-shrink-0">
                                slug /
                            </span>
                            <input
                                id="slug"
                                type="text"
                                value={data.slug}
                                onChange={(e) => setData('slug', e.target.value)}
                                className="flex-1 px-4 py-2.5 text-sm outline-none bg-white"
                            />
                        </div>
                        {errors.slug && <p className="mt-1.5 text-xs text-rose-600">{errors.slug}</p>}
                    </div>

                    <div>
                        <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1.5">
                            Email de Contato
                            <span className="ml-2 text-xs font-normal text-gray-400">(opcional)</span>
                        </label>
                        <input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="contato@escola.com"
                            className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow"
                        />
                        {errors.email && <p className="mt-1.5 text-xs text-rose-600">{errors.email}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                        <button
                            type="button"
                            onClick={() => setData('active', !data.active)}
                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none ${data.active ? 'bg-indigo-600' : 'bg-gray-200'}`}
                        >
                            <span className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${data.active ? 'translate-x-6' : 'translate-x-1'}`} />
                        </button>
                        <span className="ml-3 text-sm text-gray-600">{data.active ? 'Ativa' : 'Inativa'}</span>
                    </div>
                </div>

                <div className="flex items-center gap-3 mt-8 pt-6 border-t border-gray-100">
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium disabled:opacity-50 transition-colors"
                    >
                        {processing ? 'Salvando...' : 'Salvar Alterações'}
                    </button>
                    <Link href={base} className="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                        Cancelar
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
