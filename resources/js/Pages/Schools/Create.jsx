import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import { Head, useForm, Link, usePage } from '@inertiajs/react';

function slugify(text) {
    return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim();
}

// Defaults mirror Tailwind indigo-600 / slate-900 — kept in sync with the
// migration-level column defaults so a school created without customization
// renders identically to the legacy hardcoded UI.
const DEFAULT_PRIMARY = '#4f46e5';
const DEFAULT_SECONDARY = '#0f172a';

export default function SchoolsCreate() {
    const { auth } = usePage().props;
    const base = auth?.user?.role === 'super_admin' ? '/platform/schools' : '/admin/schools';

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        email: '',
        logo: null,
        primary_color: DEFAULT_PRIMARY,
        secondary_color: DEFAULT_SECONDARY,
        admin_name: '',
        admin_email: '',
        admin_password: '',
    });

    function handleNameChange(value) {
        setData((prev) => ({
            ...prev,
            name: value,
            slug: slugify(value),
        }));
    }

    function submit(e) {
        e.preventDefault();
        // Inertia automatically detects File payloads and uses multipart/form-data.
        post(base, { forceFormData: true });
    }

    const inputClass =
        'w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-shadow';

    return (
        <AppLayout title="Nova Escola">
            <Head title="Nova Escola" />
            <PageHeader title="Nova Escola" subtitle="Cadastrar uma nova escola no sistema">
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

            <form onSubmit={submit} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 max-w-2xl space-y-8">

                {/* ── Dados da Escola ───────────────────────────────── */}
                <div>
                    <h2 className="text-sm font-semibold text-gray-900 mb-4">Dados da Escola</h2>
                    <div className="space-y-5">
                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1.5">
                                Nome da Escola
                            </label>
                            <input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(e) => handleNameChange(e.target.value)}
                                placeholder="Ex: Escola de Idiomas Centro"
                                className={inputClass}
                            />
                            {errors.name && <p className="mt-1.5 text-xs text-rose-600">{errors.name}</p>}
                        </div>

                        <div>
                            <label htmlFor="slug" className="block text-sm font-medium text-gray-700 mb-1.5">
                                Slug
                                <span className="ml-2 text-xs font-normal text-gray-400">(gerado automaticamente, editável)</span>
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
                                    placeholder="minha-escola"
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
                    </div>
                </div>

                {/* ── Identidade Visual ─────────────────────────────── */}
                <div className="border-t border-gray-100 pt-8">
                    <h2 className="text-sm font-semibold text-gray-900 mb-1">Identidade Visual</h2>
                    <p className="text-xs text-gray-500 mb-4">
                        Logo e cores da escola. Os alunos verão essas marcas após login.
                    </p>
                    <div className="space-y-5">
                        <div>
                            <label htmlFor="logo" className="block text-sm font-medium text-gray-700 mb-1.5">
                                Logo
                                <span className="ml-2 text-xs font-normal text-gray-400">(PNG, JPG ou SVG, máx. 2MB)</span>
                            </label>
                            <input
                                id="logo"
                                type="file"
                                accept="image/png,image/jpeg,image/svg+xml"
                                onChange={(e) => setData('logo', e.target.files?.[0] ?? null)}
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

                {/* ── Administrador da Escola ───────────────────────── */}
                <div className="border-t border-gray-100 pt-8">
                    <h2 className="text-sm font-semibold text-gray-900 mb-1">Administrador da Escola</h2>
                    <p className="text-xs text-gray-500 mb-4">
                        Esta conta será criada automaticamente e terá acesso total à escola.
                    </p>
                    <div className="space-y-5">
                        <div>
                            <label htmlFor="admin_name" className="block text-sm font-medium text-gray-700 mb-1.5">
                                Nome
                            </label>
                            <input
                                id="admin_name"
                                type="text"
                                value={data.admin_name}
                                onChange={(e) => setData('admin_name', e.target.value)}
                                placeholder="Ex: João Silva"
                                className={inputClass}
                            />
                            {errors.admin_name && <p className="mt-1.5 text-xs text-rose-600">{errors.admin_name}</p>}
                        </div>

                        <div>
                            <label htmlFor="admin_email" className="block text-sm font-medium text-gray-700 mb-1.5">
                                Email
                            </label>
                            <input
                                id="admin_email"
                                type="email"
                                value={data.admin_email}
                                onChange={(e) => setData('admin_email', e.target.value)}
                                placeholder="admin@escola.com"
                                className={inputClass}
                            />
                            {errors.admin_email && <p className="mt-1.5 text-xs text-rose-600">{errors.admin_email}</p>}
                        </div>

                        <div>
                            <label htmlFor="admin_password" className="block text-sm font-medium text-gray-700 mb-1.5">
                                Senha Temporária
                            </label>
                            <input
                                id="admin_password"
                                type="password"
                                value={data.admin_password}
                                onChange={(e) => setData('admin_password', e.target.value)}
                                placeholder="Mínimo 8 caracteres"
                                className={inputClass}
                                autoComplete="new-password"
                            />
                            {errors.admin_password && <p className="mt-1.5 text-xs text-rose-600">{errors.admin_password}</p>}
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-3 pt-2 border-t border-gray-100">
                    <button
                        type="submit"
                        disabled={processing}
                        className="text-white px-6 py-2.5 rounded-xl text-sm font-medium disabled:opacity-50 transition-colors"
                        style={{ backgroundColor: 'var(--color-primary, #4f46e5)' }}
                    >
                        {processing ? 'Criando...' : 'Criar Escola'}
                    </button>
                    <Link href={base} className="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                        Cancelar
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
