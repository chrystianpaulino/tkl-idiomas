import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import { Head, useForm, Link, usePage } from '@inertiajs/react';

const DEFAULT_PRIMARY = '#4f46e5';
const DEFAULT_SECONDARY = '#0f172a';

export default function SchoolsEdit({ school }) {
    const { auth } = usePage().props;
    const base = auth?.user?.role === 'super_admin' ? '/platform/schools' : '/admin/schools';

    const { data, setData, post, processing, errors } = useForm({
        // POST + _method=PUT is required when sending multipart/form-data files.
        _method: 'put',
        name: school.name ?? '',
        slug: school.slug ?? '',
        email: school.email ?? '',
        active: school.active ?? true,
        logo: null,
        remove_logo: false,
        primary_color: school.primary_color ?? DEFAULT_PRIMARY,
        secondary_color: school.secondary_color ?? DEFAULT_SECONDARY,
    });

    function submit(e) {
        e.preventDefault();
        post(`${base}/${school.id}`, { forceFormData: true });
    }

    function handleRemoveLogo() {
        setData((prev) => ({ ...prev, logo: null, remove_logo: true }));
    }

    const inputClass =
        'w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow';

    // Build absolute URL for the existing logo. The backend already returns
    // the public storage URL via Storage::disk('public')->url(...), so we just
    // surface it directly. We hide the preview the moment the user clicks
    // "Remover logo" so the UX matches the pending state.
    const logoUrl = school.logo_url
        ? (school.logo_url.startsWith('http') ? school.logo_url : `/storage/${school.logo_url}`)
        : null;

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
                            className={inputClass}
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
                            className={inputClass}
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

                    {/* ── Identidade Visual ────────────────────────── */}
                    <div className="border-t border-gray-100 pt-6">
                        <h2 className="text-sm font-semibold text-gray-900 mb-1">Identidade Visual</h2>
                        <p className="text-xs text-gray-500 mb-4">
                            Logo e cores aplicados em toda a área autenticada da escola.
                        </p>

                        <div className="space-y-5">
                            <div>
                                <label htmlFor="logo" className="block text-sm font-medium text-gray-700 mb-1.5">
                                    Logo
                                    <span className="ml-2 text-xs font-normal text-gray-400">(PNG, JPG ou SVG, máx. 2MB)</span>
                                </label>
                                {logoUrl && !data.remove_logo && (
                                    <div className="mb-3 flex items-center gap-3">
                                        <img
                                            src={logoUrl}
                                            alt={school.name}
                                            className="h-12 w-auto rounded-lg border border-gray-200 bg-slate-900 p-2"
                                        />
                                        <button
                                            type="button"
                                            onClick={handleRemoveLogo}
                                            className="text-sm text-rose-600 hover:text-rose-700 transition-colors"
                                        >
                                            Remover logo
                                        </button>
                                    </div>
                                )}
                                {data.remove_logo && (
                                    <p className="mb-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                        Logo será removido ao salvar.
                                    </p>
                                )}
                                <input
                                    id="logo"
                                    type="file"
                                    accept="image/png,image/jpeg,image/svg+xml"
                                    onChange={(e) => setData((prev) => ({
                                        ...prev,
                                        logo: e.target.files?.[0] ?? null,
                                        remove_logo: false,
                                    }))}
                                    className="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                />
                                {errors.logo && <p className="mt-1.5 text-xs text-rose-600">{errors.logo}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label htmlFor="primary_color" className="block text-sm font-medium text-gray-700 mb-1.5">
                                        Cor Primária
                                        <span className="ml-2 text-xs font-normal text-gray-400">(botões, destaques)</span>
                                    </label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            id="primary_color"
                                            type="color"
                                            value={data.primary_color}
                                            onChange={(e) => setData('primary_color', e.target.value)}
                                            className="h-10 w-14 rounded-lg border border-gray-300 cursor-pointer"
                                        />
                                        <input
                                            type="text"
                                            value={data.primary_color}
                                            onChange={(e) => setData('primary_color', e.target.value)}
                                            placeholder="#4f46e5"
                                            className={inputClass}
                                        />
                                    </div>
                                    {errors.primary_color && <p className="mt-1.5 text-xs text-rose-600">{errors.primary_color}</p>}
                                </div>

                                <div>
                                    <label htmlFor="secondary_color" className="block text-sm font-medium text-gray-700 mb-1.5">
                                        Cor Secundária
                                        <span className="ml-2 text-xs font-normal text-gray-400">(menu lateral)</span>
                                    </label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            id="secondary_color"
                                            type="color"
                                            value={data.secondary_color}
                                            onChange={(e) => setData('secondary_color', e.target.value)}
                                            className="h-10 w-14 rounded-lg border border-gray-300 cursor-pointer"
                                        />
                                        <input
                                            type="text"
                                            value={data.secondary_color}
                                            onChange={(e) => setData('secondary_color', e.target.value)}
                                            placeholder="#0f172a"
                                            className={inputClass}
                                        />
                                    </div>
                                    {errors.secondary_color && <p className="mt-1.5 text-xs text-rose-600">{errors.secondary_color}</p>}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-3 mt-8 pt-6 border-t border-gray-100">
                    <button
                        type="submit"
                        disabled={processing}
                        className="text-white px-6 py-2.5 rounded-xl text-sm font-medium disabled:opacity-50 transition-colors"
                        style={{ backgroundColor: 'var(--color-primary, #4f46e5)' }}
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
